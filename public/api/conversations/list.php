<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationsRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\ConversationsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'updated_at';
$folderId = isset($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

// Si folder_id es -1, no filtrar (mostrar todas)
// Si es 0, mostrar solo conversaciones sin carpeta
// Si es > 0, mostrar conversaciones de esa carpeta
$filterFolderId = ($folderId === -1) ? null : $folderId;

$repo = new ConversationsRepo();
$list = $repo->listByUser((int)$user['id'], $sort, $filterFolderId);

Response::json(['items' => $list]);
