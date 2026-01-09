<?php
namespace Repos;

use App\DB;
use PDO;

class PresenceRepo {
    private PDO $pdo;
    
    // Tiempo en segundos después del cual un usuario se considera offline
    private const PRESENCE_TTL = 30;
    
    // Tiempo en segundos después del cual se considera que dejó de escribir
    private const TYPING_TTL = 5;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /**
     * Actualiza o crea el estado de presencia de un usuario en una conversación
     * Llamado periódicamente mientras el usuario tiene la conversación abierta
     */
    public function heartbeat(int $userId, int $conversationId): void
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO presence_states (user_id, conversation_id, is_typing, is_online, updated_at)
                VALUES (?, ?, 0, 1, ?)
                ON DUPLICATE KEY UPDATE is_online = 1, updated_at = VALUES(updated_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $conversationId, $now]);
    }

    /**
     * Marca que un usuario está escribiendo en una conversación
     */
    public function setTyping(int $userId, int $conversationId, bool $isTyping = true): void
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO presence_states (user_id, conversation_id, is_typing, is_online, updated_at)
                VALUES (?, ?, ?, 1, ?)
                ON DUPLICATE KEY UPDATE is_typing = VALUES(is_typing), is_online = 1, updated_at = VALUES(updated_at)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $conversationId, $isTyping ? 1 : 0, $now]);
    }

    /**
     * Marca que un usuario ha dejado una conversación (se desconectó o cambió de vista)
     */
    public function leave(int $userId, int $conversationId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM presence_states WHERE user_id = ? AND conversation_id = ?');
        $stmt->execute([$userId, $conversationId]);
    }

    /**
     * Marca que un usuario ha dejado todas las conversaciones (cerró sesión o pestaña)
     */
    public function leaveAll(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM presence_states WHERE user_id = ?');
        $stmt->execute([$userId]);
    }

    /**
     * Obtiene todos los usuarios presentes en una conversación (excluyendo al solicitante)
     * Solo retorna usuarios activos (updated_at reciente)
     */
    public function getPresence(int $conversationId, int $excludeUserId = 0): array
    {
        $ttlSeconds = self::PRESENCE_TTL;
        $typingTtl = self::TYPING_TTL;
        
        $sql = "SELECT ps.user_id, ps.is_typing, ps.is_online, ps.updated_at,
                       u.first_name, u.last_name,
                       CASE WHEN ps.is_typing = 1 AND ps.updated_at >= NOW() - INTERVAL ? SECOND THEN 1 ELSE 0 END as currently_typing
                FROM presence_states ps
                JOIN users u ON u.id = ps.user_id
                WHERE ps.conversation_id = ?
                  AND ps.user_id != ?
                  AND ps.updated_at >= NOW() - INTERVAL ? SECOND
                ORDER BY u.first_name, u.last_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$typingTtl, $conversationId, $excludeUserId, $ttlSeconds]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Verifica si alguien está escribiendo en una conversación (excluyendo al solicitante)
     */
    public function isAnyoneTyping(int $conversationId, int $excludeUserId = 0): ?array
    {
        $typingTtl = self::TYPING_TTL;
        
        $sql = "SELECT ps.user_id, u.first_name, u.last_name
                FROM presence_states ps
                JOIN users u ON u.id = ps.user_id
                WHERE ps.conversation_id = ?
                  AND ps.user_id != ?
                  AND ps.is_typing = 1
                  AND ps.updated_at >= NOW() - INTERVAL ? SECOND
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $excludeUserId, $typingTtl]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Cuenta usuarios online en una conversación
     */
    public function countOnline(int $conversationId): int
    {
        $ttlSeconds = self::PRESENCE_TTL;
        
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) as total FROM presence_states 
             WHERE conversation_id = ? AND updated_at >= NOW() - INTERVAL ? SECOND"
        );
        $stmt->execute([$conversationId, $ttlSeconds]);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Limpia registros de presencia obsoletos (llamar periódicamente)
     * Retorna el número de registros eliminados
     */
    public function cleanupStale(): int
    {
        $ttlSeconds = self::PRESENCE_TTL * 2; // Doble del TTL para limpiar
        
        $stmt = $this->pdo->prepare('DELETE FROM presence_states WHERE updated_at < NOW() - INTERVAL ? SECOND');
        $stmt->execute([$ttlSeconds]);
        return $stmt->rowCount();
    }

    /**
     * Obtiene el estado de presencia de un usuario específico en una conversación
     */
    public function getUserPresence(int $userId, int $conversationId): ?array
    {
        $ttlSeconds = self::PRESENCE_TTL;
        
        $sql = "SELECT user_id, is_typing, is_online, updated_at
                FROM presence_states
                WHERE user_id = ? AND conversation_id = ?
                  AND updated_at >= NOW() - INTERVAL ? SECOND
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId, $conversationId, $ttlSeconds]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene un resumen de presencia para múltiples conversaciones
     * Útil para mostrar indicadores en la sidebar
     */
    public function getPresenceSummary(array $conversationIds, int $excludeUserId = 0): array
    {
        if (empty($conversationIds)) {
            return [];
        }
        
        $ttlSeconds = self::PRESENCE_TTL;
        $placeholders = str_repeat('?,', count($conversationIds) - 1) . '?';
        
        $sql = "SELECT conversation_id, COUNT(*) as online_count
                FROM presence_states
                WHERE conversation_id IN ($placeholders)
                  AND user_id != ?
                  AND updated_at >= NOW() - INTERVAL ? SECOND
                GROUP BY conversation_id";
        
        $params = array_merge($conversationIds, [$excludeUserId, $ttlSeconds]);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        $result = [];
        while ($row = $stmt->fetch()) {
            $result[(int)$row['conversation_id']] = (int)$row['online_count'];
        }
        
        return $result;
    }
}
