<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/DepartmentsRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\DepartmentsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$repo = new DepartmentsRepo();
$departments = $repo->listAll();

Response::json(['departments' => $departments]);
