<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\UsersRepo;
use Repos\UserFeatureAccessRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$repo = new UsersRepo();
$accessRepo = new UserFeatureAccessRepo();
$users = $repo->listAll();

// Inyectar permisos
foreach ($users as &$user) {
    $user['access'] = $accessRepo->getUserAccess((int)$user['id']);
    $user['is_superadmin'] = (bool)$user['is_superadmin'];
}

Response::json(['users' => $users]);
