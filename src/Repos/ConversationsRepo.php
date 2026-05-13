<?php
namespace Repos;

use App\DB;
use PDO;

class ConversationsRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function listByUser(int $userId, string $sort = 'updated_at', ?int $folderId = null): array
    {
        $allowed = ['updated_at', 'created_at', 'title', 'favorite'];
        $orderBy = in_array($sort, $allowed) ? $sort : 'updated_at';
        
        // Filtro opcional por carpeta
        $folderCondition = '';
        $params = [$userId];
        if ($folderId !== null) {
            if ($folderId === 0) {
                // Carpeta raíz (sin carpeta)
                $folderCondition = ' AND folder_id IS NULL';
            } else {
                $folderCondition = ' AND folder_id = ?';
                $params[] = $folderId;
            }
        }
        
        if ($orderBy === 'favorite') {
            $sql = "SELECT id, title, status, is_favorite, folder_id, created_at, updated_at FROM conversations WHERE user_id = ?" . $folderCondition . " ORDER BY is_favorite DESC, updated_at DESC";
        } else {
            $direction = $sort === 'title' ? 'ASC' : 'DESC';
            $sql = "SELECT id, title, status, is_favorite, folder_id, created_at, updated_at FROM conversations WHERE user_id = ?" . $folderCondition . " ORDER BY {$orderBy} {$direction}";
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function findByIdForUser(int $conversationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, title, status, folder_id, created_at, updated_at FROM conversations WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$conversationId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(int $userId, ?string $title = null): int
    {
        $now = date('Y-m-d H:i:s');
        $title = $title && trim($title) !== '' ? trim($title) : 'New conversation';
        $stmt = $this->pdo->prepare('INSERT INTO conversations (user_id, title, status, created_at, updated_at) VALUES (?,?,"active",?,?)');
        $stmt->execute([$userId, $title, $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    public function rename(int $userId, int $conversationId, string $title): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE conversations SET title = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([trim($title), $now, $conversationId, $userId]);
    }

    public function delete(int $userId, int $conversationId): void
    {
        // Primero eliminar archivos físicos asociados
        require_once __DIR__ . '/ChatFilesRepo.php';
        $filesRepo = new ChatFilesRepo();
        $files = $filesRepo->listByConversation($conversationId);
        $storagePath = ChatFilesRepo::getStoragePath();
        
        foreach ($files as $file) {
            $path = $storagePath . '/' . $file['stored_name'];
            if (file_exists($path)) {
                @unlink($path);
            }
            // También eliminamos el registro de la BD explícitamente
            // para no dejar huérfanos si la FK es SET NULL
            $filesRepo->delete((int)$file['id']);
        }

        $stmt = $this->pdo->prepare('DELETE FROM conversations WHERE id = ? AND user_id = ?');
        $stmt->execute([$conversationId, $userId]);
    }

    public function touch(int $conversationId): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE conversations SET updated_at = ? WHERE id = ?');
        $stmt->execute([$now, $conversationId]);
    }

    public function autoTitle(int $conversationId, string $firstMessage): void
    {
        // Only if the title is the generic default.
        $conv = $this->pdo->prepare('SELECT title FROM conversations WHERE id = ? LIMIT 1');
        $conv->execute([$conversationId]);
        $row = $conv->fetch();
        if (!$row || !in_array($row['title'], ['New conversation', 'Nueva conversación'], true)) {
            return;
        }
        // Tomar primeras 60 caracteres del mensaje
        $title = mb_substr($firstMessage, 0, 60);
        if (mb_strlen($firstMessage) > 60) {
            $title .= '...';
        }
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE conversations SET title = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$title, $now, $conversationId]);
    }

    public function toggleFavorite(int $userId, int $conversationId): bool
    {
        // Obtener estado actual y verificar ownership
        $stmt = $this->pdo->prepare('SELECT is_favorite FROM conversations WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$conversationId, $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $newValue = $row['is_favorite'] ? 0 : 1;
        $now = date('Y-m-d H:i:s');
        $upd = $this->pdo->prepare('UPDATE conversations SET is_favorite = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $upd->execute([$newValue, $now, $conversationId, $userId]);
        return true;
    }

    /**
     * Mueve una conversación a una carpeta
     */
    public function moveToFolder(int $userId, int $conversationId, ?int $folderId): bool
    {
        // Verificar que la conversación pertenece al usuario
        if (!$this->findByIdForUser($conversationId, $userId)) {
            return false;
        }
        
        // Si se proporciona folderId, verificar que existe y pertenece al usuario
        if ($folderId !== null && $folderId > 0) {
            require_once __DIR__ . '/FoldersRepo.php';
            $foldersRepo = new FoldersRepo($this->pdo);
            if (!$foldersRepo->findByIdForUser($folderId, $userId)) {
                throw new \Exception('Folder not found');
            }
        }
        
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE conversations SET folder_id = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId > 0 ? $folderId : null, $now, $conversationId, $userId]);
        return true;
    }
}
