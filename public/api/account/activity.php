<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/UsersRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();

$repo = new UsersRepo();
$stats = $repo->getActivityStats((int)$user['id']);

Response::json($stats);
