<?php
/**
 * API: Audio transcription
 * POST /api/gestures/transcribe.php
 *
 * Primary flow:
 * - multipart/form-data with field "audio_file"
 * - creates async background job "audio-transcribe"
 *
 * Compatibility flow:
 * - JSON body with "audio_base64" + "audio_mime" or "file_id"
 * - synchronous transcription (legacy)
 */

ini_set('memory_limit', '512M');
ini_set('max_execution_time', '600');

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log("Fatal error: {$error['message']} in {$error['file']}:{$error['line']}");
        if (!headers_sent()) {
            header('Content-Type: application/json');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'fatal_error',
                'message' => 'Internal server error',
            ],
        ]);
    }
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Response;
use App\Session;
use Gestures\GestureExecutionsRepo;
use Jobs\BackgroundJobsRepo;
use Repos\ChatFilesRepo;
use Repos\UsageLogRepo;
use Sop\AudioTranscriber;

const MAX_AUDIO_BYTES = 52428800; // 50 MB
const VALID_AUDIO_MIME_TYPES = [
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

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Invalid CSRF token', 403);
}

// Primary flow: multipart upload.
if (!empty($_FILES['audio_file'])) {
    $file = $_FILES['audio_file'];
    if (!is_array($file)) {
        Response::error('invalid_upload', 'Invalid upload payload', 400);
    }

    $uploadError = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($uploadError !== UPLOAD_ERR_OK) {
        Response::error('upload_error', mapUploadError($uploadError), 400);
    }

    $tmpName = (string)($file['tmp_name'] ?? '');
    $originalName = (string)($file['name'] ?? 'audio');
    $sizeBytes = (int)($file['size'] ?? 0);
    $mimeType = normalizeMime((string)($file['type'] ?? ''));

    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        Response::error('upload_error', 'Uploaded file is not valid', 400);
    }

    if ($sizeBytes <= 0) {
        Response::error('empty_file', 'Uploaded file is empty', 400);
    }

    if ($sizeBytes > MAX_AUDIO_BYTES) {
        Response::error('file_too_large', 'Audio file exceeds 50MB limit', 400);
    }

    if (!in_array($mimeType, VALID_AUDIO_MIME_TYPES, true)) {
        Response::error('invalid_audio_type', 'Unsupported audio type. Use: mp3, wav, m4a, webm, ogg', 400);
    }

    $storageDir = dirname(__DIR__, 3) . '/storage/transcribe-jobs';
    if (!is_dir($storageDir) && !@mkdir($storageDir, 0775, true)) {
        Response::error('storage_error', 'Could not create transcription storage directory', 500);
    }

    $safeBase = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($originalName, PATHINFO_FILENAME));
    $ext = strtolower((string)pathinfo($originalName, PATHINFO_EXTENSION));
    if ($safeBase === '' || $safeBase === null) {
        $safeBase = 'audio';
    }
    if ($ext === '') {
        $ext = extensionFromMime($mimeType);
    }

    $storedName = sprintf(
        'transcribe_%d_%s_%s.%s',
        (int)$user['id'],
        date('Ymd_His'),
        bin2hex(random_bytes(6)),
        $ext
    );
    $storedPath = $storageDir . '/' . $storedName;

    if (!move_uploaded_file($tmpName, $storedPath)) {
        Response::error('storage_error', 'Could not store uploaded audio file', 500);
    }

    $jobsRepo = new BackgroundJobsRepo();
    $jobId = $jobsRepo->create([
        'user_id' => (int)$user['id'],
        'job_type' => 'audio-transcribe',
        'input_data' => [
            'file_path' => $storedPath,
            'audio_mime' => $mimeType,
            'audio_filename' => $originalName,
            'size_mb' => round($sizeBytes / (1024 * 1024), 2),
            'created_at' => date('c'),
        ],
    ]);

    Response::json([
        'success' => true,
        'async' => true,
        'job_id' => $jobId,
        'message' => 'Transcription job queued',
    ]);
}

// Legacy flow: JSON base64 / file_id, synchronous.
$rawInput = file_get_contents('php://input');
$body = json_decode($rawInput, true);
unset($rawInput);
if ($body === null && json_last_error() !== JSON_ERROR_NONE) {
    Response::error('invalid_json', 'Invalid JSON: ' . json_last_error_msg(), 400);
}

$audioBase64 = (string)($body['audio_base64'] ?? '');
$audioMime = normalizeMime((string)($body['audio_mime'] ?? ''));
$audioFilename = (string)($body['audio_filename'] ?? 'audio');
$fileId = isset($body['file_id']) ? (int)$body['file_id'] : null;

if ($fileId && $audioBase64 === '') {
    $filesRepo = new ChatFilesRepo();
    $storedFile = $filesRepo->findByIdAndUser($fileId, (int)$user['id']);
    if (!$storedFile) {
        Response::error('file_not_found', 'File not found', 404);
    }

    $storagePath = ChatFilesRepo::getStoragePath();
    $filePath = $storagePath . '/' . $storedFile['stored_name'];
    if (!file_exists($filePath)) {
        Response::error('file_not_found', 'Stored file not found', 404);
    }

    $audioBase64 = base64_encode((string)file_get_contents($filePath));
    $audioMime = normalizeMime((string)$storedFile['mime_type']);
    $audioFilename = (string)$storedFile['original_name'];
}

if ($audioBase64 === '' || $audioMime === '') {
    Response::error('missing_audio', 'Missing audio payload (audio_base64 + audio_mime) or file_id', 400);
}

if (!in_array($audioMime, VALID_AUDIO_MIME_TYPES, true)) {
    Response::error('invalid_audio_type', 'Unsupported audio type. Use: mp3, wav, m4a, webm, ogg', 400);
}

$decoded = base64_decode($audioBase64, true);
if ($decoded === false) {
    Response::error('invalid_audio', 'audio_base64 is not valid base64', 400);
}
$audioSizeBytes = strlen($decoded);
unset($decoded);

if ($audioSizeBytes > MAX_AUDIO_BYTES) {
    Response::error('file_too_large', 'Audio file exceeds 50MB limit', 400);
}

try {
    $transcriber = new AudioTranscriber();
    $result = $transcriber->transcribe($audioBase64, $audioMime, $audioFilename);

    if (!$result['success']) {
        Response::error('transcription_failed', (string)$result['error'], 500);
    }

    $transcription = (string)$result['text'];
    $durationEstimate = $result['duration_estimate'] ?? null;

    $title = mb_substr(preg_replace('/\s+/', ' ', $transcription), 0, 60);
    if (mb_strlen($transcription) > 60) {
        $title .= '...';
    }
    if (trim($title) === '') {
        $title = 'Transcription - ' . date('Y-m-d H:i');
    }

    $repo = new GestureExecutionsRepo();
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'audio-transcriber',
        'title' => $title,
        'input_data' => [
            'filename' => $audioFilename,
            'mime_type' => $audioMime,
            'size_mb' => round($audioSizeBytes / (1024 * 1024), 2),
            'duration_estimate' => $durationEstimate,
            'legacy_sync' => true,
        ],
        'output_content' => $transcription,
        'output_data' => [
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription),
            'duration_estimate' => $durationEstimate,
        ],
        'content_type' => 'transcription',
        'business_line' => null,
        'model' => 'gemini-2.5-flash',
    ]);

    $usageLog = new UsageLogRepo();
    $usageLog->log((int)$user['id'], 'gesture', 1, ['gesture_type' => 'audio-transcriber']);

    Response::json([
        'success' => true,
        'execution_id' => $executionId,
        'title' => $title,
        'transcription' => $transcription,
        'metadata' => [
            'filename' => $audioFilename,
            'duration_estimate' => $durationEstimate,
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription),
        ],
    ]);
} catch (\Exception $e) {
    Response::serverError('server_error', $e, 'Transcription failed');
}

function normalizeMime(string $mime): string
{
    $mime = strtolower(trim($mime));
    if ($mime === 'audio/m4a') {
        return 'audio/mp4';
    }
    if ($mime === 'audio/mpga') {
        return 'audio/mpeg';
    }
    return $mime;
}

function extensionFromMime(string $mime): string
{
    return match ($mime) {
        'audio/mpeg', 'audio/mp3' => 'mp3',
        'audio/wav', 'audio/wave', 'audio/x-wav' => 'wav',
        'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
        'audio/webm' => 'webm',
        'audio/ogg' => 'ogg',
        default => 'audio',
    };
}

function mapUploadError(int $code): string
{
    return match ($code) {
        UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Uploaded file is too large for server limits',
        UPLOAD_ERR_PARTIAL => 'Uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No audio file uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Temporary upload directory is missing',
        UPLOAD_ERR_CANT_WRITE => 'Could not write uploaded file to disk',
        UPLOAD_ERR_EXTENSION => 'Upload blocked by a PHP extension',
        default => 'Unknown upload error',
    };
}
