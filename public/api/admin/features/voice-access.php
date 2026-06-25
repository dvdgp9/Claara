<?php
/**
 * GET /api/admin/features/voice-access.php?user_id=123
 * Per-voice access profiles for a user (single source of truth = user_voice_profiles).
 * Used by the Feature Permissions page to render a profile dropdown per voice.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/VoicesRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\VoicesRepo;
use Repos\VoiceProfilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
if (!$user['is_superadmin']) {
    Response::error('forbidden', 'Access denied', 403);
}

$userId = (int)($_GET['user_id'] ?? 0);
if ($userId <= 0) {
    Response::error('validation_error', 'user_id is required', 400);
}

$voicesRepo = new VoicesRepo();
$profilesRepo = new VoiceProfilesRepo();

$voices = array_map(static function (array $voice) use ($profilesRepo, $userId): array {
    $voiceId = (int)$voice['id'];
    $assigned = $profilesRepo->getUserProfile($userId, $voiceId);
    return [
        'voice_id' => $voiceId,
        'slug' => (string)$voice['slug'],
        'name' => (string)$voice['name'],
        'profiles' => array_map(
            static fn(array $p): array => ['id' => (int)$p['id'], 'name' => (string)$p['name']],
            $profilesRepo->listByVoice($voiceId)
        ),
        'assigned_profile_id' => $assigned ? (int)$assigned['id'] : null,
    ];
}, $voicesRepo->listPublished());

Response::json(['success' => true, 'voices' => $voices]);
