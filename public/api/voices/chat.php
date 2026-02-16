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
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';
require_once __DIR__ . '/../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../src/Rag/EmbeddingService.php';
require_once __DIR__ . '/../../../src/Rag/LexRetriever.php';

use App\Session;
use App\Response;
use App\Env;
use Chat\OpenRouterClient;
use Voices\VoiceExecutionsRepo;
use Voices\VoiceContextBuilder;
use Repos\UsageLogRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$voiceId = $body['voice_id'] ?? '';
$message = trim($body['message'] ?? '');
$history = $body['history'] ?? [];
$executionId = $body['execution_id'] ?? null;

if (!$voiceId) {
    Response::error('missing_voice', 'Se requiere voice_id', 400);
}
if (!$message) {
    Response::error('missing_message', 'Se requiere message', 400);
}

// Obtener contexto especializado de la voz
$voiceContext = new VoiceContextBuilder($voiceId);

if (!$voiceContext->voiceExists()) {
    Response::error('invalid_voice', 'Voz no encontrada', 404);
}

// Intentar usar RAG si está habilitado para esta voz
$useRag = false;
if ($voiceContext->hasRagEnabled()) {
    $openrouterKey = Env::get('OPENROUTER_API_KEY');
    $qdrantHost = Env::get('QDRANT_HOST', 'localhost');
    $qdrantPort = (int) Env::get('QDRANT_PORT', 6333);
    
    if ($openrouterKey) {
        $voiceContext->initRetriever($openrouterKey, $qdrantHost, $qdrantPort);
        $useRag = $voiceContext->isRagReady();
    }
}

// Construir system prompt (con RAG si está disponible)
if ($useRag) {
    $systemPrompt = $voiceContext->buildSystemPromptWithRag($message, 15);
} else {
    $systemPrompt = $voiceContext->buildSystemPrompt();
}

// Construir mensajes para el LLM
$messages = [];

// Añadir historial si existe
foreach ($history as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

// Añadir mensaje actual
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Crear cliente OpenRouter con el system prompt de la voz
try {
    $client = new OpenRouterClient(
        Env::get('OPENROUTER_API_KEY'),
        'google/gemini-3-flash-preview', // Forzado por código
        $systemPrompt
    );
    
    $reply = $client->generateWithMessages($messages);
} catch (\Exception $e) {
    Response::error('llm_error', 'Error al generar respuesta: ' . $e->getMessage(), 500);
}

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
$fullHistory[] = ['role' => 'assistant', 'content' => $reply];

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
        'output_content' => $reply
    ]);
} else {
    // Crear nueva ejecución
    $executionId = $repo->create([
        'user_id' => (int)$user['id'],
        'voice_id' => $voiceId,
        'title' => $title,
        'input_data' => $inputData,
        'output_content' => $reply,
        'model' => $client->getModel()
    ]);
}

Response::json([
    'success' => true,
    'reply' => $reply,
    'execution_id' => $executionId
]);
