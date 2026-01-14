-- Migración: Añadir gesto SOP Generator
-- Fecha: 2026-01-14
-- Descripción: Añade el gesto sop-generator a la tabla de gestos y permisos

-- Insertar el gesto en la tabla gestures si existe
INSERT IGNORE INTO gestures (name, slug, description, icon, is_active, created_at)
VALUES (
    'Generador de SOPs',
    'sop-generator',
    'Transforma información desestructurada en procedimientos operativos estándar con diagramas de flujo',
    'iconoir-clipboard-check',
    1,
    NOW()
);

-- Dar acceso a todos los usuarios activos (ajustar según política de la empresa)
-- Opción 1: Dar acceso a superadmins solamente
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_id, granted_at)
SELECT id, 'gesture', 'sop-generator', NOW()
FROM users 
WHERE role = 'superadmin' AND is_active = 1;

-- Opción 2 (alternativa): Dar acceso a todos los usuarios activos
-- INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_id, granted_at)
-- SELECT id, 'gesture', 'sop-generator', NOW()
-- FROM users 
-- WHERE is_active = 1;

-- Para dar acceso manual a un usuario específico:
-- INSERT INTO user_feature_access (user_id, feature_type, feature_id, granted_at)
-- VALUES (<USER_ID>, 'gesture', 'sop-generator', NOW());
