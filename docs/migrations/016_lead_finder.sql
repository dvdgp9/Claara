-- Migration 016: Lead Finder gesture
-- Date: 2026-05-13
-- Description: Stores Lead Finder searches, editable lead results, and feature access registration.

CREATE TABLE IF NOT EXISTS lead_finder_runs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  job_id INT DEFAULT NULL,
  query TEXT NOT NULL,
  max_results INT NOT NULL DEFAULT 25,
  provider VARCHAR(80) NOT NULL DEFAULT 'mock',
  status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
  error_message TEXT DEFAULT NULL,
  results_count INT NOT NULL DEFAULT 0,
  validated_count INT NOT NULL DEFAULT 0,
  rejected_count INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME DEFAULT NULL,
  completed_at DATETIME DEFAULT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_lead_finder_runs_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

  INDEX idx_lead_finder_runs_user_created (user_id, created_at),
  INDEX idx_lead_finder_runs_status (status),
  INDEX idx_lead_finder_runs_job (job_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lead_finder_results (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  run_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(255) NOT NULL,
  website VARCHAR(512) DEFAULT NULL,
  email VARCHAR(255) DEFAULT NULL,
  phone VARCHAR(80) DEFAULT NULL,
  address VARCHAR(512) DEFAULT NULL,
  source_url VARCHAR(1024) DEFAULT NULL,
  confidence DECIMAL(5,2) DEFAULT NULL,
  status ENUM('pending', 'validated', 'rejected') NOT NULL DEFAULT 'pending',
  raw_data JSON DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_lead_finder_results_run
    FOREIGN KEY (run_id) REFERENCES lead_finder_runs(id) ON DELETE CASCADE,

  INDEX idx_lead_finder_results_run (run_id),
  INDEX idx_lead_finder_results_status (status),
  INDEX idx_lead_finder_results_email (email),
  INDEX idx_lead_finder_results_website (website(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active)
VALUES (
  'gesture',
  'lead-finder',
  'Lead Finder',
  'Find, review, validate, and export structured leads from a natural-language search.',
  'iconoir-search-window',
  10,
  1
) ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  icon = VALUES(icon),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT id, 'gesture', 'lead-finder', 1
FROM users
WHERE is_superadmin = 1;
