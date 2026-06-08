<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';

use App\Response;
use App\Session;
use App\DB;
use Auth\AdminGuard;
use Repos\UsersRepo;
use Repos\UserFeatureAccessRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();
AdminGuard::requireSuperadmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$jobTitle = trim((string)($input['job_title'] ?? ''));
$departmentId = isset($input['department_id']) && $input['department_id'] !== '' ? (int)$input['department_id'] : null;
$isSuperadmin = !empty($input['is_superadmin']);

// Validaciones
if ($email === '' || $firstName === '' || $lastName === '' || $password === '') {
    Response::error('validation_error', 'Email, nombre, apellidos y contraseña son obligatorios', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('validation_error', 'Email inválido', 400);
}

if (strlen($password) < 8) {
    Response::error('validation_error', 'La contraseña debe tener al menos 8 caracteres', 400);
}

if (mb_strlen($jobTitle, 'UTF-8') > 120) {
    Response::error('validation_error', 'Job title cannot exceed 120 characters', 400);
}

$pdo = DB::pdo();

// Verificar si el email ya existe
$repo = new UsersRepo($pdo);
$existing = $repo->findByEmail($email);
if ($existing) {
    Response::error('validation_error', 'El email ya está en uso', 400);
}

// Crear usuario
$pdo->beginTransaction();
try {
    $passwordHash = password_hash($password, PASSWORD_ARGON2ID);
    $userId = $repo->create($email, $passwordHash, $firstName, $lastName, $departmentId, $isSuperadmin, $jobTitle !== '' ? $jobTitle : null);

    $accessRepo = new UserFeatureAccessRepo($pdo);
    if (!$accessRepo->grantDefaultAccessForNewUser($userId)) {
        throw new \RuntimeException('No se pudieron aplicar los permisos por defecto');
    }

    $pdo->commit();
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::serverError('create_failed', $e, 'No se pudo crear el usuario');
}

Response::json([
    'success' => true,
    'user_id' => $userId
]);
