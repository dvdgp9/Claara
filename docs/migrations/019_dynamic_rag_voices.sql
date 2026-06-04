-- Migration 019: Dynamic RAG voices foundation
-- Date: 2026-06-04
-- Description: Adds database support for frontend-managed RAG voices, flexible voice documents, and voice editor permissions.

-- Some existing environments already have these tables from later feature work.
-- Keep these guards here so fresh environments can apply the voice-editor seed safely.
CREATE TABLE IF NOT EXISTS available_features (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  feature_type VARCHAR(40) NOT NULL,
  feature_slug VARCHAR(120) NOT NULL,
  name VARCHAR(120) NOT NULL,
  description VARCHAR(300) DEFAULT NULL,
  icon VARCHAR(120) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uniq_available_features_type_slug (feature_type, feature_slug),
  INDEX idx_available_features_type_active_sort (feature_type, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_feature_access (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  feature_type VARCHAR(40) NOT NULL,
  feature_slug VARCHAR(120) NOT NULL,
  enabled TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  CONSTRAINT fk_user_feature_access_user
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,

  UNIQUE KEY uniq_user_feature_access_user_feature (user_id, feature_type, feature_slug),
  INDEX idx_user_feature_access_feature (feature_type, feature_slug, enabled)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE voices
  ADD COLUMN slug VARCHAR(80) NULL AFTER id,
  ADD COLUMN role VARCHAR(120) NULL AFTER name,
  ADD COLUMN instructions MEDIUMTEXT NULL AFTER system_prompt,
  ADD COLUMN trigger_guidance TEXT NULL AFTER instructions,
  ADD COLUMN status ENUM('draft', 'published', 'archived') NOT NULL DEFAULT 'draft' AFTER trigger_guidance,
  ADD COLUMN rag_collection VARCHAR(120) NULL AFTER status,
  ADD COLUMN icon VARCHAR(120) NULL AFTER rag_collection,
  ADD COLUMN color VARCHAR(40) NULL AFTER icon,
  ADD COLUMN created_by BIGINT UNSIGNED NULL AFTER metadata,
  ADD COLUMN published_at DATETIME NULL AFTER created_by,
  ADD UNIQUE KEY voices_slug_uq (slug),
  ADD KEY voices_status_idx (status),
  ADD KEY voices_created_by_idx (created_by),
  ADD CONSTRAINT fk_voices_created_by
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL;

-- Seed Lex into the dynamic voices catalog without depending on the old hardcoded PHP array.
INSERT INTO voices (
  slug,
  name,
  role,
  description,
  provider,
  model,
  system_prompt,
  instructions,
  trigger_guidance,
  status,
  rag_collection,
  icon,
  color,
  visibility,
  metadata,
  created_by,
  published_at,
  created_at,
  updated_at
)
SELECT
  'lex',
  'Lex',
  'Legal Assistant',
  'Expert in collective agreements, labor rules, and legal reference documents.',
  'other',
  'google/gemini-3-flash-preview',
  NULL,
  'Answer legal and labor questions using only indexed reference documents. Cite exact sources, state uncertainty clearly, and surface conflicts when documents disagree.',
  'Use Lex when the user asks about labor rules, collective agreements, leave, rights, procedures, legal documentation, or employee policy interpretation.',
  'published',
  'lex_knowledge_base',
  'iconoir-book-stack',
  'rose',
  'global',
  JSON_OBJECT('seeded_from', '019_dynamic_rag_voices', 'rag_required', true),
  (SELECT id FROM users WHERE is_superadmin = 1 ORDER BY id LIMIT 1),
  NOW(),
  NOW(),
  NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM voices WHERE slug = 'lex'
);

UPDATE voices
SET
  role = COALESCE(role, 'Legal Assistant'),
  status = 'published',
  rag_collection = COALESCE(rag_collection, 'lex_knowledge_base'),
  icon = COALESCE(icon, 'iconoir-book-stack'),
  color = COALESCE(color, 'rose'),
  trigger_guidance = COALESCE(trigger_guidance, 'Use Lex when the user asks about labor rules, collective agreements, leave, rights, procedures, legal documentation, or employee policy interpretation.'),
  published_at = COALESCE(published_at, NOW()),
  updated_at = NOW()
WHERE slug = 'lex';

ALTER TABLE context_documents
  ADD COLUMN target_type ENUM('legacy', 'voice', 'faq', 'chat') NOT NULL DEFAULT 'legacy' AFTER target,
  ADD COLUMN target_slug VARCHAR(80) NULL AFTER target_type,
  ADD COLUMN voice_id BIGINT UNSIGNED NULL AFTER target_slug,
  ADD COLUMN indexed_at DATETIME NULL AFTER rag_error_message,
  ADD KEY idx_context_documents_target_type_slug (target_type, target_slug),
  ADD KEY idx_context_documents_voice_id (voice_id),
  ADD CONSTRAINT fk_context_documents_voice_id
    FOREIGN KEY (voice_id) REFERENCES voices(id) ON DELETE SET NULL;

UPDATE context_documents cd
LEFT JOIN voices v ON v.slug = 'lex'
SET
  cd.target_type = 'voice',
  cd.target_slug = 'lex',
  cd.voice_id = v.id,
  cd.indexed_at = CASE WHEN cd.rag_status = 'processed' THEN COALESCE(cd.indexed_at, cd.updated_at) ELSE cd.indexed_at END
WHERE cd.target = 'lex';

UPDATE context_documents
SET target_type = 'faq', target_slug = 'eboniato'
WHERE target = 'eboniato';

UPDATE context_documents
SET target_type = 'chat', target_slug = 'ebonia'
WHERE target = 'ebonia';

INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active)
VALUES
  (
    'feature',
    'voice-editor',
    'Voice editor',
    'Create, configure, test, and publish RAG voices from the admin interface.',
    'iconoir-voice-square',
    20,
    1
  ),
  (
    'voice',
    'lex',
    'Lex',
    'Legal and labor RAG assistant with indexed reference documents.',
    'iconoir-book-stack',
    10,
    1
  )
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  icon = VALUES(icon),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);

INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT id, 'feature', 'voice-editor', 1
FROM users
WHERE is_superadmin = 1;

INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT id, 'voice', 'lex', 1
FROM users
WHERE is_superadmin = 1;
