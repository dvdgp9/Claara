<?php
namespace Repos;

use App\DB;
use PDO;

class FoldersRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /**
     * Lista todas las carpetas de un usuario ordenadas jerárquicamente
     */
    public function listByUser(int $userId): array
    {
        $sql = "SELECT id, name, parent_id, sort_order, created_at, updated_at 
                FROM folders 
                WHERE user_id = ? 
                ORDER BY parent_id ASC, sort_order ASC, created_at ASC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$userId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * Obtiene una carpeta específica verificando que pertenezca al usuario
     */
    public function findByIdForUser(int $folderId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id, name, parent_id, sort_order, created_at, updated_at FROM folders WHERE id = ? AND user_id = ? LIMIT 1');
        $stmt->execute([$folderId, $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crea una nueva carpeta
     */
    public function create(int $userId, string $name, ?int $parentId = null, int $sortOrder = 0): int
    {
        $now = date('Y-m-d H:i:s');
        
        // Validar que parent_id pertenece al usuario si se proporciona
        if ($parentId !== null) {
            $parent = $this->findByIdForUser($parentId, $userId);
            if (!$parent) {
                throw new \Exception('Parent folder not found or not available to this user');
            }
        }
        
        $stmt = $this->pdo->prepare('INSERT INTO folders (user_id, name, parent_id, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?)');
        $stmt->execute([$userId, trim($name), $parentId, $sortOrder, $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Renombra una carpeta
     */
    public function rename(int $userId, int $folderId, string $name): bool
    {
        if (!$this->findByIdForUser($folderId, $userId)) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE folders SET name = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([trim($name), $now, $folderId, $userId]);
        return true;
    }

    /**
     * Mueve una carpeta (cambia su parent_id)
     */
    public function move(int $userId, int $folderId, ?int $newParentId = null): bool
    {
        if (!$this->findByIdForUser($folderId, $userId)) {
            return false;
        }
        
        // Validar que no se mueva a sí misma o a una subcarpeta propia (evitar ciclos)
        if ($newParentId !== null) {
            if ($newParentId === $folderId) {
                throw new \Exception('A folder cannot be its own parent');
            }
            
            // Verificar que newParentId pertenece al usuario
            if (!$this->findByIdForUser($newParentId, $userId)) {
                throw new \Exception('Destination folder not found');
            }
            
            // Verificar que no sea descendiente (evitar ciclos)
            if ($this->isDescendantOf($folderId, $newParentId)) {
                throw new \Exception('A folder cannot be moved into one of its subfolders');
            }
        }
        
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE folders SET parent_id = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$newParentId, $now, $folderId, $userId]);
        return true;
    }

    /**
     * Actualiza el orden de una carpeta
     */
    public function updateSortOrder(int $userId, int $folderId, int $sortOrder): bool
    {
        if (!$this->findByIdForUser($folderId, $userId)) {
            return false;
        }
        
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE folders SET sort_order = ?, updated_at = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$sortOrder, $now, $folderId, $userId]);
        return true;
    }

    /**
     * Elimina una carpeta
     * Las conversaciones dentro quedarán sin carpeta (folder_id = NULL)
     */
    public function delete(int $userId, int $folderId): bool
    {
        if (!$this->findByIdForUser($folderId, $userId)) {
            return false;
        }
        
        // Las subcarpetas se eliminarán en cascada o quedarán huérfanas según FK
        // Las conversaciones quedarán sin carpeta (ON DELETE SET NULL)
        $stmt = $this->pdo->prepare('DELETE FROM folders WHERE id = ? AND user_id = ?');
        $stmt->execute([$folderId, $userId]);
        return true;
    }

    /**
     * Cuenta conversaciones en una carpeta
     */
    public function countConversations(int $folderId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) as total FROM conversations WHERE folder_id = ?');
        $stmt->execute([$folderId]);
        $row = $stmt->fetch();
        return (int)($row['total'] ?? 0);
    }

    /**
     * Verifica si folderId es descendiente de potentialAncestorId
     * (usado para prevenir ciclos al mover carpetas)
     */
    private function isDescendantOf(int $folderId, int $potentialAncestorId): bool
    {
        $stmt = $this->pdo->prepare('SELECT parent_id FROM folders WHERE id = ? LIMIT 1');
        $stmt->execute([$potentialAncestorId]);
        $row = $stmt->fetch();
        
        if (!$row || $row['parent_id'] === null) {
            return false;
        }
        
        if ($row['parent_id'] === $folderId) {
            return true;
        }
        
        return $this->isDescendantOf($folderId, (int)$row['parent_id']);
    }

    /**
     * Obtiene la ruta completa de una carpeta (breadcrumb)
     */
    public function getPath(int $folderId, int $userId): array
    {
        $path = [];
        $currentId = $folderId;
        
        while ($currentId !== null) {
            $folder = $this->findByIdForUser($currentId, $userId);
            if (!$folder) {
                break;
            }
            array_unshift($path, [
                'id' => (int)$folder['id'],
                'name' => $folder['name']
            ]);
            $currentId = $folder['parent_id'] ? (int)$folder['parent_id'] : null;
        }
        
        return $path;
    }
}
