<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationAccessRepo.php';
require_once __DIR__ . '/../../../src/Repos/MessagesRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\ConversationAccessRepo;
use Repos\MessagesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id is required', 400);
}

$accessRepo = new ConversationAccessRepo();
if (!$accessRepo->canView($conversationId, $user)) {
    Response::error('not_found', 'Conversation not found', 404);
}

$msgs = new MessagesRepo();
Response::json([
    'activity' => $msgs->getConversationActivity($conversationId),
]);
