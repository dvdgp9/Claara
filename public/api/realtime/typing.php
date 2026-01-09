<?php
/**
 * Reporta estado de escritura de un usuario en una conversación
 * POST /api/realtime/typing.php
 * Body: { conversation_id: 123, is_typing: true }
 */
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/SharingRepo.php';
require_once __DIR__ . '/../../../src/Repos/PresenceRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\SharingRepo;
use Repos\PresenceRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$isTyping = isset($input['is_typing']) ? (bool)$input['is_typing'] : false;

if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id inválido', 400);
}

$userId = (int)$user['id'];

// Verificar acceso a la conversación
$sharingRepo = new SharingRepo();
$access = $sharingRepo->getConversationAccess($conversationId, $userId);

if (!$access) {
    Response::error('forbidden', 'Sin acceso a esta conversación', 403);
}

// Solo usuarios con permiso de escritura pueden reportar que están escribiendo
if ($access === 'viewer') {
    Response::error('forbidden', 'No tienes permiso de escritura en esta conversación', 403);
}

$presenceRepo = new PresenceRepo();
$presenceRepo->setTyping($userId, $conversationId, $isTyping);

Response::json([
    'success' => true,
    'is_typing' => $isTyping
]);
