<?php
/**
 * GET /api/admin/context/view.php?id=X
 * 
 * Obtiene el contenido de un documento de contexto.
 * Requiere superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'Sólo GET', 405);
}

AdminGuard::requireSuperadmin();

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    Response::error('invalid_id', 'ID de documento inválido', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Documento no encontrado', 404);
}

// Obtener ruta física
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

// Verificar que el archivo existe
if (!file_exists($filePath)) {
    Response::error('file_not_found', 'El archivo físico no existe en el servidor', 404);
}

// Leer contenido solo para archivos de texto (md, txt)
$content = null;
$canEdit = false;

$ext = strtolower($doc['file_extension']);
if (in_array($ext, ['md', 'txt'])) {
    $content = file_get_contents($filePath);
    $canEdit = true;
} elseif ($ext === 'pdf') {
    // Para PDFs, indicar que no se puede mostrar/editar inline
    $content = '[Archivo PDF - No se puede mostrar inline]';
    $canEdit = false;
}

Response::json([
    'document' => $doc,
    'content' => $content,
    'can_edit' => $canEdit,
    'file_exists' => true
]);
