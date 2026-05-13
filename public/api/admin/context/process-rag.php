<?php
/**
 * POST /api/admin/context/process-rag.php?id=X
 * 
 * Processes a Lex document for AI indexing (chunking, embeddings, Qdrant).
 * Only valid for target 'lex' documents.
 * Requires superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';
require_once __DIR__ . '/../../../../src/Rag/RagProcessor.php';
require_once __DIR__ . '/../../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../../src/Rag/EmbeddingService.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;
use Rag\RagProcessor;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

AdminGuard::requireSuperadmin();
Session::requireCsrf();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Response::error('invalid_id', 'Invalid document ID', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Document not found', 404);
}

// Solo permitido para target 'lex'
if ($doc['target'] !== 'lex') {
    Response::error('invalid_target', 'Index processing is only available for Lex documents', 400);
}

// Obtener ruta física
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

if (!file_exists($filePath)) {
    Response::error('file_not_found', 'Physical file does not exist', 404);
}

// Initialize index processor
try {
    $processor = new RagProcessor();
} catch (\Exception $e) {
    Response::error('rag_init_error', 'Error initializing index processor: ' . $e->getMessage(), 500);
}

// Verificar que Qdrant está disponible
if (!$processor->isQdrantHealthy()) {
    Response::error('qdrant_unavailable', 'Qdrant is unavailable. Verify the service is running.', 503);
}

// Marcar como procesando
$repo->updateRagStatus($id, 'processing');

try {
    // Procesar documento
    $documentId = pathinfo($doc['filename'], PATHINFO_FILENAME);
    $result = $processor->processDocument($filePath, $documentId, $doc['filename']);
    
    // Actualizar estado a procesado
    $repo->updateRagStatus($id, 'processed', $result['chunks_processed']);
    
    // Obtener documento actualizado
    $doc = $repo->getById($id);
    
    Response::json([
        'success' => true,
        'message' => 'Document processed successfully',
        'document' => $doc,
        'processing_result' => [
            'chunks_created' => $result['chunks_processed'],
            'text_length' => $result['text_length'],
            'collection' => $result['collection']
        ]
    ]);
    
} catch (\Exception $e) {
    // Marcar como error
    $repo->updateRagStatus($id, 'error', null, $e->getMessage());
    
    Response::error('processing_error', 'Error processing document: ' . $e->getMessage(), 500);
}
