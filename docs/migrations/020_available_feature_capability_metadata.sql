-- Migration 020: capability metadata for available features
-- Date: 2026-06-05
-- Description: Stores capability routing guidance in the feature catalog so chat context is database-driven.

ALTER TABLE available_features
  ADD COLUMN route VARCHAR(255) NULL AFTER icon,
  ADD COLUMN trigger_guidance TEXT NULL AFTER route,
  ADD COLUMN input_schema JSON NULL AFTER trigger_guidance;

INSERT INTO available_features (
  feature_type,
  feature_slug,
  name,
  description,
  icon,
  route,
  trigger_guidance,
  input_schema,
  sort_order,
  is_active
)
VALUES
  (
    'gesture',
    'write-article',
    'Write article',
    'Generate articles, blogs, and press notes.',
    'iconoir-page-edit',
    '/gestos/escribir-articulo.php',
    'Use when the user wants to draft or refine a long-form article from a topic, brief, source notes, or reference material.',
    JSON_OBJECT('summary', 'topic, audience, tone, source notes'),
    1,
    1
  ),
  (
    'gesture',
    'social-media',
    'Social media',
    'Create posts for social channels.',
    'iconoir-send-diagonal',
    '/gestos/redes-sociales.php',
    'Use when the user wants to turn a campaign idea, article, announcement, or brief into social media posts.',
    JSON_OBJECT('summary', 'source content, channel, tone, number of posts'),
    2,
    1
  ),
  (
    'gesture',
    'podcast-from-article',
    'Podcast from article',
    'Turn articles into AI-generated podcasts.',
    'iconoir-podcast',
    '/gestos/podcast-articulo.php',
    'Use when the user wants to transform an article, report, or URL into a podcast script or audio workflow.',
    JSON_OBJECT('summary', 'article text or URL, podcast style, duration'),
    3,
    1
  ),
  (
    'gesture',
    'image-editor',
    'Image editor',
    'Edit, adapt, or generate visual assets.',
    'iconoir-media-image',
    '/gestos/editor-imagenes.php',
    'Use when the user wants to edit, adapt, or generate instructions for an image or visual asset.',
    JSON_OBJECT('summary', 'image, edit request, format requirements'),
    4,
    1
  ),
  (
    'gesture',
    'content-repurposer',
    'Content repurposer',
    'Transform content into other formats.',
    'iconoir-refresh-double',
    '/gestos/transformador-contenido.php',
    'Use when the user wants to convert one piece of content into another format for reuse.',
    JSON_OBJECT('summary', 'source content, target format, audience'),
    5,
    1
  ),
  (
    'gesture',
    'sop-generator',
    'SOP generator',
    'Create procedures, checklists, and operating manuals.',
    'iconoir-list-select',
    '/gestos/sop-generator.php',
    'Use when the user wants to create procedures, checklists, operating manuals, or step-by-step SOPs.',
    JSON_OBJECT('summary', 'process description, evidence, desired format'),
    6,
    1
  ),
  (
    'gesture',
    'audio-transcriber',
    'Audio transcriber',
    'Transcribe audio and extract structured notes.',
    'iconoir-microphone',
    '/gestos/transcriptor-audio.php',
    'Use when the user wants to transcribe audio and extract structured notes, summaries, or actions.',
    JSON_OBJECT('summary', 'audio file, summary or extraction goal'),
    7,
    1
  ),
  (
    'gesture',
    'course-creator',
    'Course creator',
    'Design courses, lessons, modules, and learning material.',
    'iconoir-learning',
    '/gestos/creador-cursos.php',
    'Use when the user wants to design training courses, lessons, modules, or learning material.',
    JSON_OBJECT('summary', 'training objective, audience, duration, source material'),
    8,
    1
  ),
  (
    'gesture',
    'project-admin',
    'Project admin',
    'Structure project tasks, timelines, and follow-up.',
    'iconoir-kanban-board',
    '/gestos/admin-proyectos.php',
    'Use when the user wants to structure project tasks, timelines, responsibilities, and follow-up actions.',
    JSON_OBJECT('summary', 'project goal, stakeholders, dates, constraints'),
    9,
    1
  ),
  (
    'gesture',
    'lead-finder',
    'Lead finder',
    'Find and qualify commercial prospects.',
    'iconoir-search-engine',
    '/gestos/lead-finder.php',
    'Use when the user wants to find or qualify commercial prospects from a market, sector, or location.',
    JSON_OBJECT('summary', 'target market, location, sector, qualification criteria'),
    10,
    1
  )
ON DUPLICATE KEY UPDATE
  name = VALUES(name),
  description = VALUES(description),
  icon = VALUES(icon),
  route = VALUES(route),
  trigger_guidance = VALUES(trigger_guidance),
  input_schema = VALUES(input_schema),
  sort_order = VALUES(sort_order),
  is_active = VALUES(is_active);
