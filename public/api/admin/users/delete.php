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
$currentUser = AdminGuard::requireSuperadmin();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = isset($input['id']) ? (int)$input['id'] : 0;

if ($userId <= 0) {
    Response::error('validation_error', 'ID de usuario inválido', 400);
}

// Evitar auto-eliminación
if ($userId === (int)$currentUser['id']) {
    Response::error('validation_error', 'No puedes eliminar tu propia cuenta', 400);
}

$repo = new UsersRepo();

// Verificar que el usuario existe
$user = $repo->findById($userId);
if (!$user) {
    Response::error('not_found', 'Usuario no encontrado', 404);
}

// Proteger al último superadmin
if ($user['is_superadmin']) {
    $stmt = $repo->pdo->prepare('SELECT COUNT(*) FROM users WHERE is_superadmin = 1 AND status = "active"');
    $stmt->execute();
    $superadminCount = (int)$stmt->fetchColumn();
    
    if ($superadminCount <= 1) {
        Response::error('validation_error', 'No puedes eliminar al último superadministrador del sistema', 400);
    }
}

// Eliminar usuario
$repo->delete($userId);

Response::json(['success' => true]);
