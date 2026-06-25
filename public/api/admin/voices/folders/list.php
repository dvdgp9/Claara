<?php
/**
 * GET /api/admin/voices/folders/list.php?slug=lex
 * Returns the folder tree for a voice with per-folder document counts.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use Repos\VoiceFoldersRepo;
use Repos\ContextDocsRepo;

[, $voice] = require_voice_document_context();

$voiceId = (int)$voice['id'];
$folders = new VoiceFoldersRepo();
$folders->ensureRootFolder($voiceId);

$counts = (new ContextDocsRepo())->countByFolder((string)$voice['slug']);

$tree = array_map(static function (array $f) use ($counts): array {
    return [
        'id' => (int)$f['id'],
        'parent_id' => $f['parent_id'] !== null ? (int)$f['parent_id'] : null,
        'name' => (string)$f['name'],
        'depth' => (int)$f['depth'],
        'is_root' => (int)$f['is_root'] === 1,
        'doc_count' => $counts[(int)$f['id']] ?? 0,
        'required_level_id' => isset($f['required_level_id']) && $f['required_level_id'] !== null ? (int)$f['required_level_id'] : null,
    ];
}, $folders->listByVoice($voiceId));

Response::json([
    'success' => true,
    'folders' => $tree,
]);
