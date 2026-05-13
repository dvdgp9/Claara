<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();
AdminGuard::requireSuperadmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = isset($input['id']) ? (int)$input['id'] : 0;
$email = trim($input['email'] ?? '');
$firstName = trim($input['first_name'] ?? '');
$lastName = trim($input['last_name'] ?? '');
$departmentId = isset($input['department_id']) && $input['department_id'] !== '' ? (int)$input['department_id'] : null;
$status = (string)($input['status'] ?? 'active');
$isSuperadmin = !empty($input['is_superadmin']);
$newPassword = isset($input['new_password']) ? trim((string)$input['new_password']) : '';

// Validaciones
if ($userId <= 0) {
    Response::error('validation_error', 'ID de usuario inválido', 400);
}

if ($email === '' || $firstName === '' || $lastName === '') {
    Response::error('validation_error', 'Email, nombre y apellidos son obligatorios', 400);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    Response::error('validation_error', 'Email inválido', 400);
}

if (!in_array($status, ['active', 'disabled'])) {
    Response::error('validation_error', 'Estado inválido', 400);
}

if ($newPassword !== '' && strlen($newPassword) < 8) {
    Response::error('validation_error', 'La contraseña debe tener al menos 8 caracteres', 400);
}

$repo = new UsersRepo();

// Verificar que el usuario existe
$user = $repo->findById($userId);
if (!$user) {
    Response::error('not_found', 'Usuario no encontrado', 404);
}

// Evitar que un admin se quite a sí mismo el rol de superadmin
if ($userId === (int)$currentUser['id'] && $user['is_superadmin'] && !$isSuperadmin) {
    Response::error('validation_error', 'No puedes quitarte a ti mismo el rol de superadministrador', 400);
}

// Proteger al último superadmin activo
if ($user['is_superadmin'] && !$isSuperadmin) {
    $stmt = $repo->pdo->prepare('SELECT COUNT(*) FROM users WHERE is_superadmin = 1 AND status = "active"');
    $stmt->execute();
    $superadminCount = (int)$stmt->fetchColumn();
    
    if ($superadminCount <= 1) {
        Response::error('validation_error', 'No puedes quitar el rol de superadministrador al último admin del sistema', 400);
    }
}

// Verificar si el email cambió y si ya está en uso
if ($email !== $user['email']) {
    $existing = $repo->findByEmail($email);
    if ($existing) {
        Response::error('validation_error', 'El email ya está en uso', 400);
    }
    $repo->updateEmail($userId, $email);
}

// Actualizar datos del usuario
$repo->update($userId, $firstName, $lastName, $departmentId, $status, $isSuperadmin);

// Actualizar contraseña si se proporcionó
if ($newPassword !== '') {
    $passwordHash = password_hash($newPassword, PASSWORD_ARGON2ID);
    $repo->updatePassword($userId, $passwordHash);
}

Response::json(['success' => true]);
