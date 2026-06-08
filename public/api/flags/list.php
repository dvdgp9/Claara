<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/VoiceFlagsRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\VoiceFlagsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
$userId = (int)$user['id'];
$isAdmin = !empty($user['is_superadmin']);

$repo = new VoiceFlagsRepo();
$isResponsible = $repo->isResponsibleForAnyVoice($userId);

if (!$isAdmin && !$isResponsible) {
    Response::error('forbidden', 'No tienes acceso al panel de reportes', 403);
}

$filters = [];
if (!empty($_GET['status'])) {
    $filters['status'] = (string)$_GET['status'];
}
if (!empty($_GET['voice_slug'])) {
    $filters['voice_slug'] = (string)$_GET['voice_slug'];
}

try {
    if ($isAdmin) {
        $assigned = $repo->listAll($filters);
        $unassigned = $repo->listUnassigned($filters);
        $openCount = $repo->countOpenAll();
    } else {
        $assigned = $repo->listForResponsible($userId, $filters);
        $unassigned = [];
        $openCount = $repo->countOpenForResponsible($userId);
    }

    Response::json([
        'success' => true,
        'is_admin' => $isAdmin,
        'open_count' => $openCount,
        'flags' => $assigned,
        'unassigned' => $unassigned,
    ]);
} catch (\Throwable $e) {
    Response::serverError('flag_list_failed', $e, 'No se pudieron cargar los reportes');
}
