<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../../src/Repos/MessagesRepo.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\ConversationsRepo;
use Repos\MessagesRepo;
use Repos\UsageLogRepo;
use Voices\VoiceQueryService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$voiceSlug = trim((string)($input['voice_slug'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;

if ($voiceSlug === '') {
    Response::error('validation_error', 'voice_slug requerido', 400);
}
if ($message === '') {
    Response::error('validation_error', 'message requerido', 400);
}
if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id requerido', 400);
}

$convos = new ConversationsRepo();
$conversation = $convos->findByIdForUser($conversationId, (int)$user['id']);
if (!$conversation) {
    Response::error('not_found', 'Conversación no encontrada', 404);
}

$msgs = new MessagesRepo();
$history = [];
foreach ($msgs->listByConversation($conversationId) as $row) {
    $content = trim((string)($row['content'] ?? ''));
    if ($content === '') {
        continue;
    }
    $history[] = [
        'role' => (string)$row['role'],
        'content' => $content,
    ];
}

try {
    $result = (new VoiceQueryService())->query($user, $voiceSlug, $message, array_slice($history, -8));
} catch (\InvalidArgumentException $e) {
    Response::error('validation_error', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    $status = in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 500;
    Response::error($status === 403 ? 'forbidden' : 'voice_query_error', $e->getMessage(), $status);
} catch (\Throwable $e) {
    Response::error('voice_query_error', 'Error al consultar la voz: ' . $e->getMessage(), 500);
}

$voice = $result['voice'] ?? [];
$voiceName = (string)($voice['name'] ?? $voiceSlug);
$answer = trim((string)$result['answer']);
$content = "### Respuesta de {$voiceName}\n\n" . $answer;

$sources = array_values(array_filter((array)($result['meta']['sources'] ?? [])));
if ($sources) {
    $content .= "\n\n**Fuentes consultadas:**\n";
    foreach (array_slice($sources, 0, 8) as $source) {
        $content .= "\n- " . (string)$source;
    }
}

$messageId = $msgs->create(
    $conversationId,
    (int)$user['id'],
    'assistant',
    $content,
    (string)($result['model'] ?? 'google/gemini-3-flash-preview')
);
$convos->touch($conversationId);
(new UsageLogRepo())->log((int)$user['id'], 'voice', 1, ['voice_id' => $voiceSlug, 'source' => 'claara_chat']);

Response::json([
    'success' => true,
    'message' => [
        'id' => $messageId,
        'role' => 'assistant',
        'content' => $content,
    ],
    'voice' => [
        'slug' => $voiceSlug,
        'name' => $voiceName,
    ],
    'meta' => $result['meta'],
]);
