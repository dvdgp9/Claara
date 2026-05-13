<?php
/**
 * PUT /api/admin/context/update.php
 * 
 * Updates document content or metadata.
 * Requires superadmin.
 * 
 * Body JSON:
 * - id: document ID
 * - content: (optional) new file content (md/txt only)
 * - description: (optional) new description
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error('method_not_allowed', 'PUT only', 405);
}

AdminGuard::requireSuperadmin();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    Response::error('invalid_id', 'Invalid document ID', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Document not found', 404);
}

$updated = false;
$contentUpdated = false;

// Actualizar contenido del archivo si se proporciona
if (isset($input['content'])) {
    $ext = strtolower($doc['file_extension']);
    
    if (!in_array($ext, ['md', 'txt'])) {
        Response::error('cannot_edit', 'Only .md and .txt files can be edited', 400);
    }
    
    $targetPath = ContextDocsRepo::getTargetPath($doc['target']);
    $filePath = $targetPath . '/' . $doc['filename'];
    
    if (!file_exists($filePath)) {
        Response::error('file_not_found', 'Physical file does not exist', 404);
    }
    
    // Escribir nuevo contenido
    $result = file_put_contents($filePath, $input['content']);
    if ($result === false) {
        Response::error('write_error', 'Error writing file', 500);
    }
    
    // Actualizar tamaño en BD
    $newSize = strlen($input['content']);
    $repo->update($id, ['file_size' => $newSize]);
    
    $contentUpdated = true;
    $updated = true;
    
    // If Lex, mark index as pending reprocessing
    if ($doc['target'] === 'lex' && $doc['rag_status'] === 'processed') {
        $repo->updateRagStatus($id, 'pending');
    }
}

// Actualizar descripción si se proporciona
if (array_key_exists('description', $input)) {
    $repo->update($id, ['description' => $input['description']]);
    $updated = true;
}

if (!$updated) {
    Response::error('no_changes', 'No changes were provided', 400);
}

// Obtener documento actualizado
$doc = $repo->getById($id);

Response::json([
    'success' => true,
    'message' => $contentUpdated ? 'Content updated successfully' : 'Metadata updated successfully',
    'document' => $doc,
    'needs_rag_reprocessing' => $contentUpdated && $doc['target'] === 'lex',
    'needs_index_reprocessing' => $contentUpdated && $doc['target'] === 'lex'
]);
