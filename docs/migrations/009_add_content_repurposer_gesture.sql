-- Migración 009: Añadir gesto "Transformador de contenido" al catálogo de features
-- Fecha: 2025-01-08

-- Añadir el gesto de transformador de contenido (content repurposer)
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) 
VALUES ('gesture', 'content-repurposer', 'Transformador de contenido', 'Convierte contenido en posts, blogs, landing pages, newsletters o FAQs', 'iconoir-refresh-double', 5, 1)
ON DUPLICATE KEY UPDATE 
  name = VALUES(name), 
  description = VALUES(description), 
  icon = VALUES(icon),
  is_active = VALUES(is_active);

-- Dar acceso al superadmin (user_id=1) si existe
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT 1, 'gesture', 'content-repurposer', 1
FROM users WHERE id = 1 AND is_superadmin = 1;
