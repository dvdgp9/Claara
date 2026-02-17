<?php
/**
 * POST /api/admin/context/process-rag.php?id=X
 * 
 * Procesa un documento de Lex para RAG (chunking, embeddings, Qdrant).
 * Solo válido para documentos del target 'lex'.
 * Requiere superadmin.
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
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

AdminGuard::requireSuperadmin();
Session::requireCsrf();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    Response::error('invalid_id', 'ID de documento inválido', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Documento no encontrado', 404);
}

// Solo permitido para target 'lex'
if ($doc['target'] !== 'lex') {
    Response::error('invalid_target', 'El procesamiento RAG solo está disponible para documentos de Lex', 400);
}

// Obtener ruta física
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

if (!file_exists($filePath)) {
    Response::error('file_not_found', 'El archivo físico no existe', 404);
}

// Inicializar procesador RAG
$processor = new RagProcessor();

// Verificar que Qdrant está disponible
if (!$processor->isQdrantHealthy()) {
    Response::error('qdrant_unavailable', 'Qdrant no está disponible. Verifica que el servicio está corriendo.', 503);
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
        'message' => 'Documento procesado correctamente',
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
    
    Response::error('processing_error', 'Error al procesar documento: ' . $e->getMessage(), 500);
}
