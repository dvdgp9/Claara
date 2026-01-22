<?php
/**
 * API: Creador de Cursos
 * POST /api/gestures/course-creator.php
 * 
 * Genera material de curso a partir de contenido fuente (PDF, texto):
 * - Temario estructurado
 * - Fichas de contenido
 * - Preguntas de autoevaluación
 * - Microlearning / Flashcards
 * - Podcast educativo
 * - Examen final
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

// Formatos a generar
$outputFormats = $body['output_formats'] ?? [];
if (empty($outputFormats)) {
    $outputFormats = ['syllabus'];
}

// Validaciones de entrada
if ($sourceType === 'text' && empty($sourceText)) {
    Response::error('missing_text', 'Se requiere el texto', 400);
}
if ($sourceType === 'pdf' && empty($sourcePdf)) {
    Response::error('missing_pdf', 'Se requiere el PDF en base64', 400);
}

// Validar formatos de salida
$validFormats = array_keys(CourseGenerator::getOutputFormats());
$invalidFormats = array_diff($outputFormats, $validFormats);
if (!empty($invalidFormats)) {
    Response::error('invalid_format', 'Formatos no válidos: ' . implode(', ', $invalidFormats), 400);
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

    // === PASO 2: Generar material del curso ===
    $generator = new CourseGenerator();
    $generateResult = $generator->generateMultiple($content, $outputFormats, $title, $config);

    if (!$generateResult['success']) {
        $errorMsg = implode(', ', $generateResult['errors']);
        Response::error('generation_failed', $errorMsg, 500);
    }

    $results = $generateResult['results'];
    $firstFormat = array_key_first($results);
    $model = $results[$firstFormat]['model'] ?? 'unknown';

    // === PASO 3: Guardar en historial ===
    $repo = new GestureExecutionsRepo();
    
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'course-creator',
        'title' => $title ?: 'Curso generado',
        'input_data' => [
            'source_type' => $sourceType,
            'source' => $source,
            'output_formats' => $outputFormats,
            'word_count' => $wordCount,
            'config' => $config
        ],
        'output_content' => json_encode($results, JSON_UNESCAPED_UNICODE),
        'output_data' => [
            'formats' => $outputFormats,
            'results' => $results,
            'original_title' => $title,
            'config' => $config,
            'total_generated' => $generateResult['total_generated'],
            'total_failed' => $generateResult['total_failed']
        ],
        'content_type' => 'course_material',
        'business_line' => $body['business_line'] ?? null,
        'model' => $model
    ]);

    // Registrar en estadísticas
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', count($outputFormats), [
        'gesture_type' => 'course-creator', 
        'formats' => $outputFormats,
        'config' => $config
    ]);

    // === Respuesta ===
    Response::json([
        'success' => true,
        'execution_id' => $executionId,
        'title' => $title,
        'results' => $results,
        'formats' => $outputFormats,
        'source' => $source,
        'config' => $config,
        'model' => $model,
        'total_generated' => $generateResult['total_generated'],
        'total_failed' => $generateResult['total_failed'],
        'errors' => $generateResult['errors']
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
