-- Migration 022: Voice flags
-- Date: 2026-06-08
-- Description: User-raised reports ("flags") about a voice (missing info, incorrect
-- answer, etc.). Routed to the voice's responsibles via voice_responsibles.voice_slug.
-- Slug-based on purpose (no FK to voices), mirroring voice_responsibles.

CREATE TABLE IF NOT EXISTS voice_flags (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  voice_slug VARCHAR(80) NULL,
  raised_by_user_id BIGINT UNSIGNED NULL,
  conversation_id BIGINT UNSIGNED NULL,
  message_id BIGINT UNSIGNED NULL,
  type ENUM('missing_info','incorrect','other') NOT NULL DEFAULT 'missing_info',
  note TEXT NULL,
  status ENUM('open','in_progress','resolved','dismissed') NOT NULL DEFAULT 'open',
  resolved_by_user_id BIGINT UNSIGNED NULL,
  resolution_note TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  KEY idx_voice_flags_slug (voice_slug),
  KEY idx_voice_flags_status (status),
  KEY idx_voice_flags_raised_by (raised_by_user_id),
  KEY idx_voice_flags_slug_status (voice_slug, status),

  CONSTRAINT fk_voice_flags_raised_by
    FOREIGN KEY (raised_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_voice_flags_conversation
    FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
  CONSTRAINT fk_voice_flags_message
    FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE SET NULL,
  CONSTRAINT fk_voice_flags_resolved_by
    FOREIGN KEY (resolved_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
