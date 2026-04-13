<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/LlmModelsRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\LlmModelsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'Sólo GET', 405);
}

$user = AuthService::requireAuth();
if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Acceso denegado', 403);
}

$repo = new LlmModelsRepo();

Response::json([
    'models' => $repo->listAll()
]);
