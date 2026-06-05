<?php
/**
 * Chat Regenerate Selection Endpoint
 * Regenera una parte específica de una respuesta de IA según las instrucciones del usuario
 */

require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../src/Repos/MessagesRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Chat\OpenRouterClient;
use Chat\ContextBuilder;
use Repos\ConversationsRepo;
use Repos\MessagesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$originalContent = trim((string)($input['original_content'] ?? ''));
$selectedText = trim((string)($input['selected_text'] ?? ''));
$instructions = trim((string)($input['instructions'] ?? ''));

// Validar input
if (!$messageId || !$conversationId) {
    Response::error('validation_error', 'Se requiere ID de mensaje y conversación', 400);
}

if ($selectedText === '' || $instructions === '') {
    Response::error('validation_error', 'Se requiere texto seleccionado e instrucciones', 400);
}

$convos = new ConversationsRepo();
$msgs = new MessagesRepo();

// Verificar que el usuario es dueño de la conversación
$conversation = $convos->findByIdForUser($conversationId, (int)$user['id']);
if (!$conversation) {
    Response::error('not_found', 'Conversación no encontrada', 404);
}

// Obtener el mensaje a editar
$messages = $msgs->listByConversation($conversationId);
$targetMessage = null;
foreach ($messages as $m) {
    if ((int)$m['id'] === $messageId && $m['role'] === 'assistant') {
        $targetMessage = $m;
        break;
    }
}

if (!$targetMessage) {
    Response::error('not_found', 'Mensaje no encontrado o no editable', 404);
}

// Construir el prompt de regeneración - enfoque directo: pedir mensaje completo editado
$contextBuilder = new ContextBuilder(null, $user, true);
$systemPrompt = $contextBuilder->buildSystemPrompt();

$editPrompt = <<<PROMPT
Tienes que editar una respuesta anterior. El usuario ha seleccionado una parte específica que quiere cambiar.

RESPUESTA ORIGINAL COMPLETA:
---
{$targetMessage['content']}
---

PARTE QUE EL USUARIO QUIERE CAMBIAR:
---
{$selectedText}
---

INSTRUCCIONES DEL USUARIO PARA ESA PARTE:
{$instructions}

INSTRUCCIONES IMPORTANTES:
1. Devuelve la respuesta COMPLETA con la parte seleccionada ya modificada según las instrucciones
2. NO cambies nada fuera de la parte seleccionada
3. Mantén exactamente el mismo formato, estructura y estilo del original
4. Solo modifica la sección que el usuario seleccionó
5. Devuelve ÚNICAMENTE el texto final, sin explicaciones ni comentarios
PROMPT;

try {
    $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', $systemPrompt);
    $newContent = trim($client->generateText($editPrompt));
    
    // Actualizar el mensaje en la base de datos
    $msgs->updateContent($messageId, $newContent);
    
    // Actualizar timestamp de conversación
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
