<?php
/**
 * POST /api/admin/voices/folders/delete.php?slug=lex
 * Body JSON: { id }
 *
 * Deletes a folder and its subtree. Documents inside are reassigned to the
 * deleted folder's parent (both in the DB and in their Qdrant chunks) so they
 * are never orphaned. The root folder cannot be deleted.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceFoldersRepo;
use Repos\ContextDocsRepo;
use Rag\RagProcessor;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);

$voiceId = (int)$voice['id'];
$folders = new VoiceFoldersRepo();
$folder = $folders->getById($id);
if (!$folder || (int)$folder['voice_id'] !== $voiceId) {
    Response::error('not_found', 'Folder not found for this voice', 404);
}
if ((int)$folder['is_root'] === 1) {
    Response::error('cannot_delete_root', 'The root folder cannot be deleted', 400);
}

$subtree = $folders->subtreeIds($folder);
$parentId = $folder['parent_id'] !== null
    ? (int)$folder['parent_id']
    : $folders->ensureRootFolder($voiceId);

$docsRepo = new ContextDocsRepo();
$collection = (string)($voice['rag_collection'] ?? '');
$affectedDocs = array_filter(
    $docsRepo->listByVoice((string)$voice['slug']),
    static fn(array $d): bool => isset($d['folder_id']) && in_array((int)$d['folder_id'], $subtree, true)
);

// Re-tag the affected documents' chunks to the parent BEFORE deleting, so a
// Qdrant failure aborts the delete instead of orphaning chunks.
if ($collection !== '' && $affectedDocs) {
    try {
        $processor = new RagProcessor(null, null, $collection);
        foreach ($affectedDocs as $d) {
            $processor->setDocumentFolder((string)$d['filename'], $parentId);
        }
    } catch (\Throwable $e) {
        Response::serverError('folder_delete_reindex_failed', $e, 'Could not move documents out of the folder');
    }
}

$moved = $docsRepo->moveDocsToFolder($subtree, $parentId);
$folders->delete($id);

Response::json([
    'success' => true,
    'reassigned_documents' => $moved,
    'moved_to_folder' => $parentId,
]);
