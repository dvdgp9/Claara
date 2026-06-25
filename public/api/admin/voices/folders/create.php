<?php
/**
 * POST /api/admin/voices/folders/create.php?slug=lex
 * Body JSON: { name, parent_id? }
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
$name = clean_text($input, 'name', 255, true);
$parentId = isset($input['parent_id']) && (int)$input['parent_id'] > 0 ? (int)$input['parent_id'] : null;

$voiceId = (int)$voice['id'];
$folders = new VoiceFoldersRepo();

if ($parentId !== null) {
    $parent = $folders->getById($parentId);
    if (!$parent || (int)$parent['voice_id'] !== $voiceId) {
        Response::error('invalid_parent', 'Parent folder not found for this voice', 400);
    }
}

try {
    $id = $folders->create($voiceId, $parentId, $name);
} catch (\InvalidArgumentException $e) {
    Response::error('validation_error', $e->getMessage(), 400);
}

Response::json(['success' => true, 'folder' => $folders->getById($id)], 201);
