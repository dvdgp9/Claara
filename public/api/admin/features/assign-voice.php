<?php
/**
 * POST /api/admin/features/assign-voice.php
 * Body JSON: { user_id, voice_id, profile_id }  (profile_id null/0 = remove access)
 * Assigns a user's access profile in a voice (writes user_voice_profiles).
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\UsersRepo;
use Repos\VoiceProfilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();
if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Access denied', 403);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$userId = (int)($input['user_id'] ?? 0);
$voiceId = (int)($input['voice_id'] ?? 0);
$profileId = isset($input['profile_id']) ? (int)$input['profile_id'] : 0;

if ($userId <= 0 || $voiceId <= 0) {
    Response::error('validation_error', 'user_id and voice_id are required', 400);
}
if ((new UsersRepo())->findById($userId) === null) {
    Response::error('not_found', 'User not found', 404);
}

$profiles = new VoiceProfilesRepo();

if ($profileId > 0) {
    $profile = $profiles->getById($profileId);
    if (!$profile || (int)$profile['voice_id'] !== $voiceId) {
        Response::error('invalid_profile', 'Profile not found for this voice', 400);
    }
    $profiles->assignUser($userId, $voiceId, $profileId);
} else {
    $profiles->unassignUser($userId, $voiceId);
}

Response::json([
    'success' => true,
    'user_id' => $userId,
    'voice_id' => $voiceId,
    'profile_id' => $profileId > 0 ? $profileId : null,
]);
