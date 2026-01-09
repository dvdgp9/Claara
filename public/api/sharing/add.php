<?php
/**
 * Comparte una conversación o carpeta con un usuario
 * POST /api/sharing/add.php
 * Body: { type: "conversation"|"folder", id: 123, user_id: 456, can_write: true }
 */
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/SharingRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\SharingRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$type = $input['type'] ?? '';
$id = isset($input['id']) ? (int)$input['id'] : 0;
$targetUserId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$canWrite = isset($input['can_write']) ? (bool)$input['can_write'] : true;

if (!in_array($type, ['conversation', 'folder'])) {
    Response::error('validation_error', 'Tipo inválido. Debe ser "conversation" o "folder"', 400);
}

if ($id <= 0) {
    Response::error('validation_error', 'ID inválido', 400);
}

if ($targetUserId <= 0) {
    Response::error('validation_error', 'ID de usuario inválido', 400);
}

if ($targetUserId === (int)$user['id']) {
    Response::error('validation_error', 'No puedes compartir contigo mismo', 400);
}

$repo = new SharingRepo();

try {
    if ($type === 'conversation') {
        // Verificar que es propietario
        $access = $repo->getConversationAccess($id, (int)$user['id']);
        if ($access !== 'owner') {
            Response::error('forbidden', 'Solo el propietario puede compartir esta conversación', 403);
        }
        
        $shareId = $repo->shareConversation($id, $targetUserId, (int)$user['id'], $canWrite);
    } else {
        // Verificar que es propietario
        $access = $repo->getFolderAccess($id, (int)$user['id']);
        if ($access !== 'owner') {
            Response::error('forbidden', 'Solo el propietario puede compartir esta carpeta', 403);
        }
        
        $shareId = $repo->shareFolder($id, $targetUserId, (int)$user['id'], $canWrite);
    }
    
    Response::json([
        'success' => true,
        'share_id' => $shareId
    ]);
} catch (\Exception $e) {
    Response::error('sharing_error', $e->getMessage(), 400);
}
