<?php
/**
 * Server-Sent Events (SSE) endpoint para notificaciones en tiempo real
 * GET /api/realtime/stream.php?conversation_id=123
 * 
 * Eventos emitidos:
 * - presence: { users: [...] } - Lista de usuarios online en la conversación
 * - typing: { user_id, first_name, last_name, is_typing } - Alguien empezó/dejó de escribir
 * - message: { message_id, ... } - Nuevo mensaje añadido por otro usuario
 * - ping: {} - Keep-alive cada 15 segundos
 */
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/SharingRepo.php';
require_once __DIR__ . '/../../../src/Repos/PresenceRepo.php';
require_once __DIR__ . '/../../../src/Repos/MessagesRepo.php';

use Auth\AuthService;
use Repos\SharingRepo;
use Repos\PresenceRepo;
use Repos\MessagesRepo;

// Configuración SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Nginx

// Desactivar buffer de salida
if (ob_get_level()) ob_end_clean();

// Autenticación
$user = AuthService::requireAuth();
$userId = (int)$user['id'];

$conversationId = isset($_GET['conversation_id']) ? (int)$_GET['conversation_id'] : 0;

if ($conversationId <= 0) {
    echo "event: error\n";
    echo "data: {\"message\": \"conversation_id requerido\"}\n\n";
    flush();
    exit;
}

// Verificar acceso a la conversación
$sharingRepo = new SharingRepo();
$access = $sharingRepo->getConversationAccess($conversationId, $userId);

if (!$access) {
    echo "event: error\n";
    echo "data: {\"message\": \"Sin acceso a esta conversación\"}\n\n";
    flush();
    exit;
}

$presenceRepo = new PresenceRepo();
$messagesRepo = new MessagesRepo();

// Registrar presencia inicial
$presenceRepo->heartbeat($userId, $conversationId);

// Enviar evento de conexión exitosa
echo "event: connected\n";
echo "data: " . json_encode([
    'user_id' => $userId,
    'conversation_id' => $conversationId,
    'access' => $access
]) . "\n\n";
flush();

// Estado para detectar cambios
$lastPresenceHash = '';
$lastTypingUser = null;
$lastMessageId = 0;
$iteration = 0;

// Obtener último mensaje conocido
$messages = $messagesRepo->listByConversation($conversationId);
if (!empty($messages)) {
    $lastMessageId = (int)$messages[count($messages) - 1]['id'];
}

// Bucle principal SSE
while (true) {
    // Verificar si la conexión sigue activa
    if (connection_aborted()) {
        $presenceRepo->leave($userId, $conversationId);
        break;
    }
    
    // Heartbeat cada iteración (mantener presencia activa)
    $presenceRepo->heartbeat($userId, $conversationId);
    
    // 1. Obtener usuarios presentes
    $presence = $presenceRepo->getPresence($conversationId, $userId);
    $presenceHash = md5(json_encode($presence));
    
    if ($presenceHash !== $lastPresenceHash) {
        $lastPresenceHash = $presenceHash;
        echo "event: presence\n";
        echo "data: " . json_encode([
            'users' => array_map(function($p) {
                return [
                    'user_id' => (int)$p['user_id'],
                    'first_name' => $p['first_name'],
                    'last_name' => $p['last_name'],
                    'is_typing' => (bool)$p['currently_typing']
                ];
            }, $presence)
        ]) . "\n\n";
        flush();
    }
    
    // 2. Detectar si alguien está escribiendo
    $typingUser = $presenceRepo->isAnyoneTyping($conversationId, $userId);
    $typingUserId = $typingUser ? (int)$typingUser['user_id'] : null;
    
    if ($typingUserId !== $lastTypingUser) {
        $lastTypingUser = $typingUserId;
        echo "event: typing\n";
        echo "data: " . json_encode([
            'is_typing' => $typingUser !== null,
            'user' => $typingUser ? [
                'user_id' => (int)$typingUser['user_id'],
                'first_name' => $typingUser['first_name'],
                'last_name' => $typingUser['last_name']
            ] : null
        ]) . "\n\n";
        flush();
    }
    
    // 3. Detectar nuevos mensajes
    $messages = $messagesRepo->listByConversation($conversationId);
    if (!empty($messages)) {
        $latestId = (int)$messages[count($messages) - 1]['id'];
        if ($latestId > $lastMessageId) {
            // Hay nuevos mensajes
            foreach ($messages as $msg) {
                if ((int)$msg['id'] > $lastMessageId) {
                    echo "event: message\n";
                    echo "data: " . json_encode([
                        'id' => (int)$msg['id'],
                        'role' => $msg['role'],
                        'content' => $msg['content'],
                        'user_id' => $msg['user_id'] ? (int)$msg['user_id'] : null,
                        'created_at' => $msg['created_at']
                    ]) . "\n\n";
                    flush();
                }
            }
            $lastMessageId = $latestId;
        }
    }
    
    // 4. Ping keep-alive cada 15 iteraciones (~15 segundos)
    if ($iteration % 15 === 0) {
        echo "event: ping\n";
        echo "data: {\"time\": " . time() . "}\n\n";
        flush();
    }
    
    // Limpiar presencias obsoletas ocasionalmente
    if ($iteration % 60 === 0) {
        $presenceRepo->cleanupStale();
    }
    
    $iteration++;
    
    // Esperar 1 segundo antes de la siguiente iteración
    sleep(1);
}
