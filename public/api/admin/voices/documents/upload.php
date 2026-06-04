<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[$user, $voice] = require_voice_document_context();
Session::requireCsrf();

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    Response::error('upload_error', 'No se pudo subir el archivo', 400);
}

$file = $_FILES['file'];
$extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($extension, ['pdf', 'txt', 'md'], true)) {
    Response::error('invalid_extension', 'Formatos permitidos: pdf, txt, md', 400);
}
if ((int)$file['size'] > 30 * 1024 * 1024) {
    Response::error('file_too_large', 'El archivo supera 30MB', 400);
}

$repo = new ContextDocsRepo();
$filename = ContextDocsRepo::sanitizeFilename($file['name']);
$targetPath = voice_documents_path($voice['slug']);
$baseName = pathinfo($filename, PATHINFO_FILENAME);
$ext = pathinfo($filename, PATHINFO_EXTENSION);
$counter = 1;
while (file_exists($targetPath . '/' . $filename) && $counter < 100) {
    $filename = $baseName . '_' . $counter . '.' . $ext;
    $counter++;
}
$destPath = $targetPath . '/' . $filename;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    Response::error('move_error', 'No se pudo guardar el archivo', 500);
}

try {
    $id = $repo->create([
        'target' => 'lex',
        'target_type' => 'voice',
        'target_slug' => $voice['slug'],
        'voice_id' => $voice['id'] ?? null,
        'filename' => $filename,
        'original_filename' => $file['name'],
        'file_extension' => $extension,
        'file_size' => (int)$file['size'],
        'status' => 'active',
        'description' => $_POST['description'] ?? null,
        'created_by' => $user['id'],
    ]);

    Response::json([
        'success' => true,
        'document' => $repo->getById($id),
    ], 201);
} catch (\Throwable $e) {
    if (file_exists($destPath)) {
        unlink($destPath);
    }
    Response::serverError('voice_document_upload_failed', $e, 'No se pudo registrar el documento');
}
