<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/LlmModelsRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\LlmModelsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();
if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Acceso denegado', 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;

if ($id <= 0) {
    Response::error('validation_error', 'id requerido', 400);
}

$repo = new LlmModelsRepo();
$deleted = $repo->delete($id);

if (!$deleted) {
    Response::error('not_found', 'Modelo no encontrado', 404);
}

Response::json(['success' => true]);
