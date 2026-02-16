<?php
/**
 * API: Servir archivos de podcast con autenticación
 * GET /api/files/podcast.php?file=filename.wav
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;

Session::start();
$user = Session::user();

if (!$user) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

$filename = $_GET['file'] ?? '';

if (empty($filename)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Falta parámetro file']);
    exit;
}

// Prevenir directory traversal
$filename = basename($filename);

// Validar que sea un archivo WAV de podcast
if (!preg_match('/^podcast_[a-f0-9]{32}\.wav$/', $filename)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Nombre de archivo inválido']);
    exit;
}

$storageDir = dirname(__DIR__, 3) . '/storage/podcasts';
$filepath = $storageDir . '/' . $filename;

if (!file_exists($filepath)) {
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Archivo no encontrado']);
    exit;
}

// Servir archivo
header('Content-Type: audio/wav');
header('Content-Disposition: inline; filename="' . $filename . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: private, max-age=3600');
header('Accept-Ranges: bytes');

readfile($filepath);
exit;
