-- Migration 026: Global access LEVELS model
-- Date: 2026-06-26
-- Description: Moves access levels from per-voice to ORG-GLOBAL, so a person has
--   ONE rank everywhere and a voice simply declares the minimum rank required to
--   enter (or, for sensitive voices, an explicit allow-list of people).
--
--   * `access_levels`        : global ordered levels (Technician < Manager < ...).
--   * `users.access_level_id`: each person's single global level.
--   * `voices.access_mode`   : 'level' (min rank to enter) | 'list' (named users).
--   * `voices.min_access_level_id` : minimum global level for 'level' mode
--                                     (NULL = everyone).
--   * `voice_access_list`    : explicit (voice,user) grants for 'list' mode.
--   * `voice_folders.required_level_id` is REUSED but, after the 026 backfill,
--     references `access_levels` (global) instead of `voice_access_profiles`.
--
--   This migration is ADDITIVE ONLY. The legacy tables (voice_access_profiles,
--   user_voice_profiles, folder_profile_access) are left untouched here; the
--   backfill script performs the data cutover and a LATER migration drops them
--   once the new model is verified live.
--
-- IMPORTANT: production `schema_migrations` is known to drift. This file is
-- idempotent (IF NOT EXISTS on tables/columns/indexes) and must be applied on
-- its own, NOT through a full migrate.php run on production. Take a DB backup
-- first.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Global, ordered access levels. Higher `rank` = more access.
--    `is_default` marks the level new users receive automatically.
CREATE TABLE IF NOT EXISTS access_levels (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  `rank` INT NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Level assigned to new users by default',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_access_levels_slug (slug),
  KEY idx_access_levels_rank (`rank`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Each user's single global level. NULL = no level yet (no level-mode access);
--    the backfill assigns the default level to existing users.
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS access_level_id BIGINT UNSIGNED NULL AFTER is_superadmin;

ALTER TABLE users
  ADD INDEX IF NOT EXISTS idx_users_access_level (access_level_id);

-- 3. Per-voice access policy.
--    access_mode = 'level' -> enter if user rank >= min_access_level rank
--                             (min_access_level_id NULL = everyone).
--    access_mode = 'list'  -> enter only if listed in voice_access_list.
ALTER TABLE voices
  ADD COLUMN IF NOT EXISTS access_mode VARCHAR(10) NOT NULL DEFAULT 'level'
    COMMENT "'level' = minimum global level; 'list' = explicit users";

ALTER TABLE voices
  ADD COLUMN IF NOT EXISTS min_access_level_id BIGINT UNSIGNED NULL AFTER access_mode;

-- 4. Explicit per-voice allow-list (used when access_mode = 'list').
CREATE TABLE IF NOT EXISTS voice_access_list (
  voice_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (voice_id, user_id),
  KEY idx_voice_access_list_user (user_id),
  CONSTRAINT fk_voice_access_list_voice
    FOREIGN KEY (voice_id) REFERENCES voices(id) ON DELETE CASCADE,
  CONSTRAINT fk_voice_access_list_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Seed a single default global level so existing users have somewhere to land.
--    The admin will define the real ladder (Technician/Manager/Director) from the
--    UI; this guarantees a non-empty, sane starting point. Idempotent via slug.
INSERT INTO access_levels (name, slug, `rank`, is_default, sort_order)
SELECT 'Member', 'member', 10, 1, 0
WHERE NOT EXISTS (SELECT 1 FROM access_levels WHERE slug = 'member');
