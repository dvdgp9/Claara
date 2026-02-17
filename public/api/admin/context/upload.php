<?php
/**
 * POST /api/admin/context/upload.php
 * 
 * Sube un nuevo documento de contexto.
 * Requiere superadmin.
 * 
 * Espera multipart/form-data con:
 * - target: 'lex' | 'eboniato' | 'ebonia'
 * - file: archivo a subir
 * - description: (opcional) descripción del documento
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use App\Session;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

$user = AdminGuard::requireSuperadmin();
Session::requireCsrf();

// Validar target
$target = $_POST['target'] ?? '';
if (!ContextDocsRepo::isValidTarget($target)) {
    Response::error('invalid_target', 'Target inválido. Valores permitidos: ' . implode(', ', ContextDocsRepo::getValidTargets()), 400);
}

// Validar archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'El archivo excede el tamaño máximo permitido por el servidor',
        UPLOAD_ERR_FORM_SIZE => 'El archivo excede el tamaño máximo permitido',
        UPLOAD_ERR_PARTIAL => 'El archivo se subió parcialmente',
        UPLOAD_ERR_NO_FILE => 'No se subió ningún archivo',
        UPLOAD_ERR_NO_TMP_DIR => 'Falta carpeta temporal',
        UPLOAD_ERR_CANT_WRITE => 'Error al escribir el archivo',
        UPLOAD_ERR_EXTENSION => 'Extensión PHP bloqueó la subida',
    ];
    $errorCode = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMsg = $errorMessages[$errorCode] ?? 'Error desconocido al subir archivo';
    Response::error('upload_error', $errorMsg, 400);
}

$file = $_FILES['file'];
$originalFilename = $file['name'];
$tmpPath = $file['tmp_name'];
$fileSize = $file['size'];

// Validar extensión
$extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
if (!ContextDocsRepo::isExtensionAllowed($target, $extension)) {
    $allowed = implode(', ', ContextDocsRepo::getAllowedExtensions($target));
    Response::error('invalid_extension', "Extensión '{$extension}' no permitida para {$target}. Permitidas: {$allowed}", 400);
}

// Validar tamaño (máximo 10MB)
$maxSize = 10 * 1024 * 1024;
if ($fileSize > $maxSize) {
    Response::error('file_too_large', 'El archivo excede el tamaño máximo de 10MB', 400);
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
        Response::error('invalid_mime', "Tipo MIME '{$detectedMime}' no válido para extensión '{$extension}'", 400);
    }
}

// Preparar nombre de archivo sanitizado
$repo = new ContextDocsRepo();
$sanitizedFilename = $repo->generateUniqueFilename($target, $originalFilename);

// Obtener ruta destino
$targetPath = ContextDocsRepo::getTargetPath($target);
if (!$targetPath || !is_dir($targetPath)) {
    Response::error('target_path_error', 'No se pudo acceder al directorio de destino', 500);
}

$destPath = $targetPath . '/' . $sanitizedFilename;

// Mover archivo
if (!move_uploaded_file($tmpPath, $destPath)) {
    Response::error('move_error', 'Error al guardar el archivo', 500);
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
        'created_by' => $user['id'],
    ]);
    
    $doc = $repo->getById($docId);
    
    Response::json([
        'success' => true,
        'message' => 'Documento subido correctamente',
        'document' => $doc,
        'needs_rag_processing' => $target === 'lex'
    ]);
} catch (\Exception $e) {
    // Si falla la BD, eliminar el archivo
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    Response::error('db_error', 'Error al registrar el documento: ' . $e->getMessage(), 500);
}
