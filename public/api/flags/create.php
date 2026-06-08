<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/VoiceFlagsRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\VoiceFlagsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$voiceSlug = trim((string)($input['voice_slug'] ?? ''));
$type = (string)($input['type'] ?? 'missing_info');
$note = trim((string)($input['note'] ?? ''));
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;

if ($voiceSlug === '') {
    Response::error('validation_error', 'voice_slug requerido', 400);
}
if (mb_strlen($note) > 2000) {
    Response::error('validation_error', 'La nota es demasiado larga (máx. 2000)', 400);
}

try {
    $repo = new VoiceFlagsRepo();
    $id = $repo->create([
        'voice_slug' => $voiceSlug,
        'raised_by_user_id' => (int)$user['id'],
        'conversation_id' => $conversationId ?: null,
        'message_id' => $messageId ?: null,
        'type' => $type,
        'note' => $note,
    ]);

    Response::json(['success' => true, 'id' => $id]);
} catch (\Throwable $e) {
    Response::serverError('flag_create_failed', $e, 'No se pudo crear el reporte');
}
