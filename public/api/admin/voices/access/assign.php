<?php
/**
 * POST /api/admin/voices/access/assign.php?slug=lex
 * Body JSON: { user_id, profile_id }   (profile_id null/0 = remove access)
 */
require_once __DIR__ . '/../documents/_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Repos\VoiceProfilesRepo;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$userId = (int)($input['user_id'] ?? 0);
$profileId = isset($input['profile_id']) ? (int)$input['profile_id'] : 0;

if ((new UsersRepo())->findById($userId) === null) {
    Response::error('not_found', 'User not found', 404);
}

$voiceId = (int)$voice['id'];
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

Response::json(['success' => true, 'user_id' => $userId, 'profile_id' => $profileId > 0 ? $profileId : null]);
