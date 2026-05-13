-- Migración 015: Catálogo editable de modelos LLM (superadmin)

CREATE TABLE IF NOT EXISTS llm_models (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  model_key VARCHAR(120) NOT NULL,
  label VARCHAR(120) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_model_key (model_key),
  KEY idx_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO llm_models (model_key, label, is_active, sort_order)
SELECT * FROM (
  SELECT 'google/gemini-3-flash-preview' AS model_key, 'Gemini 3 Flash' AS label, 1 AS is_active, 10 AS sort_order
  UNION ALL SELECT 'google/gemini-3.1-flash-lite-preview', 'Gemini 3.1 Flash Lite Preview', 1, 20
  UNION ALL SELECT 'anthropic/claude-sonnet-4.6', 'Claude Sonnet 4.6', 1, 30
  UNION ALL SELECT 'deepseek/deepseek-v3.2', 'Deepseek v3.2', 1, 40
  UNION ALL SELECT 'z-ai/glm-4.7', 'GLM 4.7', 1, 50
  UNION ALL SELECT 'xiaomi/mimo-v2-flash:free', 'Xiaomi Mimo v2', 1, 60
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM llm_models LIMIT 1);
