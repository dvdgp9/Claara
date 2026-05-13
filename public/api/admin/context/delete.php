<?php
/**
 * DELETE /api/admin/context/delete.php?id=X
 * 
 * Deletes a context document.
 * For Lex documents, also deletes vectors from Qdrant.
 * Requires superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';
require_once __DIR__ . '/../../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../../src/App/Env.php';

use App\Response;
use App\Session;
use App\Env;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;
use Rag\QdrantClient;

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    Response::error('method_not_allowed', 'DELETE only', 405);
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

$errors = [];

// 1. If Lex has processed index data, remove vectors from Qdrant
if ($doc['target'] === 'lex' && $doc['rag_status'] === 'processed' && $doc['rag_chunk_count'] > 0) {
    try {
        $qdrant = new QdrantClient(
            Env::get('QDRANT_HOST', 'localhost'),
            (int)Env::get('QDRANT_PORT', 6333)
        );
        
        $documentId = pathinfo($doc['filename'], PATHINFO_FILENAME);
        
        // Eliminar puntos por document_id
        if ($qdrant->collectionExists('lex_knowledge_base')) {
            $qdrant->deletePointsByFilter('lex_knowledge_base', [
                'must' => [
                    ['key' => 'document_id', 'match' => ['value' => $documentId]]
                ]
            ]);
        }
    } catch (\Exception $e) {
        $errors[] = 'Error deleting vectors from Qdrant: ' . $e->getMessage();
    }
}

// 2. Eliminar archivo físico
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        $errors[] = 'Error deleting physical file';
    }
}

// 3. Eliminar registro de BD
if (!$repo->delete($id)) {
    Response::error('db_error', 'Error deleting database record', 500);
}

Response::json([
    'success' => true,
    'message' => 'Document deleted successfully',
    'warnings' => !empty($errors) ? $errors : null
]);
