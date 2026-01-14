<?php
/**
 * API: Servir documentos generados (PDF, DOCX)
 * GET /api/files/document.php?file=filename.pdf
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use Utils\DocumentGenerator;

Session::start();
$user = Session::user();

if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Falta parámetro file']);
    exit;
}

// Prevenir directory traversal
$filename = basename($filename);

$generator = new DocumentGenerator();
$filepath = $generator->getFilePath($filename);

if (!$filepath) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
    exit;
}

// Determinar MIME type
$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
];

$mimeType = $mimeTypes[$ext] ?? 'application/octet-stream';

// Servir archivo
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');

readfile($filepath);
exit;
