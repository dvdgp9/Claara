<?php
/**
 * POST /api/admin/voices/folders/set-level.php?slug=lex
 * Body JSON: { folder_id, level_id }   (level_id null/0 = everyone)
 * Sets the minimum access level required to read a folder.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceFoldersRepo;
use Repos\VoiceProfilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$folderId = (int)($input['folder_id'] ?? 0);
$levelId = isset($input['level_id']) && (int)$input['level_id'] > 0 ? (int)$input['level_id'] : null;

$voiceId = (int)$voice['id'];
$folders = new VoiceFoldersRepo();
$folder = $folders->getById($folderId);
if (!$folder || (int)$folder['voice_id'] !== $voiceId) {
    Response::error('not_found', 'Folder not found for this voice', 404);
}

if ($levelId !== null) {
    $level = (new VoiceProfilesRepo())->getById($levelId);
    if (!$level || (int)$level['voice_id'] !== $voiceId) {
        Response::error('invalid_level', 'Level not found for this voice', 400);
    }
}

$folders->setRequiredLevel($folderId, $levelId);

Response::json(['success' => true, 'folder_id' => $folderId, 'level_id' => $levelId]);
