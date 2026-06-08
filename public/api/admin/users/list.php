<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../../../src/Repos/OrganizationResponsibilityRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\UsersRepo;
use Repos\UserFeatureAccessRepo;
use Repos\OrganizationResponsibilityRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$repo = new UsersRepo();
$accessRepo = new UserFeatureAccessRepo();
$responsibilityRepo = new OrganizationResponsibilityRepo();
$users = $repo->listAll();
$departmentResponsibilities = $responsibilityRepo->getUserDepartmentResponsibilitiesMap();
$voiceResponsibilities = $responsibilityRepo->getUserVoiceResponsibilitiesMap();
$availableVoices = $accessRepo->getAvailableFeaturesGrouped()['voice'] ?? [];

// Inyectar permisos
foreach ($users as &$user) {
    $userId = (int)$user['id'];
    $user['access'] = $accessRepo->getUserAccess((int)$user['id']);
    $user['is_superadmin'] = (bool)$user['is_superadmin'];
    $user['department_responsibilities'] = $departmentResponsibilities[$userId] ?? [];
    $user['voice_responsibilities'] = $voiceResponsibilities[$userId] ?? [];
    $user['accessible_voices'] = [];
    foreach ($availableVoices as $voice) {
        $key = 'voice:' . $voice['feature_slug'];
        if ($user['is_superadmin'] || ($user['access'][$key] ?? false)) {
            $user['accessible_voices'][] = $voice;
        }
    }
}

Response::json(['users' => $users]);
