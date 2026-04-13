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
$modelKey = trim((string)($input['model_key'] ?? ''));
$label = trim((string)($input['label'] ?? ''));
$isActive = !empty($input['is_active']);
$sortOrder = isset($input['sort_order']) ? (int)$input['sort_order'] : 0;

if ($id <= 0) {
    Response::error('validation_error', 'id requerido', 400);
}
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
    $repo->update($id, $modelKey, $label, $isActive, $sortOrder);
    Response::json(['success' => true]);
} catch (\Throwable $e) {
    Response::error('update_failed', 'No se pudo actualizar el modelo (puede que la clave ya exista)', 400);
}
