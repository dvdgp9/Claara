<?php
/**
 * API: Listar documentos disponibles para una voz
 * GET /api/voices/docs.php?voice_id=lex
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Voices/VoiceContextBuilder.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use App\Response;
use Voices\VoiceContextBuilder;
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
    Response::error('forbidden', 'No tienes acceso a esta voz', 403);
}

$builder = new VoiceContextBuilder($voiceId);

if (!$builder->voiceExists()) {
    Response::error('invalid_voice', 'Voice not found', 404);
}

$docs = $builder->listDocuments();

Response::json([
    'success' => true,
    'voice' => $builder->getVoiceInfo(),
    'documents' => $docs
]);
