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
$name = isset($input['name']) ? trim((string)$input['name']) : '';

if ($id <= 0) {
    Response::error('validation_error', 'Invalid folder ID', 400);
}

if ($name === '') {
    Response::error('validation_error', 'Name is required', 400);
}

if (mb_strlen($name) > 150) {
    Response::error('validation_error', 'Name cannot exceed 150 characters', 400);
}

$repo = new FoldersRepo();

if (!$repo->rename((int)$user['id'], $id, $name)) {
    Response::error('not_found', 'Folder not found', 404);
}

Response::json(['ok' => true]);
