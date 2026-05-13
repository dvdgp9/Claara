<?php
/**
 * API: Listar documentos de una voz (AJAX)
 * GET /api/voices/list_docs_ajax.php?voice_id=lex
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Voices/VoiceContextBuilder.php';

use App\Session;
use App\Response;
use Voices\VoiceContextBuilder;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

$voiceId = $_GET['voice_id'] ?? '';
if (!$voiceId) {
    Response::error('missing_voice', 'voice_id is required', 400);
}

$builder = new VoiceContextBuilder($voiceId);
if (!$builder->voiceExists()) {
    Response::error('invalid_voice', 'Voice not found', 404);
}

$docs = $builder->listDocuments();

Response::json([
    'success' => true,
    'documents' => $docs
]);
