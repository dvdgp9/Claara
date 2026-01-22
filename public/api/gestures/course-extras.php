<?php
/**
 * API: Generar materiales complementarios de un curso
 * POST /api/gestures/course-extras.php
 * 
 * Genera flashcards, tests, examen final o podcast a partir del contenido desarrollado
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Content\CourseGenerator;
use Gestures\GestureExecutionsRepo;
use App\UsageLogRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

try {
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        Response::error('invalid_request', 'Datos no válidos', 400);
    }

    $executionId = $body['execution_id'] ?? null;
    $formats = $body['formats'] ?? [];
    $modules = $body['modules'] ?? [];
    $courseTitle = $body['course_title'] ?? 'Curso';

    if (empty($formats)) {
        Response::error('missing_formats', 'Debes seleccionar al menos un material a generar', 400);
    }

    if (empty($modules)) {
        Response::error('missing_modules', 'No hay módulos para generar materiales', 400);
    }

    // Construir contenido consolidado de todos los módulos
    $consolidatedContent = "";
    foreach ($modules as $module) {
        $consolidatedContent .= "## " . ($module['title'] ?? 'Módulo') . "\n\n";
        $consolidatedContent .= ($module['content'] ?? '') . "\n\n---\n\n";
    }

    if (strlen($consolidatedContent) < 100) {
        Response::error('content_too_short', 'El contenido es demasiado corto para generar materiales', 400);
    }

    // Generar materiales
    $generator = new CourseGenerator();
    $results = [];
    $errors = [];

    $formatNames = [
        'flashcards' => 'Flashcards',
        'quiz' => 'Tests por módulo',
        'final_exam' => 'Examen final',
        'podcast' => 'Guion de podcast'
    ];

    foreach ($formats as $format) {
        $result = $generator->generate($consolidatedContent, $format, $courseTitle, [
            'duration' => '8h',
            'level' => 'intermedio'
        ]);

        if ($result['success']) {
            $results[$format] = [
                'format' => $format,
                'format_name' => $formatNames[$format] ?? $format,
                'content' => $result['output'],
                'html' => nl2br(htmlspecialchars($result['output'])),
                'model' => $result['model']
            ];
        } else {
            $errors[$format] = $result['error'] ?? 'Error desconocido';
        }
    }

    if (empty($results)) {
        Response::error('generation_failed', 'No se pudo generar ningún material: ' . implode(', ', $errors), 500);
    }

    // Guardar en historial
    $repo = new GestureExecutionsRepo();
    $newExecutionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'course-creator',
        'title' => $courseTitle . ' (Materiales)',
        'input_data' => [
            'phase' => 3,
            'original_execution_id' => $executionId,
            'formats_requested' => $formats,
            'modules_count' => count($modules)
        ],
        'output_content' => json_encode($results, JSON_UNESCAPED_UNICODE),
        'output_data' => [
            'phase' => 3,
            'course_title' => $courseTitle,
            'materials' => $results,
            'total_generated' => count($results),
            'total_failed' => count($errors),
            'errors' => $errors
        ],
        'content_type' => 'course_materials',
        'business_line' => $body['business_line'] ?? null,
        'model' => $results[array_key_first($results)]['model'] ?? 'unknown'
    ]);

    // Log de uso
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', count($results), [
        'gesture_type' => 'course-creator',
        'phase' => 3,
        'materials_generated' => array_keys($results)
    ]);

    Response::json([
        'success' => true,
        'phase' => 3,
        'execution_id' => $newExecutionId,
        'course_title' => $courseTitle,
        'materials' => $results,
        'total_generated' => count($results),
        'total_failed' => count($errors),
        'errors' => $errors
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
