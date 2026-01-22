-- Migración 012: Añadir gesto "Creador de Cursos" al catálogo de features
-- Fecha: 2026-01-22

-- Añadir el gesto de creador de cursos (course-creator)
INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) 
VALUES ('gesture', 'course-creator', 'Creador de Cursos', 'Genera material formativo completo a partir de PDFs: temario, fichas, quizzes, flashcards, podcasts educativos y exámenes', 'iconoir-graduation-cap', 8, 1)
ON DUPLICATE KEY UPDATE 
  name = VALUES(name), 
  description = VALUES(description), 
  icon = VALUES(icon),
  is_active = VALUES(is_active);

-- Dar acceso al superadmin (user_id=1) si existe
INSERT IGNORE INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
SELECT 1, 'gesture', 'course-creator', 1
FROM users WHERE id = 1 AND is_superadmin = 1;
