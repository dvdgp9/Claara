<?php
/**
 * Chat Regenerate Full Endpoint
 * Regenera por completo una respuesta del asistente reusando el historial
 * de la conversación hasta ese mensaje. Solo texto (no reintenta archivos,
 * imágenes ni búsqueda web del turno original).
 */

require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../src/Repos/ConversationAccessRepo.php';
require_once __DIR__ . '/../../src/Repos/MessagesRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Chat\OpenRouterClient;
use Chat\ContextBuilder;
use Repos\ConversationsRepo;
use Repos\ConversationAccessRepo;
use Repos\MessagesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;

if (!$messageId || !$conversationId) {
    Response::error('validation_error', 'Se requiere ID de mensaje y conversación', 400);
}

$convos = new ConversationsRepo();
$conversationAccess = new ConversationAccessRepo();
$msgs = new MessagesRepo();

if (!$conversationAccess->canChat($conversationId, $user)) {
    Response::error('not_found', 'Conversation not found', 404);
}

$allMessages = $msgs->listByConversation($conversationId);

// Construir historial con todos los mensajes ANTERIORES al mensaje objetivo.
$history = [];
$found = false;
foreach ($allMessages as $m) {
    if ((int)$m['id'] === $messageId) {
        if ($m['role'] !== 'assistant') {
            Response::error('not_found', 'Mensaje no encontrado o no regenerable', 404);
        }
        $found = true;
        break;
    }
    $content = trim((string)($m['content'] ?? ''));
    if ($content === '') {
        continue;
    }
    $history[] = ['role' => $m['role'], 'content' => $content];
}

if (!$found) {
    Response::error('not_found', 'Mensaje no encontrado o no regenerable', 404);
}

if (empty($history)) {
    Response::error('validation_error', 'No hay contexto previo para regenerar', 400);
}

$contextBuilder = new ContextBuilder(null, $user, true);
$systemPrompt = $contextBuilder->buildSystemPrompt();

try {
    $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', $systemPrompt);
    $newContent = trim($client->generateWithMessages($history));

    if ($newContent === '') {
        Response::error('generation_error', 'La regeneración no devolvió contenido', 500);
    }

    $msgs->updateContent($messageId, $newContent);
    $convos->touch($conversationId);

    Response::json([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'content' => $newContent
        ]
    ]);
} catch (\Exception $e) {
    Response::error('generation_error', 'Error al regenerar: ' . $e->getMessage(), 500);
}
