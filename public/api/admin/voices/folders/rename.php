<?php
/**
 * POST /api/admin/voices/folders/rename.php?slug=lex
 * Body JSON: { id, name }
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceFoldersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);
$name = clean_text($input, 'name', 255, true);

$folders = new VoiceFoldersRepo();
$folder = $folders->getById($id);
if (!$folder || (int)$folder['voice_id'] !== (int)$voice['id']) {
    Response::error('not_found', 'Folder not found for this voice', 404);
}

$folders->rename($id, $name);

Response::json(['success' => true, 'folder' => $folders->getById($id)]);
