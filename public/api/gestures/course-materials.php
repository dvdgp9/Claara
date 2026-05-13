<?php
/**
 * API: Generar materiales complementarios a partir de módulos desarrollados
 * POST /api/gestures/course-materials.php
 * 
 * Genera: flashcards, quiz, final_exam, podcast
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Content\CourseGenerator;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;
use App\DB;

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo se permite POST', 405);
}

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

Session::requireCsrf();

try {
    $body = json_decode(file_get_contents('php://input'), true);
    
    $executionId = $body['execution_id'] ?? null;
    $materialType = $body['material_type'] ?? null; // flashcards, quiz, final_exam, podcast
    $moduleIndices = $body['module_indices'] ?? []; // Para podcast: qué módulos incluir
    $courseTitle = $body['course_title'] ?? 'Curso';
    $modulesContent = $body['modules_content'] ?? null; // Contenido de módulos en texto
    
    // Validar tipo de material
    $validTypes = ['flashcards', 'quiz', 'final_exam', 'podcast'];
    if (!in_array($materialType, $validTypes)) {
        Response::error('invalid_type', 'Tipo de material no válido. Opciones: ' . implode(', ', $validTypes), 400);
    }
    
    // Obtener contenido de módulos
    $content = '';
    
    if ($modulesContent) {
        // Contenido enviado directamente
        $content = $modulesContent;
    } elseif ($executionId) {
        // Obtener de la ejecución guardada
        $repo = new GestureExecutionsRepo();
        $execution = $repo->findById((int)$executionId);
        
        if (!$execution || (int)$execution['user_id'] !== (int)$user['id']) {
            Response::error('not_found', 'Ejecución no encontrada', 404);
        }
        
        $outputData = $execution['output_data'] ?? [];
        $modules = $outputData['modules'] ?? [];
        
        if (empty($modules)) {
            Response::error('no_modules', 'No hay módulos desarrollados en esta ejecución', 400);
        }
        
        // Filtrar módulos si se especifican índices (para podcast)
        if (!empty($moduleIndices) && $materialType === 'podcast') {
            $filteredModules = [];
            foreach ($moduleIndices as $idx) {
                if (isset($modules[$idx])) {
                    $filteredModules[] = $modules[$idx];
                }
            }
            $modules = $filteredModules;
        }
        
        // Concatenar contenido de módulos
        foreach ($modules as $module) {
            $content .= "## " . ($module['title'] ?? 'Módulo') . "\n\n";
            $content .= ($module['content'] ?? '') . "\n\n---\n\n";
        }
        
        $courseTitle = $outputData['course_title'] ?? $courseTitle;
    }
    
    if (empty(trim($content))) {
        Response::error('no_content', 'No hay contenido para generar el material', 400);
    }
    
    // === GENERAR MATERIAL ===
    $generator = new CourseGenerator();
    
    // Configuración por defecto
    $config = [
        'duration' => '8h',
        'level' => 'intermedio',
        'course_format' => 'online'
    ];
    
    $result = $generator->generate($content, $materialType, $courseTitle, $config);
    
    if (!$result['success']) {
        Response::error('generation_failed', $result['error'] ?? 'Error generando material', 500);
    }
    
    // Parsear output
    $parsed = $generator->parseOutput($result['output'], $materialType);
    
    // === GUARDAR EN HISTORIAL ===
    $repo = new GestureExecutionsRepo();
    $newExecutionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'course-creator',
        'title' => $courseTitle . ' - ' . ucfirst(str_replace('_', ' ', $materialType)),
        'input_data' => [
            'phase' => 3,
            'material_type' => $materialType,
            'source_execution_id' => $executionId,
            'module_indices' => $moduleIndices
        ],
        'output_content' => $result['output'],
        'output_data' => [
            'phase' => 3,
            'material_type' => $materialType,
            'course_title' => $courseTitle,
            'raw' => $result['output'],
            'parsed' => $parsed
        ],
        'content_type' => 'course_material_' . $materialType,
        'model' => $result['model'] ?? 'unknown'
    ]);
    
    // Registrar uso
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', 1, [
        'gesture_type' => 'course-creator',
        'phase' => 3,
        'material_type' => $materialType
    ]);
    
    // === RESPUESTA ===
    Response::json([
        'success' => true,
        'phase' => 3,
        'execution_id' => $newExecutionId,
        'material_type' => $materialType,
        'course_title' => $courseTitle,
        'output' => $result['output'],
        'parsed' => $parsed,
        'model' => $result['model']
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
