<?php
/**
 * POST /api/admin/voices/profiles/reorder.php?slug=lex
 * Body JSON: { id, direction }   direction = 'up' (more access) | 'down' (less)
 * Swaps a level's rank with its neighbour.
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
$direction = (string)($input['direction'] ?? '');

$voiceId = (int)$voice['id'];
$repo = new VoiceProfilesRepo();
$level = $repo->getById($id);
if (!$level || (int)$level['voice_id'] !== $voiceId) {
    Response::error('not_found', 'Level not found for this voice', 404);
}

// listByVoice is ordered by rank DESC (highest access first): 'up' moves toward
// the top (higher rank), 'down' toward the bottom (lower rank).
$levels = $repo->listByVoice($voiceId);
$idx = null;
foreach ($levels as $i => $l) {
    if ((int)$l['id'] === $id) { $idx = $i; break; }
}

$swapIdx = $direction === 'up' ? $idx - 1 : $idx + 1;
if ($idx === null || $swapIdx < 0 || $swapIdx >= count($levels)) {
    Response::json(['success' => true, 'moved' => false]);
}

$a = $levels[$idx];
$b = $levels[$swapIdx];
$repo->setRank((int)$a['id'], (int)$b['rank']);
$repo->setRank((int)$b['id'], (int)$a['rank']);

Response::json(['success' => true, 'moved' => true]);
