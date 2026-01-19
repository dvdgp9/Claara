-- Migración: Añadir gesto Transcriptor de Audio
-- Ejecutar en producción después de desplegar el código

-- 1. Registrar el gesto en available_features
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active)
VALUES (
    'gesture',
    'audio-transcriber',
    'Transcriptor de audio',
    'Convierte archivos de audio en texto. Ideal para transcribir grabaciones, reuniones o notas de voz.',
    'iconoir-microphone',
    70,
    1
) ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    description = VALUES(description),
    icon = VALUES(icon),
    is_active = 1;

-- 2. Dar acceso a superadmins automáticamente (ya tienen acceso por defecto)
-- Para usuarios normales, asignar permisos manualmente desde el panel de admin

-- 3. Si quieres dar acceso a todos los usuarios actuales que tienen otros gestos:
-- INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
-- SELECT DISTINCT user_id, 'gesture', 'audio-transcriber', 1
-- FROM user_feature_access
-- WHERE feature_type = 'gesture' AND enabled = 1
-- ON DUPLICATE KEY UPDATE enabled = 1;
