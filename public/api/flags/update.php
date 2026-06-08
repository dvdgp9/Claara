<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/VoiceFlagsRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\VoiceFlagsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();
$userId = (int)$user['id'];
$isAdmin = !empty($user['is_superadmin']);

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;
$status = (string)($input['status'] ?? '');
$note = isset($input['resolution_note']) ? trim((string)$input['resolution_note']) : null;

if ($id <= 0) {
    Response::error('validation_error', 'id requerido', 400);
}
if (!in_array($status, ['open', 'in_progress', 'resolved', 'dismissed'], true)) {
    Response::error('validation_error', 'status inválido', 400);
}

$repo = new VoiceFlagsRepo();
$flag = $repo->get($id);
if (!$flag) {
    Response::error('not_found', 'Reporte no encontrado', 404);
}

// Autorización: admin o responsable de la voz del flag
if (!$isAdmin && !$repo->userCanManage($id, $userId)) {
    Response::error('forbidden', 'No puedes gestionar este reporte', 403);
}

try {
    $repo->updateStatus($id, $status, $userId, $note);
    Response::json(['success' => true, 'flag' => $repo->get($id)]);
} catch (\Throwable $e) {
    Response::serverError('flag_update_failed', $e, 'No se pudo actualizar el reporte');
}
