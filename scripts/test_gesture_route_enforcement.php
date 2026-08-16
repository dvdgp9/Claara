<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function gestureRouteCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

function sourceContains(string $root, string $file, string $needle): bool
{
    $source = file_get_contents($root . '/' . $file);
    return is_string($source) && str_contains($source, $needle);
}

$guardPath = $root . '/src/Gestures/GestureAccessGuard.php';
if (!is_file($guardPath)) {
    gestureRouteCheck(false, 'central Gesture access guard exists');
    echo "RESULT {$passed} passed, {$failed} failed\n";
    exit(1);
}

require_once $root . '/src/Modules/ModuleConfigurationException.php';
require_once $root . '/src/Modules/ModuleDefinition.php';
require_once $root . '/src/Modules/ModuleRegistry.php';
require_once $guardPath;

use Gestures\GestureAccessGuard;

$expectedGestures = [
    'audio-transcriber',
    'content-repurposer',
    'course-creator',
    'image-editor',
    'lead-finder',
    'podcast-from-article',
    'project-admin',
    'social-media',
    'sop-generator',
    'write-article',
];
gestureRouteCheck(GestureAccessGuard::supportedGestures() === $expectedGestures, 'guard allow-lists every registered Gesture');
gestureRouteCheck(GestureAccessGuard::gestureForJobType('podcast') === 'podcast-from-article', 'Podcast job maps to its Gesture');
gestureRouteCheck(GestureAccessGuard::gestureForJobType('audio-transcribe') === 'audio-transcriber', 'Audio job maps to its Gesture');
gestureRouteCheck(GestureAccessGuard::gestureForJobType('lead-finder') === 'lead-finder', 'Lead job maps to its Gesture');
gestureRouteCheck(GestureAccessGuard::gestureForJobType('unknown') === null, 'unknown job type fails closed');

$fixedApiRoutes = [
    'public/api/gestures/admin-proyectos.php' => 'project-admin',
    'public/api/gestures/course-creator.php' => 'course-creator',
    'public/api/gestures/course-develop.php' => 'course-creator',
    'public/api/gestures/course-export.php' => 'course-creator',
    'public/api/gestures/course-materials.php' => 'course-creator',
    'public/api/gestures/generate-image.php' => 'image-editor',
    'public/api/gestures/podcast.php' => 'podcast-from-article',
    'public/api/gestures/repurposer.php' => 'content-repurposer',
    'public/api/gestures/sop.php' => 'sop-generator',
    'public/api/gestures/transcribe.php' => 'audio-transcriber',
    'public/api/gestures/lead-finder/delete.php' => 'lead-finder',
    'public/api/gestures/lead-finder/export.php' => 'lead-finder',
    'public/api/gestures/lead-finder/get.php' => 'lead-finder',
    'public/api/gestures/lead-finder/history.php' => 'lead-finder',
    'public/api/gestures/lead-finder/search.php' => 'lead-finder',
    'public/api/gestures/lead-finder/update-result.php' => 'lead-finder',
    'public/api/files/podcast.php' => 'podcast-from-article',
];
foreach ($fixedApiRoutes as $file => $gesture) {
    gestureRouteCheck(sourceContains($root, $file, "requireApi(\$user, '{$gesture}')"), "fixed API guard: {$file}");
}

$dynamicRouteChecks = [
    'public/api/gestures/generate.php' => 'requireDynamicApi($user, $gestureType',
    'public/api/gestures/history.php' => 'requireDynamicApi($user, $gestureType',
    'public/api/gestures/recent.php' => 'requireDynamicApi($user, $gestureType',
    'public/api/gestures/delete.php' => 'requireExecutionApi($user, $execution)',
    'public/api/gestures/get.php' => 'requireExecutionApi($user, $execution)',
    'public/api/gestures/update-title.php' => 'requireExecutionApi($user, $execution)',
];
foreach ($dynamicRouteChecks as $file => $needle) {
    gestureRouteCheck(sourceContains($root, $file, $needle), "dynamic API guard: {$file}");
}

$gesturePages = [
    'public/gestos/admin-proyectos.php' => 'project-admin',
    'public/gestos/creador-cursos.php' => 'course-creator',
    'public/gestos/editor-imagenes.php' => 'image-editor',
    'public/gestos/editor-imagenes-old.php' => 'image-editor',
    'public/gestos/escribir-articulo.php' => 'write-article',
    'public/gestos/lead-finder.php' => 'lead-finder',
    'public/gestos/podcast-articulo.php' => 'podcast-from-article',
    'public/gestos/redes-sociales.php' => 'social-media',
    'public/gestos/sop-generator.php' => 'sop-generator',
    'public/gestos/transcriptor-audio.php' => 'audio-transcriber',
    'public/gestos/transformador-contenido.php' => 'content-repurposer',
];
foreach ($gesturePages as $file => $gesture) {
    gestureRouteCheck(sourceContains($root, $file, "hasGestureAccess((int)\$user['id'], '{$gesture}')"), "page guard: {$file}");
}

gestureRouteCheck(sourceContains($root, 'src/Jobs/BackgroundJobsRepo.php', 'captureJobRequirement('), 'every queued job captures its required Gesture');
gestureRouteCheck(sourceContains($root, 'public/api/jobs/process.php', 'requireJobWorker($job)'), 'worker re-checks Gesture access before processing');
foreach (['active.php', 'cancel.php', 'status.php'] as $file) {
    gestureRouteCheck(sourceContains($root, 'public/api/jobs/' . $file, 'GestureAccessGuard'), "job endpoint is Gesture-aware: {$file}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
