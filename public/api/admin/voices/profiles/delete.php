<?php
/**
 * POST /api/admin/voices/profiles/delete.php?slug=lex
 * Body JSON: { id }
 *
 * Deleting a profile removes its folder grants and unassigns its users (via the
 * ON DELETE CASCADE foreign keys); those users lose access to the voice unless
 * they are superadmins or voice responsibles.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceProfilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);

$repo = new VoiceProfilesRepo();
$profile = $repo->getById($id);
if (!$profile || (int)$profile['voice_id'] !== (int)$voice['id']) {
    Response::error('not_found', 'Profile not found for this voice', 404);
}

$repo->delete($id);

Response::json(['success' => true]);
