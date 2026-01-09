<?php
/**
 * Lista usuarios con acceso a una conversación o carpeta
 * GET /api/sharing/list.php?type=conversation&id=123
 * GET /api/sharing/list.php?type=folder&id=123
 */
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/SharingRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\SharingRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'Sólo GET', 405);
}

$user = AuthService::requireAuth();

$type = $_GET['type'] ?? '';
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!in_array($type, ['conversation', 'folder'])) {
    Response::error('validation_error', 'Tipo inválido. Debe ser "conversation" o "folder"', 400);
}

if ($id <= 0) {
    Response::error('validation_error', 'ID inválido', 400);
}

$repo = new SharingRepo();

// Verificar que el usuario tiene acceso (solo propietarios pueden ver la lista completa)
if ($type === 'conversation') {
    $access = $repo->getConversationAccess($id, (int)$user['id']);
    if ($access !== 'owner') {
        Response::error('forbidden', 'Solo el propietario puede gestionar la compartición', 403);
    }
    
    $shares = $repo->listConversationShares($id);
    $owner = $repo->getConversationOwner($id);
} else {
    $access = $repo->getFolderAccess($id, (int)$user['id']);
    if ($access !== 'owner') {
        Response::error('forbidden', 'Solo el propietario puede gestionar la compartición', 403);
    }
    
    $shares = $repo->listFolderShares($id);
    $owner = $repo->getFolderOwner($id);
}

Response::json([
    'owner' => $owner,
    'shares' => array_map(function($s) {
        return [
            'id' => (int)$s['id'],
            'user_id' => (int)$s['user_id'],
            'first_name' => $s['first_name'],
            'last_name' => $s['last_name'],
            'email' => $s['email'],
            'can_write' => (bool)$s['can_write'],
            'created_at' => $s['created_at']
        ];
    }, $shares)
]);
