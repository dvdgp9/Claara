<?php
require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;

$pdo = DB::pdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$database = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();

$hasColumn = function (string $column) use ($pdo, $database): bool {
    $stmt = $pdo->prepare('
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = "available_features"
          AND COLUMN_NAME = ?
    ');
    $stmt->execute([$database, $column]);

    return (int)$stmt->fetchColumn() > 0;
};

$columns = [
    'route' => 'ALTER TABLE available_features ADD COLUMN route VARCHAR(255) NULL AFTER icon',
    'trigger_guidance' => 'ALTER TABLE available_features ADD COLUMN trigger_guidance TEXT NULL AFTER route',
    'input_schema' => 'ALTER TABLE available_features ADD COLUMN input_schema JSON NULL AFTER trigger_guidance',
];

foreach ($columns as $column => $sql) {
    if ($hasColumn($column)) {
        echo "exists {$column}\n";
        continue;
    }

    $pdo->exec($sql);
    echo "added {$column}\n";
}

$gestures = [
    ['write-article', 'Write article', 'Generate articles, blogs, and press notes.', 'iconoir-page-edit', '/gestos/escribir-articulo.php', 'Use when the user wants to draft or refine a long-form article from a topic, brief, source notes, or reference material.', 'topic, audience, tone, source notes', 1],
    ['social-media', 'Social media', 'Create posts for social channels.', 'iconoir-send-diagonal', '/gestos/redes-sociales.php', 'Use when the user wants to turn a campaign idea, article, announcement, or brief into social media posts.', 'source content, channel, tone, number of posts', 2],
    ['podcast-from-article', 'Podcast from article', 'Turn articles into AI-generated podcasts.', 'iconoir-podcast', '/gestos/podcast-articulo.php', 'Use when the user wants to transform an article, report, or URL into a podcast script or audio workflow.', 'article text or URL, podcast style, duration', 3],
    ['image-editor', 'Image editor', 'Edit, adapt, or generate visual assets.', 'iconoir-media-image', '/gestos/editor-imagenes.php', 'Use when the user wants to edit, adapt, or generate instructions for an image or visual asset.', 'image, edit request, format requirements', 4],
    ['content-repurposer', 'Content repurposer', 'Transform content into other formats.', 'iconoir-refresh-double', '/gestos/transformador-contenido.php', 'Use when the user wants to convert one piece of content into another format for reuse.', 'source content, target format, audience', 5],
    ['sop-generator', 'SOP generator', 'Create procedures, checklists, and operating manuals.', 'iconoir-list-select', '/gestos/sop-generator.php', 'Use when the user wants to create procedures, checklists, operating manuals, or step-by-step SOPs.', 'process description, evidence, desired format', 6],
    ['audio-transcriber', 'Audio transcriber', 'Transcribe audio and extract structured notes.', 'iconoir-microphone', '/gestos/transcriptor-audio.php', 'Use when the user wants to transcribe audio and extract structured notes, summaries, or actions.', 'audio file, summary or extraction goal', 7],
    ['course-creator', 'Course creator', 'Design courses, lessons, modules, and learning material.', 'iconoir-learning', '/gestos/creador-cursos.php', 'Use when the user wants to design training courses, lessons, modules, or learning material.', 'training objective, audience, duration, source material', 8],
    ['project-admin', 'Project admin', 'Structure project tasks, timelines, and follow-up.', 'iconoir-kanban-board', '/gestos/admin-proyectos.php', 'Use when the user wants to structure project tasks, timelines, responsibilities, and follow-up actions.', 'project goal, stakeholders, dates, constraints', 9],
    ['lead-finder', 'Lead finder', 'Find and qualify commercial prospects.', 'iconoir-search-engine', '/gestos/lead-finder.php', 'Use when the user wants to find or qualify commercial prospects from a market, sector, or location.', 'target market, location, sector, qualification criteria', 10],
];

$stmt = $pdo->prepare('
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
    ) VALUES (
        "gesture", ?, ?, ?, ?, ?, ?, ?, ?, 1
    )
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        icon = VALUES(icon),
        route = VALUES(route),
        trigger_guidance = VALUES(trigger_guidance),
        input_schema = VALUES(input_schema),
        sort_order = VALUES(sort_order),
        is_active = VALUES(is_active)
');

foreach ($gestures as $gesture) {
    $stmt->execute([
        $gesture[0],
        $gesture[1],
        $gesture[2],
        $gesture[3],
        $gesture[4],
        $gesture[5],
        json_encode(['summary' => $gesture[6]], JSON_UNESCAPED_UNICODE),
        $gesture[7],
    ]);
}

$pdo->exec('
    CREATE TABLE IF NOT EXISTS schema_migrations (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        filename VARCHAR(255) NOT NULL,
        executed_at DATETIME NOT NULL,
        UNIQUE KEY schema_migrations_filename_uq (filename)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
');

$mark = $pdo->prepare('INSERT IGNORE INTO schema_migrations (filename, executed_at) VALUES (?, NOW())');
$mark->execute(['020_available_feature_capability_metadata.sql']);

echo 'seeded gestures=' . count($gestures) . "\n";
