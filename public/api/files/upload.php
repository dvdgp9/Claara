<?php
/**
 * API: Subir archivo al chat
 * POST /api/files/upload.php
 * Body JSON: { data: base64, mime_type: string, name: string, conversation_id?: int }
 * Response: { success: true, file_id: int, url: string }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Repos/ChatFilesRepo.php';

use App\Session;
use App\Response;
use Repos\ChatFilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

// Validar CSRF - puede venir en header o en POST (FormData)
$csrfToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
if (!$csrfToken || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    Response::error('csrf_invalid', 'CSRF token inválido o ausente', 403);
}

$repo = new ChatFilesRepo();

// Limpieza de archivos expirados (lazy cleanup)
$storagePath = ChatFilesRepo::getStoragePath();
$expiredFiles = $repo->getExpired(50);
foreach ($expiredFiles as $expired) {
    $filePath = $storagePath . '/' . $expired['stored_name'];
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
}
$repo->deleteExpired();

// Tipos permitidos (mime => extensión)
$allowedTypes = [
    'application/pdf' => 'pdf',
    'image/png' => 'png',
    'image/jpeg' => 'jpg',
    'image/gif' => 'gif',
    'image/webp' => 'webp',
    'text/csv' => 'csv',
    'application/vnd.ms-excel' => 'xls',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx'
];

// Mapa de extensiones a mime canónico (para cuando el navegador no lo detecta bien)
$extensionToMime = [
    'pdf'  => 'application/pdf',
    'png'  => 'image/png',
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
    'csv'  => 'text/csv',
    'xls'  => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
];

/**
 * Resuelve un mime type válido combinando: tipo declarado, detección por
 * magic bytes (finfo) y extensión del nombre de archivo. Devuelve cadena
 * vacía si no se puede mapear a un tipo permitido.
 */
$resolveAllowedMimeType = function (string $declared, string $filename, ?string $tmpPath) use ($allowedTypes, $extensionToMime): string {
    $declared = strtolower(trim(preg_replace('/;.*$/', '', $declared)));
    if ($declared !== '' && isset($allowedTypes[$declared])) {
        return $declared;
    }

    // Detección por contenido (finfo) si hay archivo físico
    if ($tmpPath && function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $detected = strtolower((string)@finfo_file($finfo, $tmpPath));
            @finfo_close($finfo);
            if ($detected !== '' && isset($allowedTypes[$detected])) {
                return $detected;
            }
        }
    }

    // Fallback por extensión del nombre original
    $ext = strtolower((string)pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext !== '' && isset($extensionToMime[$ext])) {
        return $extensionToMime[$ext];
    }

    return '';
};

// Procesar request - soportar FormData (multipart/form-data) y JSON
$isFormData = isset($_FILES['file']);

if ($isFormData) {
    // FormData: archivo binario directo
    if (empty($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        Response::error('validation_error', 'Error al subir archivo', 400);
    }

    $uploadedFile = $_FILES['file'];
    $binaryData = file_get_contents($uploadedFile['tmp_name']);
    $originalName = $uploadedFile['name'];
    $mimeType = $resolveAllowedMimeType(
        (string)($uploadedFile['type'] ?? ''),
        $originalName,
        $uploadedFile['tmp_name'] ?? null
    );
    $conversationId = isset($_POST['conversation_id']) ? (int)$_POST['conversation_id'] : null;
} else {
    // JSON: archivo en base64
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $base64Data = $body['data'] ?? '';
    $originalName = $body['name'] ?? 'archivo';
    $mimeType = $resolveAllowedMimeType((string)($body['mime_type'] ?? ''), $originalName, null);
    $conversationId = isset($body['conversation_id']) ? (int)$body['conversation_id'] : null;

    if (empty($base64Data)) {
        Response::error('validation_error', 'Datos de archivo requeridos', 400);
    }

    $binaryData = base64_decode($base64Data);
    if ($binaryData === false) {
        Response::error('validation_error', 'Datos base64 inválidos', 400);
    }
}

if ($mimeType === '' || !isset($allowedTypes[$mimeType])) {
    Response::error('validation_error', 'Tipo de archivo no soportado', 400);
}

// Validar tamaño (máx 30MB)
$maxSize = 30 * 1024 * 1024;
$size = strlen($binaryData);
if ($size > $maxSize) {
    Response::error('validation_error', 'El archivo excede el límite de 30MB', 400);
}

// Generar nombre único
$extension = $allowedTypes[$mimeType];
$storedName = bin2hex(random_bytes(16)) . '.' . $extension;

// Crear directorio si no existe
if (!is_dir($storagePath)) {
    mkdir($storagePath, 0755, true);
}

// Guardar archivo
$filePath = $storagePath . '/' . $storedName;
if (file_put_contents($filePath, $binaryData) === false) {
    Response::error('server_error', 'Error al guardar archivo', 500);
}

// Guardar en base de datos
try {
    $fileId = $repo->create([
        'user_id' => (int)$user['id'],
        'conversation_id' => $conversationId,
        'original_name' => $originalName,
        'stored_name' => $storedName,
        'mime_type' => $mimeType,
        'size_bytes' => $size
    ]);
} catch (\Exception $e) {
    // Si falla la DB, borrar archivo físico
    @unlink($filePath);
    Response::error('server_error', 'Error al registrar archivo', 500);
}

Response::json([
    'success' => true,
    'file_id' => $fileId,
    'url' => '/api/files/serve.php?id=' . $fileId,
    'name' => $originalName,
    'mime_type' => $mimeType,
    'size' => $size
]);
