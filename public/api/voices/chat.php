<?php
/**
 * API: Chat con una voz especializada
 * POST /api/voices/chat.php
 * Body JSON: { voice_id, message, history?, execution_id? }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../../src/Chat/LlmProvider.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterProvider.php';
require_once __DIR__ . '/../../../src/Chat/LlmProviderFactory.php';
require_once __DIR__ . '/../../../src/Voices/VoiceExecutionsRepo.php';
require_once __DIR__ . '/../../../src/Voices/VoiceContextBuilder.php';
require_once __DIR__ . '/../../../src/Voices/VoiceQueryService.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../src/Rag/EmbeddingService.php';
require_once __DIR__ . '/../../../src/Rag/LexRetriever.php';

use App\Session;
use App\Response;
use I18n\I18n;
use Voices\VoiceExecutionsRepo;
use Voices\VoiceQueryService;
use Repos\UsageLogRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', I18n::translate('auth.error.unauthorized'), 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', I18n::translate('auth.error.csrf_invalid'), 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', I18n::translate('auth.error.method_not_allowed'), 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$voiceId = $body['voice_id'] ?? '';
$message = trim($body['message'] ?? '');
$history = $body['history'] ?? [];
$executionId = $body['execution_id'] ?? null;

if (!$voiceId) {
    Response::error('missing_voice', I18n::translate('voice_api.voice_required'), 400);
}
if (!$message) {
    Response::error('missing_message', I18n::translate('voice_api.message_required'), 400);
}

try {
    $result = (new VoiceQueryService())->query($user, $voiceId, $message, is_array($history) ? $history : []);
} catch (\InvalidArgumentException $e) {
    Response::error('validation_error', I18n::translate('voice_api.invalid_request'), 400);
} catch (\RuntimeException $e) {
    $status = in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 500;
    $message = $status === 403
        ? I18n::translate('voice_api.forbidden')
        : ($status === 404 ? I18n::translate('voice_api.voice_not_found') : I18n::translate('voice_api.query_error'));
    Response::error($status === 403 ? 'forbidden' : 'voice_query_error', $message, $status);
} catch (\Throwable $e) {
    error_log('Voice query failed: ' . $e->getMessage());
    Response::error('llm_error', I18n::translate('voice_api.query_error'), 500);
}
$answer = $result['answer'];
$meta = $result['meta'];

// Guardar o actualizar ejecución
$repo = new VoiceExecutionsRepo();

// Generar título si es nueva conversación
$title = $message;
if (strlen($title) > 60) {
    $title = substr($title, 0, 57) . '...';
}

// Preparar historial completo para guardar
$fullHistory = $history;
$fullHistory[] = ['role' => 'user', 'content' => $message];
$fullHistory[] = ['role' => 'assistant', 'content' => $answer, 'meta' => $meta];

$inputData = [
    'history' => $fullHistory
];

// Registrar uso de voz (siempre, independientemente de si es nueva o existente)
$usageLog = new UsageLogRepo();
$usageLog->log((int)$user['id'], 'voice', 1, ['voice_id' => $voiceId]);

if ($executionId) {
    // Actualizar ejecución existente
    $repo->update($executionId, (int)$user['id'], [
        'input_data' => $inputData,
        'output_content' => $answer
    ]);
} else {
    // Crear nueva ejecución
    $executionId = $repo->create([
        'user_id' => (int)$user['id'],
        'voice_id' => $voiceId,
        'title' => $title,
        'input_data' => $inputData,
        'output_content' => $answer,
        'model' => $result['model']
    ]);
}

Response::json([
    'success' => true,
    'reply' => $answer,
    'meta' => $meta,
    'execution_id' => $executionId
]);
