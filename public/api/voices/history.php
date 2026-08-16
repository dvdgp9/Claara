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
use I18n\I18n;
use Voices\VoiceExecutionsRepo;
use Voices\VoiceAccessResolver;
use Repos\VoicesRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', I18n::translate('auth.error.unauthorized'), 401);
}

$voiceId = $_GET['voice_id'] ?? '';
if (!$voiceId) {
    Response::error('missing_voice', I18n::translate('voice_api.voice_required'), 400);
}
$voice = (new VoicesRepo())->findBySlug($voiceId) ?? ['slug' => $voiceId];
if (!(new VoiceAccessResolver())->hasVoiceAccess((int)$user['id'], $voice)) {
    Response::error('forbidden', I18n::translate('voice_api.forbidden'), 403);
}

$repo = new VoiceExecutionsRepo();
$items = $repo->listByVoice((int)$user['id'], $voiceId, 50);

Response::json([
    'success' => true,
    'items' => $items
]);
