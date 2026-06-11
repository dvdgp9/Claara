<?php
namespace Repos;

use App\DB;
use PDO;

class MessagesRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function listByConversation(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, role, content, file_id, images, model, input_tokens, output_tokens, metadata, created_at FROM messages WHERE conversation_id = ? ORDER BY id ASC');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll() ?: [];
    }

    public function getConversationActivity(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) AS total, COALESCE(MAX(id), 0) AS latest_message_id FROM messages WHERE conversation_id = ?');
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch() ?: [];

        // Locks older than the stale threshold (180s, same as acquireAiLock) count as idle.
        $staleBefore = date('Y-m-d H:i:s', time() - 180);
        $stmtAi = $this->pdo->prepare('SELECT ai_status, ai_started_at FROM conversations WHERE id = ? LIMIT 1');
        $stmtAi->execute([$conversationId]);
        $ai = $stmtAi->fetch() ?: [];
        $aiStatus = (($ai['ai_status'] ?? null) === 'responding' && ($ai['ai_started_at'] ?? '') >= $staleBefore)
            ? 'responding'
            : 'idle';

        return [
            'total' => (int)($row['total'] ?? 0),
            'latest_message_id' => (int)($row['latest_message_id'] ?? 0),
            'ai_status' => $aiStatus,
        ];
    }

    public function create(int $conversationId, ?int $userId, string $role, string $content, ?string $model = null, ?int $inputTokens = null, ?int $outputTokens = null, ?int $fileId = null, ?array $images = null, ?array $metadata = null): int
    {
        $now = date('Y-m-d H:i:s');
        $imagesJson = $images ? json_encode($images) : null;
        $metadataJson = ($metadata !== null && $metadata !== []) ? json_encode($metadata) : null;
        $stmt = $this->pdo->prepare('INSERT INTO messages (conversation_id, user_id, role, content, file_id, images, model, input_tokens, output_tokens, metadata, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$conversationId, $userId, $role, $content, $fileId, $imagesJson, $model, $inputTokens, $outputTokens, $metadataJson, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Borra las imágenes almacenadas en mensajes más antiguas que $days días.
     * Esto evita retener data URLs en la BD más allá del SLA definido.
     */
    public function purgeImagesOlderThan(int $days): void
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET images = NULL WHERE images IS NOT NULL AND created_at < (NOW() - INTERVAL ? DAY)');
        $stmt->execute([$days]);
    }

    /**
     * Actualiza el contenido de un mensaje (para regeneración parcial)
     */
    public function updateContent(int $messageId, string $content): void
    {
        $stmt = $this->pdo->prepare('UPDATE messages SET content = ? WHERE id = ?');
        $stmt->execute([$content, $messageId]);
    }
}
