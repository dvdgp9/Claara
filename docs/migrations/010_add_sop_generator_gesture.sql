-- Migración 010: Añadir gesto "Generador de SOPs" al catálogo de features
-- Fecha: 2026-01-14

-- Añadir el gesto de generador de SOPs (sop-generator)
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) 
VALUES ('gesture', 'sop-generator', 'Generador de SOPs', 'Transforma texto, audio o imágenes en procedimientos estructurados con diagramas y documentos', 'iconoir-clipboard-check', 6, 1)
ON DUPLICATE KEY UPDATE 
  name = VALUES(name), 
  description = VALUES(description), 
  icon = VALUES(icon),
  is_active = VALUES(is_active);

-- Dar acceso al superadmin (user_id=1) si existe
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT 1, 'gesture', 'sop-generator', 1
FROM users WHERE id = 1 AND is_superadmin = 1;
