<?php
/**
 * PUT /api/admin/context/update.php
 * 
 * Actualiza el contenido o metadatos de un documento.
 * Requiere superadmin.
 * 
 * Body JSON:
 * - id: ID del documento
 * - content: (opcional) nuevo contenido del archivo (solo md/txt)
 * - description: (opcional) nueva descripción
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    Response::error('method_not_allowed', 'Sólo PUT', 405);
}

AdminGuard::requireSuperadmin();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id = (int)($input['id'] ?? 0);
if ($id <= 0) {
    Response::error('invalid_id', 'ID de documento inválido', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Documento no encontrado', 404);
}

$updated = false;
$contentUpdated = false;

// Actualizar contenido del archivo si se proporciona
if (isset($input['content'])) {
    $ext = strtolower($doc['file_extension']);
    
    if (!in_array($ext, ['md', 'txt'])) {
        Response::error('cannot_edit', 'Solo se pueden editar archivos .md y .txt', 400);
    }
    
    $targetPath = ContextDocsRepo::getTargetPath($doc['target']);
    $filePath = $targetPath . '/' . $doc['filename'];
    
    if (!file_exists($filePath)) {
        Response::error('file_not_found', 'El archivo físico no existe', 404);
    }
    
    // Escribir nuevo contenido
    $result = file_put_contents($filePath, $input['content']);
    if ($result === false) {
        Response::error('write_error', 'Error al escribir el archivo', 500);
    }
    
    // Actualizar tamaño en BD
    $newSize = strlen($input['content']);
    $repo->update($id, ['file_size' => $newSize]);
    
    $contentUpdated = true;
    $updated = true;
    
    // Si es Lex, marcar RAG como pendiente de reprocesar
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
    Response::error('no_changes', 'No se proporcionaron cambios para aplicar', 400);
}

// Obtener documento actualizado
$doc = $repo->getById($id);

Response::json([
    'success' => true,
    'message' => $contentUpdated ? 'Contenido actualizado correctamente' : 'Metadatos actualizados correctamente',
    'document' => $doc,
    'needs_rag_reprocessing' => $contentUpdated && $doc['target'] === 'lex'
]);
