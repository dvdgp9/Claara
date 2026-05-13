<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/FoldersRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\FoldersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();

$repo = new FoldersRepo();
$folders = $repo->listByUser((int)$user['id']);

// Añadir contador de conversaciones para cada carpeta
foreach ($folders as &$folder) {
    $folder['conversation_count'] = $repo->countConversations((int)$folder['id']);
}

Response::json(['folders' => $folders]);
