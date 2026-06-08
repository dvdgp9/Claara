<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/App/DB.php';
require_once __DIR__ . '/../../../../src/Repos/DepartmentsRepo.php';
require_once __DIR__ . '/../../../../src/Repos/OrganizationResponsibilityRepo.php';

use App\Response;
use App\Session;
use App\DB;
use Auth\AdminGuard;
use Repos\DepartmentsRepo;
use Repos\OrganizationResponsibilityRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();
AdminGuard::requireSuperadmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$name = trim((string)($input['name'] ?? ''));
$responsibleUserIds = is_array($input['responsible_user_ids'] ?? null) ? $input['responsible_user_ids'] : [];

if ($name === '') {
    Response::error('validation_error', 'Nombre requerido', 400);
}
if (mb_strlen($name, 'UTF-8') > 120) {
    Response::error('validation_error', 'El nombre no puede superar 120 caracteres', 400);
}

$pdo = DB::pdo();
$repo = new DepartmentsRepo($pdo);
$responsibilityRepo = new OrganizationResponsibilityRepo($pdo);

try {
    $pdo->beginTransaction();
    $id = $repo->create($name);
    $responsibilityRepo->setDepartmentResponsibles($id, $responsibleUserIds);
    $pdo->commit();
    Response::json(['success' => true, 'department' => $repo->findById($id)]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::error('create_failed', 'No se pudo crear el departamento', 400);
}
