<?php
/**
 * API: Transformador de Contenido (Content Repurposer)
 * POST /api/gestures/repurposer.php
 * 
 * Transforma contenido de una fuente (URL, texto, PDF) en diferentes formatos:
 * - Posts para redes sociales (Instagram, Facebook, LinkedIn, X)
 * - Entrada de blog
 * - Landing page (HTML/CSS/JS)
 * - Newsletter
 * - FAQs
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Audio\ContentExtractor;
use Content\ContentRepurposer;
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
$sourceUrl = $body['url'] ?? '';
$sourceText = $body['text'] ?? '';
$sourcePdf = $body['pdf_base64'] ?? '';
$outputFormat = $body['output_format'] ?? 'instagram';
$options = $body['options'] ?? [];

// Validaciones de entrada
if ($sourceType === 'url' && empty($sourceUrl)) {
    Response::error('missing_url', 'Se requiere una URL', 400);
}
if ($sourceType === 'text' && empty($sourceText)) {
    Response::error('missing_text', 'Se requiere el texto', 400);
}
if ($sourceType === 'pdf' && empty($sourcePdf)) {
    Response::error('missing_pdf', 'Se requiere el PDF en base64', 400);
}

// Validar formato de salida
$validFormats = array_keys(ContentRepurposer::getOutputFormats());
if (!in_array($outputFormat, $validFormats)) {
    Response::error('invalid_format', 'Formato no válido: ' . $outputFormat, 400);
}

try {
    // === PASO 1: Extraer contenido ===
    $extractor = new ContentExtractor();
    $content = null;
    $title = '';
    $source = '';

    switch ($sourceType) {
        case 'url':
            $result = $extractor->extractFromUrl($sourceUrl);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = $result['source'];
            break;

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

    // === PASO 2: Generar contenido transformado ===
    $repurposer = new ContentRepurposer();
    $generateResult = $repurposer->generate($content, $outputFormat, $title, $options);

    if (!$generateResult['success']) {
        Response::error('generation_failed', $generateResult['error'], 500);
    }

    $output = $generateResult['output'];
    $formatName = $generateResult['format_name'];
    $model = $generateResult['model'];

    // === PASO 3: Guardar en historial ===
    $repo = new GestureExecutionsRepo();
    
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'content-repurposer',
        'title' => $title ?: 'Transformación: ' . $formatName,
        'input_data' => [
            'source_type' => $sourceType,
            'source' => $source,
            'url' => $sourceUrl,
            'output_format' => $outputFormat,
            'word_count' => str_word_count($content)
        ],
        'output_content' => $output,
        'output_data' => [
            'format' => $outputFormat,
            'format_name' => $formatName,
            'original_title' => $title,
            'options' => $options
        ],
        'content_type' => 'transformed',
        'business_line' => $options['business_line'] ?? null,
        'model' => $model
    ]);

    // Registrar en estadísticas
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', 1, ['gesture_type' => 'content-repurposer', 'format' => $outputFormat]);

    // === Respuesta ===
    Response::json([
        'success' => true,
        'execution_id' => $executionId,
        'title' => $title,
        'output' => $output,
        'format' => $outputFormat,
        'format_name' => $formatName,
        'source' => $source,
        'model' => $model
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
