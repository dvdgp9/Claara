-- Migración 008: Añadir gesto "Editor de imágenes" al catálogo de features
-- Fecha: 2025-01-03

-- Añadir el gesto de editor de imágenes
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) 
VALUES ('gesture', 'image-editor', 'Editor de imágenes', 'Genera imágenes corporativas con Nanobanana 🍌', 'iconoir-media-image', 4, 1)
ON DUPLICATE KEY UPDATE 
  name = VALUES(name), 
  description = VALUES(description), 
  icon = VALUES(icon),
  is_active = VALUES(is_active);

-- Dar acceso al superadmin (user_id=1) si existe
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT 1, 'gesture', 'image-editor', 1
FROM users WHERE id = 1 AND is_superadmin = 1;
