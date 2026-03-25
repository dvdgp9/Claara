-- Migración 014: Renombrar gesto "project-admin"

UPDATE available_features
SET name = 'Análisis Eco Proy.'
WHERE feature_type = 'gesture'
  AND feature_slug = 'project-admin';
