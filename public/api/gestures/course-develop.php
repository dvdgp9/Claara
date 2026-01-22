<?php
/**
 * API: Creador de Cursos - Fase 2: Desarrollar Módulos
 * POST /api/gestures/course-develop.php
 * 
 * Fase 2 del flujo de creación de cursos:
 * - Recibe el índice (editado o no) y el execution_id de la fase 1
 * - Desarrolla el contenido completo de cada módulo secuencialmente
 * - Genera Markdown y HTML para cada módulo
 * - Permite descarga en Word/PDF
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Content\CourseGenerator;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;
use Utils\DocumentGenerator;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'No autenticado', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$executionId = $body['execution_id'] ?? null;
$outline = $body['outline'] ?? null;
$sourceContent = $body['source_content'] ?? null;

// Validaciones
if (!$executionId && !$outline) {
    Response::error('missing_data', 'Se requiere execution_id o outline', 400);
}

if (!$outline || !isset($outline['modules'])) {
    Response::error('invalid_outline', 'El índice no tiene una estructura válida', 400);
}

try {
    $repo = new GestureExecutionsRepo();
    
    // Si tenemos execution_id, recuperar el contenido original de la fase 1
    if ($executionId && !$sourceContent) {
        $execution = $repo->findById((int)$executionId);
        
        // Verificar que pertenece al usuario
        if ($execution && (int)$execution['user_id'] !== (int)$user['id']) {
            Response::error('forbidden', 'No tienes acceso a esta ejecución', 403);
        }
        
        if (!$execution) {
            Response::error('not_found', 'No se encontró la ejecución de la fase 1', 404);
        }
        
        // El contenido original está guardado en output_content
        $sourceContent = $execution['output_content'] ?? '';
        
        if (empty($sourceContent)) {
            Response::error('missing_content', 'No se encontró el contenido original', 400);
        }
    }

    if (empty($sourceContent)) {
        Response::error('missing_content', 'Se requiere el contenido fuente', 400);
    }

    // === DESARROLLAR MÓDULOS ===
    $generator = new CourseGenerator();
    
    $developResult = $generator->developModules($sourceContent, $outline);

    if (!$developResult['success']) {
        $errorMsg = implode(', ', $developResult['errors'] ?? ['Error desconocido']);
        Response::error('development_failed', $errorMsg, 500);
    }

    $modules = $developResult['modules'];
    $courseTitle = $developResult['course_title'] ?? 'Curso';
    $model = $developResult['model'] ?? 'unknown';

    // === GUARDAR EN HISTORIAL (Fase 2) ===
    $newExecutionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'course-creator',
        'title' => $courseTitle . ' (Desarrollado)',
        'input_data' => [
            'phase' => 2,
            'original_execution_id' => $executionId,
            'outline' => $outline,
            'modules_count' => count($modules)
        ],
        'output_content' => json_encode($modules, JSON_UNESCAPED_UNICODE),
        'output_data' => [
            'phase' => 2,
            'course_title' => $courseTitle,
            'modules' => $modules,
            'outline' => $outline,
            'total_developed' => $developResult['total_developed'],
            'total_failed' => $developResult['total_failed'],
            'errors' => $developResult['errors']
        ],
        'content_type' => 'course_developed',
        'business_line' => $body['business_line'] ?? null,
        'model' => $model
    ]);

    // Si había una ejecución de fase 1, actualizarla para vincularla
    if ($executionId) {
        // Por ahora, solo guardamos el vínculo en output_data de la nueva ejecución
        // El vínculo inverso ya está en input_data.original_execution_id
    }

    // Registrar en estadísticas
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', count($modules), [
        'gesture_type' => 'course-creator', 
        'phase' => 2,
        'modules_developed' => count($modules)
    ]);

    // === Respuesta ===
    Response::json([
        'success' => true,
        'phase' => 2,
        'execution_id' => $newExecutionId,
        'course_title' => $courseTitle,
        'modules' => $modules,
        'total_developed' => $developResult['total_developed'],
        'total_failed' => $developResult['total_failed'],
        'errors' => $developResult['errors'],
        'model' => $model
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
