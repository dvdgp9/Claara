<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Rag\RagProcessor;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    Response::error('invalid_id', 'id requerido', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);
if (!$doc || ($doc['target_slug'] ?? '') !== $voice['slug']) {
    Response::error('not_found', 'Documento no encontrado para esta voz', 404);
}

$filePath = voice_documents_path($voice['slug']) . '/' . $doc['filename'];
if (!file_exists($filePath)) {
    Response::error('file_not_found', 'El archivo físico no existe', 404);
}

try {
    $processor = new RagProcessor(null, null, $voice['rag_collection']);
    if (!$processor->isQdrantHealthy()) {
        Response::error('qdrant_unavailable', 'Qdrant no está disponible', 503);
    }

    $repo->updateRagStatus($id, 'processing');
    $documentId = $voice['slug'] . '_' . pathinfo($doc['filename'], PATHINFO_FILENAME);
    $result = $processor->processDocument($filePath, $documentId, $doc['filename'], [
        'document_date' => $doc['document_date'] ?? null,
        'is_official_source' => !empty($doc['is_official_source']),
        'source_authority' => $doc['source_authority'] ?? null,
    ]);
    $repo->updateRagStatus($id, 'processed', $result['chunks_processed']);

    Response::json([
        'success' => true,
        'document' => $repo->getById($id),
        'processing_result' => $result,
    ]);
} catch (\Throwable $e) {
    $repo->updateRagStatus($id, 'error', null, $e->getMessage());
    Response::serverError('voice_document_process_failed', $e, 'No se pudo procesar el documento');
}
