-- Migration 024: Voice access profiles & document folders
-- Date: 2026-06-25
-- Description: Foundation for per-voice access control.
--   * Access profiles scoped to a voice (e.g. standard / manager / board).
--   * A folder tree per voice for organizing knowledge documents.
--   * Folder -> profile access (a grant inherits down the tree).
--   * User -> profile assignment (one profile per user per voice = voice access).
--   * folder_id on context_documents so each document belongs to a folder.
--
-- IMPORTANT: production `schema_migrations` is known to drift (several applied
-- migrations are not registered). This file is written to be idempotent on
-- MariaDB 10.x/11.x (IF NOT EXISTS on tables, columns, and indexes) and must be
-- applied on its own, NOT through a full migrate.php run on production.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 1. Access profiles, scoped to a voice.
CREATE TABLE IF NOT EXISTS voice_access_profiles (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voice_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  description VARCHAR(300) NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Seeded full-access profile created during backfill',
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_voice_access_profiles_slug (voice_id, slug),
  KEY idx_voice_access_profiles_voice (voice_id),
  CONSTRAINT fk_voice_access_profiles_voice
    FOREIGN KEY (voice_id) REFERENCES voices(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Folder tree per voice.
-- `path` is a materialized id-path including self, e.g. '/12/' for a root and
-- '/12/30/' for its child. Descendants (inclusive) of folder G satisfy
-- `path LIKE CONCAT(G.path, '%')`, which is how access inherits down the tree.
CREATE TABLE IF NOT EXISTS voice_folders (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voice_id BIGINT UNSIGNED NOT NULL,
  parent_id BIGINT UNSIGNED NULL,
  name VARCHAR(255) NOT NULL,
  path VARCHAR(1000) NOT NULL DEFAULT '/',
  depth INT NOT NULL DEFAULT 0,
  is_root TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_voice_folders_voice_parent (voice_id, parent_id),
  KEY idx_voice_folders_path (voice_id, path),
  CONSTRAINT fk_voice_folders_voice
    FOREIGN KEY (voice_id) REFERENCES voices(id) ON DELETE CASCADE,
  CONSTRAINT fk_voice_folders_parent
    FOREIGN KEY (parent_id) REFERENCES voice_folders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Which profiles can access a folder. A grant inherits down to descendants.
CREATE TABLE IF NOT EXISTS folder_profile_access (
  folder_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (folder_id, profile_id),
  KEY idx_folder_profile_access_profile (profile_id),
  CONSTRAINT fk_folder_profile_access_folder
    FOREIGN KEY (folder_id) REFERENCES voice_folders(id) ON DELETE CASCADE,
  CONSTRAINT fk_folder_profile_access_profile
    FOREIGN KEY (profile_id) REFERENCES voice_access_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. User -> profile assignment. One profile per (user, voice); a row here means
-- the user has access to that voice (at that profile's level).
CREATE TABLE IF NOT EXISTS user_voice_profiles (
  user_id BIGINT UNSIGNED NOT NULL,
  voice_id BIGINT UNSIGNED NOT NULL,
  profile_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, voice_id),
  KEY idx_user_voice_profiles_voice (voice_id),
  KEY idx_user_voice_profiles_profile (profile_id),
  CONSTRAINT fk_user_voice_profiles_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_voice_profiles_voice
    FOREIGN KEY (voice_id) REFERENCES voices(id) ON DELETE CASCADE,
  CONSTRAINT fk_user_voice_profiles_profile
    FOREIGN KEY (profile_id) REFERENCES voice_access_profiles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Attach each voice document to a folder. NULL means "not yet placed"; the
-- backfill script moves every existing document into its voice root folder.
-- No FK here on purpose: keeps this migration idempotent on a drift-prone prod,
-- and referential integrity for folder_id is enforced in the application layer
-- (folder deletes reassign documents to the voice root).
ALTER TABLE context_documents
  ADD COLUMN IF NOT EXISTS folder_id BIGINT UNSIGNED NULL AFTER voice_id;

ALTER TABLE context_documents
  ADD INDEX IF NOT EXISTS idx_context_documents_folder (folder_id);
