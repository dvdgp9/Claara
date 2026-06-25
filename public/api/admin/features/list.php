<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\UserFeatureAccessRepo;

// Solo GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

// Requiere autenticación y ser superadmin
$user = AuthService::requireAuth();
if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Access denied', 403);
}

$repo = new UserFeatureAccessRepo();

Response::json([
    'features' => $repo->getAvailableFeaturesGrouped(),
    'users' => $repo->getAllUsersWithAccess()
]);
