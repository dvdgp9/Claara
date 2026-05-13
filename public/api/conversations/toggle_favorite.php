<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationsRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\ConversationsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();
$user = AuthService::requireAuth();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    Response::error('validation_error', 'id es obligatorio', 400);
}

$repo = new ConversationsRepo();
$success = $repo->toggleFavorite((int)$user['id'], $id);

if (!$success) {
    Response::error('not_found', 'Conversación no encontrada', 404);
}

Response::json(['success' => true]);
