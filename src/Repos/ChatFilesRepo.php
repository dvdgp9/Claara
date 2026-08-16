<?php

declare(strict_types=1);

namespace Repos;

use App\DB;
use App\Storage;
use PDO;

/**
 * Repositorio para gestionar archivos subidos al chat
 */
class ChatFilesRepo
{
    private PDO $pdo;
    private int $expirationDays = 5;

    public function __construct()
    {
        $this->pdo = DB::pdo();
    }

    /**
     * Crea un registro de archivo
     */
    public function create(array $data): int
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->expirationDays} days"));
        
        $sql = "INSERT INTO chat_files 
                (user_id, conversation_id, message_id, original_name, stored_name, mime_type, size_bytes, expires_at)
                VALUES (:user_id, :conversation_id, :message_id, :original_name, :stored_name, :mime_type, :size_bytes, :expires_at)";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'conversation_id' => $data['conversation_id'] ?? null,
            'message_id' => $data['message_id'] ?? null,
            'original_name' => $data['original_name'],
            'stored_name' => $data['stored_name'],
            'mime_type' => $data['mime_type'],
            'size_bytes' => $data['size_bytes'],
            'expires_at' => $expiresAt
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtiene un archivo por ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM chat_files WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ?: null;
    }

    /**
     * Obtiene un archivo por ID verificando que pertenezca al usuario
     */
    public function findByIdAndUser(int $id, int $userId): ?array
    {
        $sql = "SELECT * FROM chat_files WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ?: null;
    }

    /**
     * Actualiza el message_id de un archivo
     */
    public function updateMessageId(int $fileId, int $messageId): bool
    {
        $sql = "UPDATE chat_files SET message_id = :message_id WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['message_id' => $messageId, 'id' => $fileId]);
    }

    /**
     * Actualiza el conversation_id de un archivo
     */
    public function updateConversationId(int $fileId, int $conversationId): bool
    {
        $sql = "UPDATE chat_files SET conversation_id = :conversation_id WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['conversation_id' => $conversationId, 'id' => $fileId]);
    }

    /**
     * Obtiene archivos expirados para limpieza
     */
    public function getExpired(int $limit = 100): array
    {
        $sql = "SELECT id, stored_name FROM chat_files WHERE expires_at < NOW() LIMIT :limit";
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina archivos expirados de la base de datos
     */
    public function deleteExpired(): int
    {
        $sql = "DELETE FROM chat_files WHERE expires_at < NOW()";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        
        return $stmt->rowCount();
    }

    /**
     * Elimina un archivo por ID
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM chat_files WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Obtiene archivos de una conversación
     */
    public function listByConversation(int $conversationId): array
    {
        $sql = "SELECT * FROM chat_files WHERE conversation_id = :conversation_id ORDER BY created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['conversation_id' => $conversationId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene el directorio de almacenamiento
     */
    public static function getStoragePath(): string
    {
        return Storage::path('chat-files');
    }
}
