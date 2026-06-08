<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/DepartmentsRepo.php';
require_once __DIR__ . '/../../../../src/Repos/OrganizationResponsibilityRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\DepartmentsRepo;
use Repos\OrganizationResponsibilityRepo;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$repo = new DepartmentsRepo();
$departments = $repo->listAll();
$responsibilityRepo = new OrganizationResponsibilityRepo();
$responsibles = $responsibilityRepo->getDepartmentResponsiblesMap();

foreach ($departments as &$department) {
    $departmentId = (int)$department['id'];
    $department['responsible_users'] = $responsibles[$departmentId] ?? [];
}

$usersRepo = new UsersRepo();
$users = array_map(static fn(array $user): array => [
    'id' => (int)$user['id'],
    'email' => (string)$user['email'],
    'first_name' => (string)$user['first_name'],
    'last_name' => (string)$user['last_name'],
    'job_title' => (string)($user['job_title'] ?? ''),
    'department_id' => isset($user['department_id']) ? (int)$user['department_id'] : null,
    'status' => (string)$user['status'],
], $usersRepo->listAll());

Response::json(['departments' => $departments, 'users' => $users]);
