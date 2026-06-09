-- Migration 023: Shared conversations
-- Date: 2026-06-09
-- Description: Adds user/department conversation sharing and a lightweight AI run lock.

CREATE TABLE IF NOT EXISTS conversation_shares (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id BIGINT UNSIGNED NOT NULL,
  target_type ENUM('user','department') NOT NULL,
  target_id BIGINT UNSIGNED NOT NULL,
  permission ENUM('view','chat') NOT NULL DEFAULT 'view',
  created_by BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY conversation_shares_target_uq (conversation_id, target_type, target_id),
  KEY conversation_shares_target_idx (target_type, target_id),
  KEY conversation_shares_permission_idx (conversation_id, permission),
  KEY conversation_shares_created_by_idx (created_by),

  CONSTRAINT fk_conversation_shares_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  CONSTRAINT fk_conversation_shares_created_by
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE conversations
  ADD COLUMN ai_status ENUM('idle','responding') NOT NULL DEFAULT 'idle' AFTER metadata,
  ADD COLUMN ai_started_at DATETIME NULL AFTER ai_status,
  ADD COLUMN ai_locked_by_message_id BIGINT UNSIGNED NULL AFTER ai_started_at,
  ADD KEY conversations_ai_status_idx (ai_status, ai_started_at),
  ADD KEY conversations_ai_locked_message_idx (ai_locked_by_message_id);
