<?php
/**
 * API: Generador de SOPs (Standard Operating Procedures)
 * POST /api/gestures/sop.php
 * 
 * Body: {
 *   title?: string,
 *   text?: string,
 *   url?: string,
 *   pdf_base64?: string,
 *   audio_base64?: string,
 *   audio_mime?: string,
 *   audio_filename?: string,
 *   images?: [{ base64: string, mime_type: string }]
 * }
 * 
 * Response: {
 *   success: true,
 *   title: string,
 *   formats: {
 *     markdown: string,
 *     mermaid: string|null,
 *     pdf: { url: string, filename: string }|null,
 *     docx: { url: string, filename: string }|null
 *   },
 *   sources: string[],
 *   warnings: string[]
 * }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Sop\SopGenerator;
use Gestures\GestureExecutionsRepo;
use I18n\I18n;
use Repos\UsageLogRepo;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

// Verificar acceso al gesto y a su módulo de instancia.
$gestureAccess = new \Gestures\GestureAccessGuard();
$gestureAccess->requireApi($user, 'sop-generator');

Session::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

// Parsear body
$body = json_decode(file_get_contents('php://input'), true) ?? [];

// Validar que hay al menos una fuente de contenido
$hasContent = !empty($body['text']) 
    || !empty($body['url']) 
    || !empty($body['pdf_base64'])
    || !empty($body['audio_base64'])
    || (!empty($body['images']) && is_array($body['images']) && count($body['images']) > 0);

if (!$hasContent) {
    Response::error('missing_content', I18n::translate('sop_ui.source_required'), 400);
}

// Configurar tiempo límite alto para procesamiento de audio/múltiples fuentes
set_time_limit(300);

try {
    $generator = new SopGenerator();
    
    $result = $generator->generate([
        'title' => $body['title'] ?? '',
        'text' => $body['text'] ?? '',
        'url' => $body['url'] ?? '',
        'pdf_base64' => $body['pdf_base64'] ?? '',
        'audio_base64' => $body['audio_base64'] ?? '',
        'audio_mime' => $body['audio_mime'] ?? '',
        'audio_filename' => $body['audio_filename'] ?? 'audio',
        'images' => $body['images'] ?? [],
    ]);
    
    if (!$result['success']) {
        Response::error('generation_failed', $result['error'], 500);
    }
    
    // Guardar en historial de gestos
    $gesturesRepo = new GestureExecutionsRepo();
    $executionId = $gesturesRepo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'sop-generator',
        'title' => $result['title'],
        'input_data' => [
            'sources' => $result['sources'],
            'has_text' => !empty($body['text']),
            'has_url' => !empty($body['url']),
            'has_pdf' => !empty($body['pdf_base64']),
            'has_audio' => !empty($body['audio_base64']),
            'has_images' => !empty($body['images']),
            'image_count' => count($body['images'] ?? []),
        ],
        'output_content' => $result['formats']['markdown'],
        'output_data' => [
            'markdown' => $result['formats']['markdown'],
            'mermaid' => $result['formats']['mermaid'],
            'pdf' => $result['formats']['pdf'],
            'docx' => $result['formats']['docx'],
        ],
        'content_type' => 'sop',
        'business_line' => null,
        'model' => 'google/gemini-3-flash-preview'
    ]);
    
    // Registrar uso
    $usageLog = new UsageLogRepo();
    $usageLog->log($user['id'], 'gesture', 1, ['gesture_type' => 'sop-generator']);
    
    Response::json([
        'success' => true,
        'execution_id' => $executionId,
        'title' => $result['title'],
        'formats' => $result['formats'],
        'sources' => $result['sources'],
        'warnings' => $result['warnings'] ?? []
    ]);
    
} catch (\Exception $e) {
    Response::serverError('server_error', $e, I18n::translate('sop_ui.generation_error', ['message' => I18n::translate('sop_ui.unknown_error')]));
}
