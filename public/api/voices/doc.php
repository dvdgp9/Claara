<?php
/**
 * API: Obtener contenido de un documento de una voz
 * GET /api/voices/doc.php?voice_id=lex&doc_id=convenio_colectivo
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Voices/VoiceContextBuilder.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';

use App\Session;
use App\Response;
use Voices\VoiceContextBuilder;
use Repos\UserFeatureAccessRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

$voiceId = $_GET['voice_id'] ?? '';
$docId = $_GET['doc_id'] ?? '';

if (!$voiceId) {
    Response::error('missing_voice', 'voice_id is required', 400);
}
if (!$docId) {
    Response::error('missing_doc', 'doc_id is required', 400);
}
if (!(new UserFeatureAccessRepo())->hasVoiceAccess((int)$user['id'], $voiceId)) {
    Response::error('forbidden', 'No tienes acceso a esta voz', 403);
}

$builder = new VoiceContextBuilder($voiceId);

if (!$builder->voiceExists()) {
    Response::error('invalid_voice', 'Voice not found', 404);
}

// Buscar el documento
$docs = $builder->listDocuments();
$doc = null;
foreach ($docs as $d) {
    if ($d['id'] === $docId) {
        $doc = $d;
        break;
    }
}

if (!$doc) {
    Response::error('not_found', 'Document not found', 404);
}

// Detectar tipo de archivo
$extension = strtolower(pathinfo($doc['path'], PATHINFO_EXTENSION));
$isDownload = isset($_GET['download']) && $_GET['download'] == '1';

if ($isDownload) {
    if (!file_exists($doc['path'])) {
        Response::error('file_not_found', 'File not found on server', 404);
    }

    $mimeTypes = [
        'pdf' => 'application/pdf',
        'txt' => 'text/plain; charset=utf-8',
        'md' => 'text/markdown; charset=utf-8',
    ];
    header('Content-Type: ' . ($mimeTypes[$extension] ?? 'application/octet-stream'));
    header('Content-Disposition: inline; filename="' . basename($doc['path']) . '"');
    header('Content-Length: ' . filesize($doc['path']));
    header('Cache-Control: private, max-age=300');

    readfile($doc['path']);
    exit;
}

// Si es PDF u otro binario
if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
    // Si no es descarga, devolver info JSON
    Response::json([
        'success' => true,
        'document' => [
            'id' => $doc['id'],
            'name' => $doc['name'],
            'size' => $doc['size'],
            'type' => $extension,
            'isBinary' => true,
            'message' => 'This is a PDF file. Documents are indexed and available for Lex assistant queries. To view full content, open it in a new window.'
        ]
    ]);
}

// Leer contenido de archivos de texto
$content = file_get_contents($doc['path']);
if ($content === false) {
    Response::error('read_error', 'Error reading document', 500);
}

Response::json([
    'success' => true,
    'document' => [
        'id' => $doc['id'],
        'name' => $doc['name'],
        'size' => $doc['size'],
        'type' => $extension,
        'isBinary' => false,
        'content' => $content
    ]
]);
