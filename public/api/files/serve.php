<?php
/**
 * API: Servir archivo del chat
 * GET /api/files/serve.php?id=X
 * Devuelve el archivo con headers apropiados
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Repos/ChatFilesRepo.php';
require_once __DIR__ . '/../../../src/Repos/ConversationAccessRepo.php';

use App\Session;
use Repos\ChatFilesRepo;
use Repos\ConversationAccessRepo;

$user = Session::user();
if (!$user) {
    http_response_code(401);
    exit('Unauthorized');
}

$fileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($fileId <= 0) {
    http_response_code(400);
    exit('Invalid file ID');
}

$repo = new ChatFilesRepo();
$file = $repo->findById($fileId);

if (!$file) {
    http_response_code(404);
    exit('File not found');
}

$ownsFile = (int)$file['user_id'] === (int)$user['id'];
$conversationId = isset($file['conversation_id']) ? (int)$file['conversation_id'] : 0;
$canViewConversation = $conversationId > 0 && (new ConversationAccessRepo())->canView($conversationId, $user);

if (!$ownsFile && !$canViewConversation) {
    http_response_code(404);
    exit('File not found');
}

// Verificar si ha expirado
if (strtotime($file['expires_at']) < time()) {
    http_response_code(410);
    exit('File expired');
}

$storagePath = ChatFilesRepo::getStoragePath();
$filePath = $storagePath . '/' . $file['stored_name'];

if (!file_exists($filePath)) {
    http_response_code(404);
    exit('File not found on disk');
}

// Headers para servir el archivo
header('Content-Type: ' . $file['mime_type']);
header('Content-Length: ' . $file['size_bytes']);
header('Content-Disposition: inline; filename="' . addslashes($file['original_name']) . '"');
header('Cache-Control: private, max-age=86400');
header('X-Content-Type-Options: nosniff');

// Enviar archivo
readfile($filePath);
exit;
