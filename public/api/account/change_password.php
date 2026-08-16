<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use I18n\I18n;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', I18n::translate('auth.error.method_not_allowed'), 405);
}

Session::requireCsrf();
$user = AuthService::requireAuth();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$currentPassword = (string)($input['current_password'] ?? '');
$newPassword = (string)($input['new_password'] ?? '');
$confirmPassword = (string)($input['confirm_password'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
    Response::error('validation_error', I18n::translate('account.api.all_fields_required'), 400);
}

if ($newPassword !== $confirmPassword) {
    Response::error('validation_error', I18n::translate('account.password_mismatch'), 400);
}

if (strlen($newPassword) < 8) {
    Response::error('validation_error', I18n::translate('account.api.password_minimum'), 400);
}

// Verificar contraseña actual
$repo = new UsersRepo();
$dbUser = $repo->findByEmail($user['email']);
if (!$dbUser || !password_verify($currentPassword, $dbUser['password_hash'])) {
    Response::error('validation_error', I18n::translate('account.api.current_password_incorrect'), 400);
}

// Actualizar contraseña
$newHash = password_hash($newPassword, PASSWORD_ARGON2ID);
$repo->updatePassword((int)$user['id'], $newHash);

Response::json(['success' => true]);
