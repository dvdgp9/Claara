<?php
/**
 * API: Transcribir audio a texto
 * POST /api/gestures/transcribe.php
 * 
 * Flujo:
 * 1. Recibe audio en base64 o file_id
 * 2. Valida formato y tamaño
 * 3. Transcribe usando Gemini via OpenRouter
 * 4. Guarda en historial de gestos
 * 5. Devuelve transcripción
 */

// Aumentar límites de PHP para audios grandes
ini_set('memory_limit', '512M');
ini_set('max_execution_time', '600');

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'fatal_error',
                'message' => 'Error del servidor: ' . $error['message'],
                'debug' => $error
            ]
        ]);
    }
});

// Manejador de errores
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Sop\AudioTranscriber;
use Gestures\GestureExecutionsRepo;
use Repos\ChatFilesRepo;
use Repos\UsageLogRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'No autenticado', 401);
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || $csrfHeader !== $csrfSession) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

// Parsear body
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$audioBase64 = $body['audio_base64'] ?? '';
$audioMime = $body['audio_mime'] ?? '';
$audioFilename = $body['audio_filename'] ?? 'audio';
$fileId = isset($body['file_id']) ? (int)$body['file_id'] : null;

// Si viene file_id, cargar el archivo desde storage
if ($fileId && empty($audioBase64)) {
    $filesRepo = new ChatFilesRepo();
    $storedFile = $filesRepo->findByIdAndUser($fileId, (int)$user['id']);
    
    if (!$storedFile) {
        Response::error('file_not_found', 'Archivo no encontrado', 404);
    }
    
    $storagePath = ChatFilesRepo::getStoragePath();
    $filePath = $storagePath . '/' . $storedFile['stored_name'];
    
    if (!file_exists($filePath)) {
        Response::error('file_not_found', 'Archivo no encontrado en storage', 404);
    }
    
    $audioBase64 = base64_encode(file_get_contents($filePath));
    $audioMime = $storedFile['mime_type'];
    $audioFilename = $storedFile['original_name'];
}

// Validar que tenemos audio
if (empty($audioBase64) || empty($audioMime)) {
    Response::error('missing_audio', 'Se requiere audio (base64 + mime) o file_id', 400);
}

// Validar tipos de audio permitidos
$validAudioTypes = [
    'audio/mpeg',
    'audio/mp3',
    'audio/wav',
    'audio/wave',
    'audio/x-wav',
    'audio/mp4',
    'audio/m4a',
    'audio/x-m4a',
    'audio/webm',
    'audio/ogg',
];

if (!in_array($audioMime, $validAudioTypes)) {
    Response::error('invalid_audio_type', 'Tipo de audio no soportado. Usa: mp3, wav, m4a, webm, ogg', 400);
}

// Validar tamaño (max 50MB)
$audioSizeBytes = strlen(base64_decode($audioBase64));
$audioSizeMB = $audioSizeBytes / (1024 * 1024);

if ($audioSizeMB > 50) {
    Response::error('file_too_large', "El audio es demasiado grande (" . round($audioSizeMB, 1) . "MB). Máximo 50MB.", 400);
}

try {
    // Transcribir audio
    $transcriber = new AudioTranscriber();
    $result = $transcriber->transcribe($audioBase64, $audioMime, $audioFilename);
    
    if (!$result['success']) {
        Response::error('transcription_failed', $result['error'], 500);
    }
    
    $transcription = $result['text'];
    $durationEstimate = $result['duration_estimate'] ?? null;
    
    // Generar título automático (primeras palabras de la transcripción)
    $title = mb_substr(preg_replace('/\s+/', ' ', $transcription), 0, 60);
    if (mb_strlen($transcription) > 60) {
        $title .= '...';
    }
    if (empty(trim($title))) {
        $title = 'Transcripción - ' . date('d/m/Y H:i');
    }
    
    // Guardar en historial de gestos
    $repo = new GestureExecutionsRepo();
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'audio-transcriber',
        'title' => $title,
        'input_data' => [
            'filename' => $audioFilename,
            'mime_type' => $audioMime,
            'size_mb' => round($audioSizeMB, 2),
            'duration_estimate' => $durationEstimate
        ],
        'output_content' => $transcription,
        'output_data' => [
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription),
            'duration_estimate' => $durationEstimate
        ],
        'content_type' => 'transcription',
        'business_line' => null,
        'model' => 'google/gemini-3-flash-preview'
    ]);
    
    // Registrar uso
    $usageLog = new UsageLogRepo();
    $usageLog->log((int)$user['id'], 'gesture', 1, ['gesture_type' => 'audio-transcriber']);
    
    // Respuesta exitosa
    Response::json([
        'success' => true,
        'execution_id' => $executionId,
        'title' => $title,
        'transcription' => $transcription,
        'metadata' => [
            'filename' => $audioFilename,
            'duration_estimate' => $durationEstimate,
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription)
        ]
    ]);
    
} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
