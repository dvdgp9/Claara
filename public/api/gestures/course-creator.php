<?php
/**
 * API: Creador de Cursos - Fase 1: Generar Índice
 * POST /api/gestures/course-creator.php
 * 
 * Fase 1 del flujo de creación de cursos:
 * - Extrae contenido del PDF/texto
 * - Genera un índice pedagógico editable (JSON)
 * - El usuario puede editar el índice antes de la Fase 2
 * 
 * Para la Fase 2 (desarrollar módulos), ver course-develop.php
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Audio\ContentExtractor;
use Content\CourseGenerator;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'No autenticado', 401);
}

Session::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$sourceType = $body['source_type'] ?? 'text';
$sourceText = $body['text'] ?? '';
$sourcePdf = $body['pdf_base64'] ?? '';

// Configuración del curso
$config = [
    'duration' => $body['duration'] ?? '8h',
    'level' => $body['level'] ?? 'intermedio',
    'course_format' => $body['course_format'] ?? 'online'
];

// Validaciones de entrada
if ($sourceType === 'text' && empty($sourceText)) {
    Response::error('missing_text', 'Se requiere el texto', 400);
}
if ($sourceType === 'pdf' && empty($sourcePdf)) {
    Response::error('missing_pdf', 'Se requiere el PDF en base64', 400);
}

// Validar configuración
$validDurations = ['4h', '8h', '16h', '40h'];
if (!in_array($config['duration'], $validDurations)) {
    $config['duration'] = '8h';
}

$validLevels = ['basico', 'intermedio', 'avanzado'];
if (!in_array($config['level'], $validLevels)) {
    $config['level'] = 'intermedio';
}

$validCourseFormats = ['presencial', 'online', 'hibrido'];
if (!in_array($config['course_format'], $validCourseFormats)) {
    $config['course_format'] = 'online';
}

try {
    // === PASO 1: Extraer contenido ===
    $extractor = new ContentExtractor();
    $content = null;
    $title = '';
    $source = '';

    switch ($sourceType) {
        case 'pdf':
            $result = $extractor->extractFromPdf($sourcePdf);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'PDF';
            break;

        case 'text':
        default:
            $result = $extractor->extractFromText($sourceText);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'Texto';
            break;
    }

    // Validar longitud mínima para un curso
    $wordCount = str_word_count($content);
    if ($wordCount < 100) {
        Response::error('content_too_short', 'El contenido es demasiado corto para generar un curso (mínimo 100 palabras, tienes ' . $wordCount . ')', 400);
    }

    // === PASO 2: Generar índice del curso ===
    $generator = new CourseGenerator();
    $outlineResult = $generator->generateOutline($content, $title, $config);

    if (!$outlineResult['success']) {
        Response::error('generation_failed', $outlineResult['error'] ?? 'Error generando índice', 500);
    }

    $outline = $outlineResult['outline'];
    $model = $outlineResult['model'] ?? 'unknown';

    // === PASO 3: Guardar en historial (fase 1) ===
    $repo = new GestureExecutionsRepo();
    
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'course-creator',
        'title' => $outline['course_title'] ?? $title ?: 'Curso generado',
        'input_data' => [
            'source_type' => $sourceType,
            'source' => $source,
            'word_count' => $wordCount,
            'config' => $config,
            'phase' => 1
        ],
        'output_content' => $content, // Guardamos el contenido extraído para la fase 2
        'output_data' => [
            'phase' => 1,
            'outline' => $outline,
            'raw' => $outlineResult['raw'] ?? '',
            'original_title' => $title,
            'config' => $config,
            'parse_error' => $outlineResult['parse_error'] ?? null
        ],
        'content_type' => 'course_outline',
        'business_line' => $body['business_line'] ?? null,
        'model' => $model
    ]);

    // Registrar en estadísticas
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', 1, [
        'gesture_type' => 'course-creator', 
        'phase' => 1,
        'config' => $config
    ]);

    // === Respuesta ===
    Response::json([
        'success' => true,
        'phase' => 1,
        'execution_id' => $executionId,
        'title' => $outline['course_title'] ?? $title,
        'outline' => $outline,
        'raw' => $outlineResult['raw'] ?? '',
        'source' => $source,
        'word_count' => $wordCount,
        'config' => $config,
        'model' => $model,
        'parse_error' => $outlineResult['parse_error'] ?? null,
        'next_step' => 'Edita el índice si lo deseas y luego pulsa "Desarrollar módulos"'
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
