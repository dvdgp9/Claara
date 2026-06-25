<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\UserFeatureAccessRepo;

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

// Requiere autenticación y ser superadmin
$user = AuthService::requireAuth();
Session::requireCsrf();

if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Access denied', 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$userId = isset($input['user_id']) ? (int)$input['user_id'] : 0;
$featureType = $input['feature_type'] ?? '';
$featureSlug = $input['feature_slug'] ?? '';
$enabled = isset($input['enabled']) ? (bool)$input['enabled'] : false;

// Validar
if ($userId <= 0) {
    Response::error('validation_error', 'user_id is required', 400);
}
if (!in_array($featureType, ['gesture', 'voice', 'feature'])) {
    Response::error('validation_error', 'invalid feature_type', 400);
}
if (empty($featureSlug)) {
    Response::error('validation_error', 'feature_slug is required', 400);
}

$repo = new UserFeatureAccessRepo();

if ($repo->setAccess($userId, $featureType, $featureSlug, $enabled)) {
    Response::json([
        'success' => true,
        'user_id' => $userId,
        'feature_type' => $featureType,
        'feature_slug' => $featureSlug,
        'enabled' => $enabled
    ]);
} else {
    Response::error('update_failed', 'Could not update permission', 500);
}
