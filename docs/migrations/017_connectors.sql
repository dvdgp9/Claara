-- Migration 017: External connectors foundation
-- Date: 2026-05-15
-- Description: Shared data model for Google Drive, OneDrive, Slack and Teams connectors.

CREATE TABLE IF NOT EXISTS connector_providers (
  provider_key VARCHAR(60) NOT NULL PRIMARY KEY,
  display_name VARCHAR(120) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  icon VARCHAR(120) DEFAULT NULL,
  auth_type ENUM('oauth2') NOT NULL DEFAULT 'oauth2',
  is_enabled TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  INDEX idx_connector_providers_enabled_sort (is_enabled, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS connector_accounts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(60) NOT NULL,
  external_account_id VARCHAR(255) DEFAULT NULL,
  external_email VARCHAR(190) DEFAULT NULL,
  external_name VARCHAR(190) DEFAULT NULL,
  display_name VARCHAR(190) DEFAULT NULL,
  scopes TEXT DEFAULT NULL,
  status ENUM('connected', 'disconnected', 'error', 'needs_attention') NOT NULL DEFAULT 'connected',
  last_sync_at DATETIME DEFAULT NULL,
  last_error_message TEXT DEFAULT NULL,
  connected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  disconnected_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_connector_accounts_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_connector_accounts_provider
    FOREIGN KEY (provider_key) REFERENCES connector_providers(provider_key) ON DELETE RESTRICT ON UPDATE CASCADE,

  UNIQUE KEY uniq_connector_account_external (provider_key, external_account_id, user_id),
  INDEX idx_connector_accounts_user_provider (user_id, provider_key),
  INDEX idx_connector_accounts_status (status),
  INDEX idx_connector_accounts_external_email (external_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS connector_tokens (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  encrypted_access_token MEDIUMTEXT DEFAULT NULL,
  encrypted_refresh_token MEDIUMTEXT DEFAULT NULL,
  token_type VARCHAR(40) DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  scopes TEXT DEFAULT NULL,
  metadata JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_connector_tokens_account
    FOREIGN KEY (account_id) REFERENCES connector_accounts(id) ON DELETE CASCADE,

  UNIQUE KEY uniq_connector_tokens_account (account_id),
  INDEX idx_connector_tokens_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS connector_items (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  account_id BIGINT UNSIGNED NOT NULL,
  provider_key VARCHAR(60) NOT NULL,
  external_item_id VARCHAR(512) NOT NULL,
  item_type ENUM('file', 'folder', 'channel', 'message', 'team', 'chat', 'unknown') NOT NULL DEFAULT 'unknown',
  name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(190) DEFAULT NULL,
  source_url VARCHAR(1024) DEFAULT NULL,
  external_version VARCHAR(190) DEFAULT NULL,
  checksum VARCHAR(128) DEFAULT NULL,
  size_bytes BIGINT UNSIGNED DEFAULT NULL,
  status ENUM('selected', 'queued', 'importing', 'imported', 'error', 'removed') NOT NULL DEFAULT 'selected',
  metadata JSON DEFAULT NULL,
  selected_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_imported_at DATETIME DEFAULT NULL,
  last_error_message TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_connector_items_account
    FOREIGN KEY (account_id) REFERENCES connector_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_connector_items_provider
    FOREIGN KEY (provider_key) REFERENCES connector_providers(provider_key) ON DELETE RESTRICT ON UPDATE CASCADE,

  UNIQUE KEY uniq_connector_items_external (account_id, external_item_id(191)),
  INDEX idx_connector_items_account_status (account_id, status),
  INDEX idx_connector_items_provider_status (provider_key, status),
  INDEX idx_connector_items_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS connector_imports (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  item_id BIGINT UNSIGNED NOT NULL,
  account_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  job_id INT DEFAULT NULL,
  context_target VARCHAR(60) NOT NULL DEFAULT 'lex',
  context_document_id BIGINT UNSIGNED DEFAULT NULL,
  status ENUM('queued', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'queued',
  error_message TEXT DEFAULT NULL,
  import_metadata JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_connector_imports_item
    FOREIGN KEY (item_id) REFERENCES connector_items(id) ON DELETE CASCADE,
  CONSTRAINT fk_connector_imports_account
    FOREIGN KEY (account_id) REFERENCES connector_accounts(id) ON DELETE CASCADE,
  CONSTRAINT fk_connector_imports_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_connector_imports_context_document
    FOREIGN KEY (context_document_id) REFERENCES context_documents(id) ON DELETE SET NULL,

  INDEX idx_connector_imports_item_status (item_id, status),
  INDEX idx_connector_imports_user_created (user_id, created_at),
  INDEX idx_connector_imports_job (job_id),
  INDEX idx_connector_imports_context_document (context_document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO connector_providers (provider_key, display_name, description, icon, auth_type, is_enabled, sort_order)
VALUES
  ('google_drive', 'Google Drive', 'Import selected files from Google Drive.', 'iconoir-google-drive', 'oauth2', 1, 10),
  ('onedrive', 'OneDrive', 'Import selected files from Microsoft OneDrive.', 'iconoir-cloud', 'oauth2', 0, 20),
  ('slack', 'Slack', 'Import selected Slack channels, messages, or files.', 'iconoir-slack', 'oauth2', 0, 30),
  ('teams', 'Microsoft Teams', 'Import selected Teams content after tenant admin consent.', 'iconoir-microsoft-teams', 'oauth2', 0, 40)
ON DUPLICATE KEY UPDATE
  display_name = VALUES(display_name),
  description = VALUES(description),
  icon = VALUES(icon),
  auth_type = VALUES(auth_type),
  is_enabled = VALUES(is_enabled),
  sort_order = VALUES(sort_order);

