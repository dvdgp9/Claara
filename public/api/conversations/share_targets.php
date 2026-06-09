<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../src/Repos/DepartmentsRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\UsersRepo;
use Repos\DepartmentsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$currentUser = AuthService::requireAuth();

$usersRepo = new UsersRepo();
$departmentsRepo = new DepartmentsRepo();

$users = array_values(array_map(static fn(array $user): array => [
    'id' => (int)$user['id'],
    'email' => (string)$user['email'],
    'first_name' => (string)$user['first_name'],
    'last_name' => (string)$user['last_name'],
    'job_title' => (string)($user['job_title'] ?? ''),
    'department_id' => isset($user['department_id']) ? (int)$user['department_id'] : null,
    'department_name' => (string)($user['department_name'] ?? ''),
], array_filter(
    $usersRepo->listAll(),
    static fn(array $user): bool => (int)$user['id'] !== (int)$currentUser['id'] && ($user['status'] ?? '') === 'active'
)));

$departments = array_map(static fn(array $department): array => [
    'id' => (int)$department['id'],
    'name' => (string)$department['name'],
    'slug' => (string)$department['slug'],
    'user_count' => (int)($department['user_count'] ?? 0),
], $departmentsRepo->listAll());

Response::json([
    'users' => $users,
    'departments' => $departments,
]);
