<?php
/**
 * Elimina el acceso de un usuario a una conversación o carpeta
 * POST /api/sharing/remove.php
 * Body: { type: "conversation"|"folder", id: 123, user_id: 456 }
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

if (!in_array($type, ['conversation', 'folder'])) {
    Response::error('validation_error', 'Tipo inválido. Debe ser "conversation" o "folder"', 400);
}

if ($id <= 0) {
    Response::error('validation_error', 'ID inválido', 400);
}

if ($targetUserId <= 0) {
    Response::error('validation_error', 'ID de usuario inválido', 400);
}

$repo = new SharingRepo();

try {
    if ($type === 'conversation') {
        // Verificar que es propietario
        $access = $repo->getConversationAccess($id, (int)$user['id']);
        if ($access !== 'owner') {
            Response::error('forbidden', 'Solo el propietario puede gestionar la compartición', 403);
        }
        
        $removed = $repo->unshareConversation($id, $targetUserId);
    } else {
        // Verificar que es propietario
        $access = $repo->getFolderAccess($id, (int)$user['id']);
        if ($access !== 'owner') {
            Response::error('forbidden', 'Solo el propietario puede gestionar la compartición', 403);
        }
        
        $removed = $repo->unshareFolder($id, $targetUserId);
    }
    
    Response::json([
        'success' => $removed
    ]);
} catch (\Exception $e) {
    Response::error('sharing_error', $e->getMessage(), 400);
}
