-- Migration 021: Organization responsibilities
-- Date: 2026-06-08
-- Description: Adds user job titles and many-to-many responsibility links for departments and voices.

ALTER TABLE users
  ADD COLUMN job_title VARCHAR(120) NULL AFTER last_name;

CREATE TABLE IF NOT EXISTS department_responsibles (
  department_id BIGINT UNSIGNED NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (department_id, user_id),
  KEY idx_department_responsibles_user (user_id),
  CONSTRAINT fk_department_responsibles_department
    FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE CASCADE,
  CONSTRAINT fk_department_responsibles_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS voice_responsibles (
  voice_slug VARCHAR(80) NOT NULL,
  user_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

  PRIMARY KEY (voice_slug, user_id),
  KEY idx_voice_responsibles_user (user_id),
  KEY idx_voice_responsibles_slug (voice_slug),
  CONSTRAINT fk_voice_responsibles_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
