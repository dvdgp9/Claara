<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Repos\UsersRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

$freshUser = (new UsersRepo())->findById((int)$user['id']);
if ($freshUser) {
    $user = array_merge($user, [
        'email' => $freshUser['email'],
        'first_name' => $freshUser['first_name'],
        'last_name' => $freshUser['last_name'],
        'job_title' => $freshUser['job_title'] ?? null,
        'department_id' => $freshUser['department_id'] ? (int)$freshUser['department_id'] : null,
        'department_name' => $freshUser['department_name'] ?? null,
        'is_superadmin' => (bool)$freshUser['is_superadmin'],
    ]);
    $_SESSION['user'] = $user;
}
Response::json(['user' => $user, 'csrf_token' => $_SESSION['csrf_token'] ?? null]);
