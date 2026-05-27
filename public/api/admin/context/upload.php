<?php
/**
 * POST /api/admin/context/upload.php
 * 
 * Uploads a new context document.
 * Requires superadmin.
 * 
 * Espera multipart/form-data con:
 * - target: 'lex' | 'eboniato' | 'ebonia'
 * - file: file to upload
 * - description: (optional) document description
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AdminGuard::requireSuperadmin();
Session::requireCsrf();

// Validar target
$target = $_POST['target'] ?? '';
if (!ContextDocsRepo::isValidTarget($target)) {
    Response::error('invalid_target', 'Invalid target. Allowed values: ' . implode(', ', ContextDocsRepo::getValidTargets()), 400);
}

// Validar archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'File exceeds server upload limit',
        UPLOAD_ERR_FORM_SIZE => 'File exceeds maximum allowed size',
        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Error writing file',
        UPLOAD_ERR_EXTENSION => 'PHP extension blocked the upload',
    ];
    $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMsg = $errorMessages[$errorCode] ?? 'Unknown upload error';
    Response::error('upload_error', $errorMsg, 400);
}

$file = $_FILES['file'];
$originalFilename = $file['name'];
$tmpPath = $file['tmp_name'];
$fileSize = $file['size'];
$documentDate = trim((string)($_POST['document_date'] ?? ''));
if ($documentDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $documentDate)) {
    Response::error('invalid_document_date', 'Document date must use YYYY-MM-DD format', 400);
}
$isOfficialSource = !empty($_POST['is_official_source']) ? 1 : 0;
$sourceAuthority = trim((string)($_POST['source_authority'] ?? ''));

// Validar extensión
$extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
if (!ContextDocsRepo::isExtensionAllowed($target, $extension)) {
    $allowed = implode(', ', ContextDocsRepo::getAllowedExtensions($target));
    Response::error('invalid_extension', "Extension '{$extension}' is not allowed for {$target}. Allowed: {$allowed}", 400);
}

// Validar tamaño (máximo 30MB)
$maxSize = 30 * 1024 * 1024;
if ($fileSize > $maxSize) {
    Response::error('file_too_large', 'File exceeds the 30MB maximum size', 400);
}

// Validar MIME type para mayor seguridad
$allowedMimes = [
    'pdf' => ['application/pdf'],
    'txt' => ['text/plain'],
    'md' => ['text/plain', 'text/markdown', 'text/x-markdown', 'application/octet-stream'],
];

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $tmpPath);
finfo_close($finfo);

$validMimes = $allowedMimes[$extension] ?? [];
if (!empty($validMimes) && !in_array($detectedMime, $validMimes)) {
    // Permitir algunos casos edge para markdown
    if ($extension !== 'md') {
        Response::error('invalid_mime', "MIME type '{$detectedMime}' is not valid for extension '{$extension}'", 400);
    }
}

// Preparar nombre de archivo sanitizado
$repo = new ContextDocsRepo();
$sanitizedFilename = $repo->generateUniqueFilename($target, $originalFilename);

// Obtener ruta destino
$targetPath = ContextDocsRepo::getTargetPath($target);
if (!$targetPath || !is_dir($targetPath)) {
    Response::error('target_path_error', 'Could not access target directory', 500);
}

$destPath = $targetPath . '/' . $sanitizedFilename;

// Comprobar permisos de escritura antes de mover (diagnóstico claro en prod)
if (!is_writable($targetPath)) {
    Response::error('target_not_writable', "Target directory is not writable by the web server: {$targetPath}", 500);
}

// Mover archivo
if (!move_uploaded_file($tmpPath, $destPath)) {
    $reason = is_writable($targetPath) ? 'move_uploaded_file failed' : 'destination not writable';
    Response::error('move_error', "Error saving file ({$reason})", 500);
}

// Crear registro en BD
try {
    $docId = $repo->create([
        'target' => $target,
        'filename' => $sanitizedFilename,
        'original_filename' => $originalFilename,
        'file_extension' => $extension,
        'file_size' => $fileSize,
        'status' => 'active',
        'description' => $_POST['description'] ?? null,
        'document_date' => $documentDate !== '' ? $documentDate : null,
        'is_official_source' => $isOfficialSource,
        'source_authority' => $sourceAuthority !== '' ? $sourceAuthority : null,
        'created_by' => $user['id'],
    ]);
    
    $doc = $repo->getById($docId);
    
    Response::json([
        'success' => true,
        'message' => 'Document uploaded successfully',
        'document' => $doc,
        'needs_rag_processing' => $target === 'lex',
        'needs_index_processing' => $target === 'lex'
    ]);
} catch (\Exception $e) {
    // Si falla la BD, eliminar el archivo
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    Response::error('db_error', 'Error registering document: ' . $e->getMessage(), 500);
}
