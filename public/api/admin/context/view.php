<?php
/**
 * GET /api/admin/context/view.php?id=X
 * GET /api/admin/context/view.php?id=X&raw=1 (serve PDF directly)
 * 
 * Gets content for a context document.
 * Requires superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$id = (int)($_GET['id'] ?? 0);
$raw = isset($_GET['raw']);

if ($id <= 0) {
    Response::error('invalid_id', 'Invalid document ID', 400);
}

$repo = new ContextDocsRepo();
$doc = $repo->getById($id);

if (!$doc) {
    Response::error('not_found', 'Document not found', 404);
}

// Obtener ruta física
$targetPath = ContextDocsRepo::getTargetPath($doc['target']);
$filePath = $targetPath . '/' . $doc['filename'];

// Verificar que el archivo existe
if (!file_exists($filePath)) {
    Response::error('file_not_found', 'Physical file does not exist on server', 404);
}

$ext = strtolower($doc['file_extension']);

// Si es PDF y se pide raw, servir directamente
if ($ext === 'pdf' && $raw) {
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $doc['filename'] . '"');
    header('Content-Length: ' . filesize($filePath));
    readfile($filePath);
    exit;
}

// Leer contenido solo para archivos de texto (md, txt)
$content = null;
$canEdit = false;

if (in_array($ext, ['md', 'txt'])) {
    $content = file_get_contents($filePath);
    $canEdit = true;
} elseif ($ext === 'pdf') {
    // Para PDFs, devolver URL para ver
    $content = null;
    $canEdit = false;
}

Response::json([
    'document' => $doc,
    'content' => $content,
    'can_edit' => $canEdit,
    'file_exists' => true,
    'pdf_url' => ($ext === 'pdf') ? "/api/admin/context/view.php?id={$id}&raw=1" : null
]);
