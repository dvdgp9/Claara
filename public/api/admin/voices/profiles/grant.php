<?php
/**
 * POST /api/admin/voices/profiles/grant.php?slug=lex
 * Body JSON: { profile_id, folder_id, granted: bool }
 * Grants or revokes a folder for a profile. The grant inherits down the tree.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceProfilesRepo;
use Repos\VoiceFoldersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$profileId = (int)($input['profile_id'] ?? 0);
$folderId = (int)($input['folder_id'] ?? 0);
$granted = (bool)($input['granted'] ?? false);

$voiceId = (int)$voice['id'];
$profiles = new VoiceProfilesRepo();
$folders = new VoiceFoldersRepo();

$profile = $profiles->getById($profileId);
if (!$profile || (int)$profile['voice_id'] !== $voiceId) {
    Response::error('not_found', 'Profile not found for this voice', 404);
}
$folder = $folders->getById($folderId);
if (!$folder || (int)$folder['voice_id'] !== $voiceId) {
    Response::error('invalid_folder', 'Folder not found for this voice', 400);
}

if ($granted) {
    $profiles->grantFolder($folderId, $profileId);
} else {
    $profiles->revokeFolder($folderId, $profileId);
}

Response::json(['success' => true, 'granted' => $granted]);
