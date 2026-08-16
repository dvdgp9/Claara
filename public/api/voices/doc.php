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
use I18n\I18n;
use Voices\VoiceContextBuilder;
use Voices\VoiceAccessResolver;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', I18n::translate('auth.error.unauthorized'), 401);
}

$voiceId = $_GET['voice_id'] ?? '';
$docId = $_GET['doc_id'] ?? '';

if (!$voiceId) {
    Response::error('missing_voice', I18n::translate('voice_api.voice_required'), 400);
}
if (!$docId) {
    Response::error('missing_doc', I18n::translate('voice_api.document_required'), 400);
}
$builder = new VoiceContextBuilder($voiceId);

if (!$builder->voiceExists()) {
    Response::error('invalid_voice', I18n::translate('voice_api.voice_not_found'), 404);
}

$voice = $builder->getVoiceInfo() ?? ['slug' => $voiceId];
$resolver = new VoiceAccessResolver();
if (!$resolver->hasVoiceAccess((int)$user['id'], $voice)) {
    Response::error('forbidden', I18n::translate('voice_api.forbidden'), 403);
}
$allowedFolderIds = $resolver->hasFullAccess((int)$user['id'], $voice)
    ? null
    : $resolver->resolveAccessibleFolderIds((int)$user['id'], $voice);

// Buscar el documento solo entre los accesibles para este usuario. Un documento
// fuera de sus carpetas no aparece y devuelve 404 (sin distinguir de inexistente).
$docs = $builder->listDocuments($allowedFolderIds);
$doc = null;
foreach ($docs as $d) {
    if ($d['id'] === $docId) {
        $doc = $d;
        break;
    }
}

if (!$doc) {
    Response::error('not_found', I18n::translate('voice_api.document_not_found'), 404);
}

// Detectar tipo de archivo
$extension = strtolower(pathinfo($doc['path'], PATHINFO_EXTENSION));
$isDownload = isset($_GET['download']) && $_GET['download'] == '1';

if ($isDownload) {
    if (!file_exists($doc['path'])) {
        Response::error('file_not_found', I18n::translate('voice_api.file_not_found'), 404);
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
            'message' => I18n::translate('voice_api.binary_document')
        ]
    ]);
}

// Leer contenido de archivos de texto
$content = file_get_contents($doc['path']);
if ($content === false) {
    Response::error('read_error', I18n::translate('voice_api.read_error'), 500);
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
