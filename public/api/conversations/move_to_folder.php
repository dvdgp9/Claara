<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationsRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\ConversationsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$conversationId = (int)($input['conversation_id'] ?? 0);
$folderId = isset($input['folder_id']) ? (int)$input['folder_id'] : null;

if ($conversationId <= 0) {
    Response::error('validation_error', 'Invalid conversation ID', 400);
}

$repo = new ConversationsRepo();

try {
    // folderId = 0 o null significa "sin carpeta" (raíz)
    if (!$repo->moveToFolder((int)$user['id'], $conversationId, $folderId > 0 ? $folderId : null)) {
        Response::error('not_found', 'Conversation not found', 404);
    }
    Response::json(['ok' => true]);
} catch (\Exception $e) {
    Response::error('folder_error', $e->getMessage(), 400);
}
