<?php
/**
 * POST /api/admin/voices/documents/move.php?slug=lex
 * Body JSON: { id, folder_id }
 * Moves a document to a folder and re-tags its Qdrant chunks.
 */
require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Repos\ContextDocsRepo;
use Repos\VoiceFoldersRepo;
use Rag\RagProcessor;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$id = (int)($input['id'] ?? 0);
$folderId = (int)($input['folder_id'] ?? 0);

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);
if (!$doc || ($doc['target_slug'] ?? '') !== $voice['slug']) {
    Response::error('not_found', 'Documento no encontrado para esta voz', 404);
}

$folders = new VoiceFoldersRepo();
$folder = $folders->getById($folderId);
if (!$folder || (int)$folder['voice_id'] !== (int)$voice['id']) {
    Response::error('invalid_folder', 'Carpeta no encontrada para esta voz', 400);
}

$repo->setFolder($id, $folderId);

$collection = (string)($voice['rag_collection'] ?? '');
if ($collection !== '') {
    try {
        (new RagProcessor(null, null, $collection))->setDocumentFolder((string)$doc['filename'], $folderId);
    } catch (\Throwable $e) {
        // The DB move is the source of truth; a stale Qdrant tag is corrected on
        // the next process run. Do not fail the move on a transient Qdrant error.
    }
}

Response::json(['success' => true, 'document' => $repo->getById($id)]);
