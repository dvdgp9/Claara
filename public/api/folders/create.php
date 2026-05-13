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
$name = isset($input['name']) ? trim((string)$input['name']) : '';
$parentId = isset($input['parent_id']) ? (int)$input['parent_id'] : null;
$sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

if ($name === '') {
    Response::error('validation_error', 'Name is required', 400);
}

if (mb_strlen($name) > 150) {
    Response::error('validation_error', 'Name cannot exceed 150 characters', 400);
}

$repo = new FoldersRepo();

try {
    $id = $repo->create((int)$user['id'], $name, $parentId > 0 ? $parentId : null, $sortOrder);
    Response::json(['id' => $id]);
} catch (\Exception $e) {
    Response::error('folder_error', $e->getMessage(), 400);
}
