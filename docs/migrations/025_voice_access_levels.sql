-- Migration 025: Voice access LEVELS model
-- Date: 2026-06-26
-- Description: Replaces the folder×profile grant matrix with a simpler ordered
--   "access level" model. Each access profile becomes a ranked LEVEL; each
--   folder declares a single minimum level required to read it; a user sees a
--   folder when their level rank >= the folder's required level rank.
--   Higher rank = more access. required_level_id NULL = everyone (with the voice).
--
--   We reuse voice_access_profiles (now "levels", ordered by `rank`) and add a
--   single required_level_id per folder. folder_profile_access is left in place
--   but no longer used by the resolver. Idempotent for MariaDB.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE voice_access_profiles
  ADD COLUMN IF NOT EXISTS `rank` INT NOT NULL DEFAULT 0 AFTER is_default;

ALTER TABLE voice_folders
  ADD COLUMN IF NOT EXISTS required_level_id BIGINT UNSIGNED NULL AFTER is_root;

ALTER TABLE voice_folders
  ADD INDEX IF NOT EXISTS idx_voice_folders_required_level (required_level_id);

-- Existing seeded "Full access" profiles become the top level so that nobody
-- loses access. Folders keep required_level_id = NULL (everyone) by default.
UPDATE voice_access_profiles SET `rank` = 100 WHERE is_default = 1 AND `rank` = 0;
