<?php
/**
 * POST /api/admin/voices/profiles/update.php?slug=lex
 * Body JSON: { id, name, description? }
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
$name = clean_text($input, 'name', 120, true);
$description = clean_text($input, 'description', 300, false);

$repo = new VoiceProfilesRepo();
$profile = $repo->getById($id);
if (!$profile || (int)$profile['voice_id'] !== (int)$voice['id']) {
    Response::error('not_found', 'Profile not found for this voice', 404);
}

$repo->update($id, $name, $description);

Response::json(['success' => true, 'profile' => $repo->getById($id)]);
