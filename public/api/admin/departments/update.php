<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/DepartmentsRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\DepartmentsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

Session::requireCsrf();
AdminGuard::requireSuperadmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($input['id']) ? (int)$input['id'] : 0;
$name = trim((string)($input['name'] ?? ''));

if ($id <= 0) {
    Response::error('validation_error', 'ID de departamento inválido', 400);
}
if ($name === '') {
    Response::error('validation_error', 'Nombre requerido', 400);
}
if (mb_strlen($name, 'UTF-8') > 120) {
    Response::error('validation_error', 'El nombre no puede superar 120 caracteres', 400);
}

$repo = new DepartmentsRepo();
if (!$repo->findById($id)) {
    Response::error('not_found', 'Departamento no encontrado', 404);
}

try {
    $repo->update($id, $name);
    Response::json(['success' => true, 'department' => $repo->findById($id)]);
} catch (\Throwable $e) {
    Response::error('update_failed', 'No se pudo actualizar el departamento', 400);
}
