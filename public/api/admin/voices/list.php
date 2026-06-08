<?php
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../../../src/Repos/OrganizationResponsibilityRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';

use App\Response;
use Repos\VoicesRepo;
use Repos\OrganizationResponsibilityRepo;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

require_voice_editor();

$includeArchived = ($_GET['include_archived'] ?? '') === '1';
$repo = new VoicesRepo();
$responsibilityRepo = new OrganizationResponsibilityRepo();
$voices = $repo->listAll($includeArchived);
$responsibles = $responsibilityRepo->getVoiceResponsiblesMap();

foreach ($voices as &$voice) {
    $voice['responsible_users'] = $responsibles[$voice['slug']] ?? [];
}

$usersRepo = new UsersRepo();
$users = array_map(static fn(array $user): array => [
    'id' => (int)$user['id'],
    'email' => (string)$user['email'],
    'first_name' => (string)$user['first_name'],
    'last_name' => (string)$user['last_name'],
    'job_title' => (string)($user['job_title'] ?? ''),
    'status' => (string)$user['status'],
], array_filter($usersRepo->listAll(), static fn(array $user): bool => ($user['status'] ?? '') === 'active'));

Response::json([
    'success' => true,
    'voices' => $voices,
    'users' => $users,
]);
