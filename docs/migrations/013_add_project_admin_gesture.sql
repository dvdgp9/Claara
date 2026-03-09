-- Migración 013: Añadir gesto "Admin Proyectos" al catálogo de features
-- Fecha: 2026-03-09

-- Añadir el gesto de administración de proyectos (project-admin)
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) 
VALUES ('gesture', 'project-admin', 'Admin Proyectos', 'Analiza pliegos de concursos públicos y extrae gastos no personales, horas de trabajo y otra información clave', 'iconoir-folder-settings', 9, 1)
ON DUPLICATE KEY UPDATE 
  name = VALUES(name), 
  description = VALUES(description), 
  icon = VALUES(icon),
  is_active = VALUES(is_active);

-- Dar acceso al superadmin (user_id=1) si existe
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT 1, 'gesture', 'project-admin', 1
FROM users WHERE id = 1 AND is_superadmin = 1;
