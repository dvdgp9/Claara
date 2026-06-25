<?php
/**
 * API: Listar historial de ejecuciones de una voz
 * GET /api/voices/history.php?voice_id=lex
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Voices/VoiceExecutionsRepo.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use App\Response;
use Voices\VoiceExecutionsRepo;
use Repos\UserFeatureAccessRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

$voiceId = $_GET['voice_id'] ?? '';
if (!$voiceId) {
    Response::error('missing_voice', 'voice_id is required', 400);
}
if (!(new UserFeatureAccessRepo())->hasVoiceAccess((int)$user['id'], $voiceId)) {
    Response::error('forbidden', 'You do not have access to this voice', 403);
}

$repo = new VoiceExecutionsRepo();
$items = $repo->listByVoice((int)$user['id'], $voiceId, 50);

Response::json([
    'success' => true,
    'items' => $items
]);
