<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/FoldersRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\FoldersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$sortOrder = (int)($input['sort_order'] ?? 0);

if ($id <= 0) {
    Response::error('validation_error', 'ID de carpeta inválido', 400);
}

$repo = new FoldersRepo();

if (!$repo->updateSortOrder((int)$user['id'], $id, $sortOrder)) {
    Response::error('not_found', 'Carpeta no encontrada', 404);
}

Response::json(['ok' => true]);
