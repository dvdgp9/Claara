<?php
/**
 * POST /api/admin/voices/access/list-set.php?slug=lex
 * Body JSON: { user_id, listed: bool }
 * Adds or removes a user from the voice allow-list (used in 'list' mode).
 */
require_once __DIR__ . '/../documents/_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/VoiceAccessListRepo.php';

use App\Response;
use App\Session;
use Repos\VoiceAccessListRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$userId = (int)($input['user_id'] ?? 0);
$listed = !empty($input['listed']);
if ($userId <= 0) {
    Response::error('validation_error', 'user_id is required', 400);
}

$voiceId = (int)$voice['id'];
$repo = new VoiceAccessListRepo();
$listed ? $repo->add($voiceId, $userId) : $repo->remove($voiceId, $userId);

Response::json(['success' => true, 'user_id' => $userId, 'listed' => $listed]);
