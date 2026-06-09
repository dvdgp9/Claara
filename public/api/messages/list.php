<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationAccessRepo.php';
require_once __DIR__ . '/../../../src/Repos/MessagesRepo.php';
require_once __DIR__ . '/../../../src/Repos/ChatFilesRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\ConversationAccessRepo;
use Repos\MessagesRepo;
use Repos\ChatFilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;
if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id is required', 400);
}

$accessRepo = new ConversationAccessRepo();
$access = $accessRepo->getAccess($conversationId, $user);
if (!$access || empty($access['can_view'])) {
    Response::error('not_found', 'Conversation not found', 404);
}

$msgs = new MessagesRepo();
$filesRepo = new ChatFilesRepo();
$items = $msgs->listByConversation($conversationId);

// Enriquecer mensajes con información de archivos e imágenes
foreach ($items as &$item) {
    if (!empty($item['file_id'])) {
        $file = $filesRepo->findById((int)$item['file_id']);
        if ($file) {
            $item['file'] = [
                'id' => $file['id'],
                'name' => $file['original_name'],
                'mime_type' => $file['mime_type'],
                'url' => '/api/files/serve.php?id=' . $file['id'],
                'expired' => strtotime($file['expires_at']) < time()
            ];
        }
    }
    
    // Decodificar images JSON si existe
    if (!empty($item['images'])) {
        $item['images'] = json_decode($item['images'], true);
    }

    // Exponer la voz que generó el mensaje (para el botón de reportar)
    $item['voice_slug'] = null;
    if (!empty($item['metadata'])) {
        $meta = json_decode($item['metadata'], true);
        if (is_array($meta) && !empty($meta['voice_slug'])) {
            $item['voice_slug'] = $meta['voice_slug'];
        }
    }
    unset($item['metadata']);
}

Response::json([
    'items' => $items,
    'access' => [
        'permission' => $access['permission'],
        'can_view' => (bool)$access['can_view'],
        'can_chat' => (bool)$access['can_chat'],
        'can_manage' => (bool)$access['can_manage'],
        'is_shared' => (bool)($access['is_shared'] ?? false),
        'share_count' => (int)($access['share_count'] ?? 0),
        'share' => $access['share'],
        'conversation' => $access['conversation'],
    ],
]);
