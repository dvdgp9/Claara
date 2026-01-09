<?php
namespace Repos;

use App\DB;
use PDO;

class SharingRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    // =========================================================================
    // CONVERSATION SHARES
    // =========================================================================

    /**
     * Lista todos los usuarios con acceso a una conversación (excluyendo al propietario)
     */
    public function listConversationShares(int $conversationId): array
    {
        $sql = "SELECT cs.id, cs.user_id, cs.shared_by_user_id, cs.can_write, cs.created_at,
                       u.first_name, u.last_name, u.email
                FROM conversation_shares cs
                JOIN users u ON u.id = cs.user_id
                WHERE cs.conversation_id = ?
                ORDER BY cs.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Comparte una conversación con un usuario
     */
    public function shareConversation(int $conversationId, int $userId, int $sharedByUserId, bool $canWrite = true): int
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO conversation_shares (conversation_id, user_id, shared_by_user_id, can_write, created_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE can_write = VALUES(can_write)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId, $userId, $sharedByUserId, $canWrite ? 1 : 0, $now]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Elimina el acceso de un usuario a una conversación
     */
    public function unshareConversation(int $conversationId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM conversation_shares WHERE conversation_id = ? AND user_id = ?');
        $stmt->execute([$conversationId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si un usuario tiene acceso a una conversación (propietario o compartida)
     * Retorna: null (sin acceso), 'owner', 'editor', 'viewer'
     */
    public function getConversationAccess(int $conversationId, int $userId): ?string
    {
        // Primero verificar si es propietario
        $stmt = $this->pdo->prepare('SELECT user_id FROM conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        
        if (!$conv) {
            return null;
        }
        
        if ((int)$conv['user_id'] === $userId) {
            return 'owner';
        }
        
        // Verificar si tiene acceso compartido directo
        $stmt = $this->pdo->prepare('SELECT can_write FROM conversation_shares WHERE conversation_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$conversationId, $userId]);
        $share = $stmt->fetch();
        
        if ($share) {
            return $share['can_write'] ? 'editor' : 'viewer';
        }
        
        // Verificar acceso heredado por carpeta
        $stmt = $this->pdo->prepare('SELECT folder_id FROM conversations WHERE id = ? LIMIT 1');
        $stmt->execute([$conversationId]);
        $conv = $stmt->fetch();
        
        if ($conv && $conv['folder_id']) {
            $folderAccess = $this->getFolderAccess((int)$conv['folder_id'], $userId);
            if ($folderAccess === 'owner' || $folderAccess === 'editor') {
                return 'editor';
            } elseif ($folderAccess === 'viewer') {
                return 'viewer';
            }
        }
        
        return null;
    }

    /**
     * Verifica si un usuario puede escribir en una conversación
     */
    public function canWriteConversation(int $conversationId, int $userId): bool
    {
        $access = $this->getConversationAccess($conversationId, $userId);
        return $access === 'owner' || $access === 'editor';
    }

    /**
     * Lista conversaciones compartidas con un usuario (donde no es propietario)
     */
    public function listSharedConversations(int $userId): array
    {
        $sql = "SELECT c.id, c.title, c.updated_at, c.folder_id,
                       cs.can_write, cs.created_at as shared_at,
                       owner.first_name as owner_first_name, owner.last_name as owner_last_name
                FROM conversation_shares cs
                JOIN conversations c ON c.id = cs.conversation_id
                JOIN users owner ON owner.id = c.user_id
                WHERE cs.user_id = ?
                ORDER BY c.updated_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // FOLDER SHARES
    // =========================================================================

    /**
     * Lista todos los usuarios con acceso a una carpeta
     */
    public function listFolderShares(int $folderId): array
    {
        $sql = "SELECT fs.id, fs.user_id, fs.shared_by_user_id, fs.can_write, fs.created_at,
                       u.first_name, u.last_name, u.email
                FROM folder_shares fs
                JOIN users u ON u.id = fs.user_id
                WHERE fs.folder_id = ?
                ORDER BY fs.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$folderId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Comparte una carpeta con un usuario
     */
    public function shareFolder(int $folderId, int $userId, int $sharedByUserId, bool $canWrite = true): int
    {
        $now = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO folder_shares (folder_id, user_id, shared_by_user_id, can_write, created_at)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE can_write = VALUES(can_write)";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$folderId, $userId, $sharedByUserId, $canWrite ? 1 : 0, $now]);
        
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Elimina el acceso de un usuario a una carpeta
     */
    public function unshareFolder(int $folderId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM folder_shares WHERE folder_id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Verifica si un usuario tiene acceso a una carpeta (propietario o compartida)
     */
    public function getFolderAccess(int $folderId, int $userId): ?string
    {
        // Verificar si es propietario
        $stmt = $this->pdo->prepare('SELECT user_id FROM folders WHERE id = ? LIMIT 1');
        $stmt->execute([$folderId]);
        $folder = $stmt->fetch();
        
        if (!$folder) {
            return null;
        }
        
        if ((int)$folder['user_id'] === $userId) {
            return 'owner';
        }
        
        // Verificar si tiene acceso compartido
        $stmt = $this->pdo->prepare('SELECT can_write FROM folder_shares WHERE folder_id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$folderId, $userId]);
        $share = $stmt->fetch();
        
        if ($share) {
            return $share['can_write'] ? 'editor' : 'viewer';
        }
        
        return null;
    }

    /**
     * Lista carpetas compartidas con un usuario
     */
    public function listSharedFolders(int $userId): array
    {
        $sql = "SELECT f.id, f.name, f.created_at,
                       fs.can_write, fs.created_at as shared_at,
                       owner.first_name as owner_first_name, owner.last_name as owner_last_name
                FROM folder_shares fs
                JOIN folders f ON f.id = fs.folder_id
                JOIN users owner ON owner.id = f.user_id
                WHERE fs.user_id = ?
                ORDER BY f.name ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Busca usuarios por email o nombre para autocompletado (excluyendo un usuario)
     */
    public function searchUsers(string $query, int $excludeUserId, int $limit = 10): array
    {
        $search = '%' . trim($query) . '%';
        $sql = "SELECT id, first_name, last_name, email
                FROM users
                WHERE id != ?
                  AND status = 'active'
                  AND (email LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR CONCAT(first_name, ' ', last_name) LIKE ?)
                ORDER BY first_name, last_name
                LIMIT ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$excludeUserId, $search, $search, $search, $search, $limit]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Obtiene el propietario de una conversación
     */
    public function getConversationOwner(int $conversationId): ?array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM conversations c
                JOIN users u ON u.id = c.user_id
                WHERE c.id = ?
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$conversationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene el propietario de una carpeta
     */
    public function getFolderOwner(int $folderId): ?array
    {
        $sql = "SELECT u.id, u.first_name, u.last_name, u.email
                FROM folders f
                JOIN users u ON u.id = f.user_id
                WHERE f.id = ?
                LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$folderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
