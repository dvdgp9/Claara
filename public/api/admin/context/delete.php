<?php
/**
 * DELETE /api/admin/context/delete.php?id=X
 * 
 * Elimina un documento de contexto.
 * Para documentos de Lex, también elimina los vectores de Qdrant.
 * Requiere superadmin.
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
    Response::error('method_not_allowed', 'Sólo DELETE', 405);
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

$errors = [];

// 1. Si es Lex con RAG procesado, eliminar vectores de Qdrant
if ($doc['target'] === 'lex' && $doc['rag_status'] === 'processed' && $doc['rag_chunk_count'] > 0) {
    try {
        $qdrant = new QdrantClient(
            Env::get('QDRANT_HOST', 'localhost'),
            (int)Env::get('QDRANT_PORT', 6333)
        );
        
        $documentId = pathinfo($doc['filename'], PATHINFO_FILENAME);
        
        // Eliminar puntos por document_id
        if ($qdrant->collectionExists('lex_convenios')) {
            $qdrant->deletePointsByFilter('lex_convenios', [
                'must' => [
                    ['key' => 'document_id', 'match' => ['value' => $documentId]]
                ]
            ]);
        }
    } catch (\Exception $e) {
        $errors[] = 'Error al eliminar vectores de Qdrant: ' . $e->getMessage();
    }
}

// 2. Eliminar archivo físico
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

if (file_exists($filePath)) {
    if (!unlink($filePath)) {
        $errors[] = 'Error al eliminar el archivo físico';
    }
}

// 3. Eliminar registro de BD
if (!$repo->delete($id)) {
    Response::error('db_error', 'Error al eliminar el registro de la base de datos', 500);
}

Response::json([
    'success' => true,
    'message' => 'Documento eliminado correctamente',
    'warnings' => !empty($errors) ? $errors : null
]);
