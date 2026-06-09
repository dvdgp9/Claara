<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationAccessRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\ConversationAccessRepo;

$user = AuthService::requireAuth();
$accessRepo = new ConversationAccessRepo();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
    if ($conversationId <= 0) {
        Response::error('validation_error', 'conversation_id is required', 400);
    }

    $access = $accessRepo->getAccess($conversationId, $user);
    if (!$access || empty($access['can_manage'])) {
        Response::error('not_found', 'Conversation not found', 404);
    }

    Response::json([
        'shares' => $accessRepo->listShares($conversationId),
        'access' => [
            'permission' => $access['permission'],
            'can_manage' => (bool)$access['can_manage'],
        ],
    ]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'GET or POST only', 405);
}

Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$action = (string)($input['action'] ?? 'upsert');
$conversationId = (int)($input['conversation_id'] ?? 0);

if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id is required', 400);
}

$access = $accessRepo->getAccess($conversationId, $user);
if (!$access || empty($access['can_manage'])) {
    Response::error('not_found', 'Conversation not found', 404);
}

try {
    if ($action === 'remove') {
        $shareId = (int)($input['share_id'] ?? 0);
        if ($shareId <= 0) {
            Response::error('validation_error', 'share_id is required', 400);
        }

        $removed = $accessRepo->removeShare($conversationId, $shareId);
        Response::json([
            'ok' => true,
            'removed' => $removed,
            'shares' => $accessRepo->listShares($conversationId),
        ]);
    }

    $targetType = (string)($input['target_type'] ?? '');
    $targetId = (int)($input['target_id'] ?? 0);
    $permission = (string)($input['permission'] ?? 'view');

    if ($targetId <= 0) {
        Response::error('validation_error', 'target_id is required', 400);
    }

    $accessRepo->upsertShare($conversationId, $targetType, $targetId, $permission, (int)$user['id']);

    Response::json([
        'ok' => true,
        'shares' => $accessRepo->listShares($conversationId),
    ]);
} catch (\InvalidArgumentException $e) {
    Response::error('validation_error', $e->getMessage(), 400);
} catch (\Throwable $e) {
    Response::serverError('conversation_share_error', $e, 'Could not update conversation sharing');
}
