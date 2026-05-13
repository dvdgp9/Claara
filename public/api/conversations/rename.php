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

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int)($input['id'] ?? 0);
$title = trim((string)($input['title'] ?? ''));
if ($id <= 0 || $title === '') {
    Response::error('validation_error', 'The id and title fields are required', 400);
}

$repo = new ConversationsRepo();
if (!$repo->findByIdForUser($id, (int)$user['id'])) {
    Response::error('not_found', 'Conversation not found', 404);
}
$repo->rename((int)$user['id'], $id, $title);
Response::json(['ok' => true]);
