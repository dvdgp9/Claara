<?php
require_once __DIR__ . '/../../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../../src/Repos/LlmModelsRepo.php';

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
$modelKey = trim((string)($input['model_key'] ?? ''));
$label = trim((string)($input['label'] ?? ''));

if ($modelKey === '') {
    Response::error('validation_error', 'model_key requerido', 400);
}
if ($label === '') {
    Response::error('validation_error', 'label requerido', 400);
}
if (strlen($modelKey) > 120 || strlen($label) > 120) {
    Response::error('validation_error', 'Campos demasiado largos', 400);
}

$repo = new LlmModelsRepo();

try {
    $id = $repo->create($modelKey, $label, true);
    Response::json([
        'success' => true,
        'id' => $id,
        'model_key' => $modelKey,
        'label' => $label
    ]);
} catch (\Throwable $e) {
    Response::error('create_failed', 'No se pudo crear el modelo (puede que ya exista)', 400);
}
