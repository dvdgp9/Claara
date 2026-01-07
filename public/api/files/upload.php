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

Session::requireCsrf();

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

// Procesar request
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$base64Data = $body['data'] ?? '';
$mimeType = $body['mime_type'] ?? '';
$originalName = $body['name'] ?? 'archivo';
$conversationId = isset($body['conversation_id']) ? (int)$body['conversation_id'] : null;

if (empty($base64Data) || empty($mimeType)) {
    Response::error('validation_error', 'Datos de archivo requeridos', 400);
}

// Validar tipo MIME
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

if (!isset($allowedTypes[$mimeType])) {
    Response::error('validation_error', 'Tipo de archivo no soportado', 400);
}

// Decodificar base64
$binaryData = base64_decode($base64Data);
if ($binaryData === false) {
    Response::error('validation_error', 'Datos base64 inválidos', 400);
}

// Validar tamaño (máx 10MB)
$maxSize = 10 * 1024 * 1024;
$size = strlen($binaryData);
if ($size > $maxSize) {
    Response::error('validation_error', 'El archivo excede el límite de 10MB', 400);
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
