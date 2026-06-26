<?php
namespace Repos;

use App\DB;
use PDO;

class UsersRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.email, u.password_hash, u.first_name, u.last_name, u.job_title, u.status, u.is_superadmin, u.department_id, d.name as department_name
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.email = ? 
            LIMIT 1
        ');
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function updateLastLoginAt(int $userId): void
    {
        $this->pdo->prepare('UPDATE users SET last_login_at = NOW(), updated_at = NOW() WHERE id = ?')->execute([$userId]);
    }

    public function getRoles(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT r.slug FROM roles r INNER JOIN user_roles ur ON ur.role_id = r.id WHERE ur.user_id = ?');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        return array_values($rows);
    }

    public function updateProfile(int $userId, string $firstName, string $lastName): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE users SET first_name = ?, last_name = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$firstName, $lastName, $now, $userId]);
    }

    public function updatePassword(int $userId, string $newPasswordHash): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$newPasswordHash, $now, $userId]);
    }

    public function getActivityStats(int $userId): array
    {
        // Total de conversaciones
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM conversations WHERE user_id = ?');
        $stmt->execute([$userId]);
        $totalConversations = (int)$stmt->fetchColumn();

        // Conversaciones esta semana
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM conversations WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$userId]);
        $conversationsThisWeek = (int)$stmt->fetchColumn();

        // Total de mensajes enviados
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND role = "user"');
        $stmt->execute([$userId]);
        $totalMessages = (int)$stmt->fetchColumn();

        // Mensajes esta semana
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM messages WHERE user_id = ? AND role = "user" AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)');
        $stmt->execute([$userId]);
        $messagesThisWeek = (int)$stmt->fetchColumn();

        return [
            'total_conversations' => $totalConversations,
            'conversations_this_week' => $conversationsThisWeek,
            'total_messages' => $totalMessages,
            'messages_this_week' => $messagesThisWeek
        ];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.is_superadmin,
                   u.job_title, u.department_id, d.name as department_name, u.last_login_at, u.created_at,
                   u.access_level_id
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            ORDER BY u.created_at DESC
        ');
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.email, u.first_name, u.last_name, u.status, u.is_superadmin, 
                   u.job_title, u.department_id, d.name as department_name, u.last_login_at, u.created_at, u.updated_at
            FROM users u
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE u.id = ?
            LIMIT 1
        ');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $email, string $passwordHash, string $firstName, string $lastName, ?int $departmentId = null, bool $isSuperadmin = false, ?string $jobTitle = null): int
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO users (email, password_hash, first_name, last_name, job_title, department_id, is_superadmin, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, "active", ?, ?)
        ');
        $stmt->execute([$email, $passwordHash, $firstName, $lastName, $jobTitle, $departmentId, $isSuperadmin ? 1 : 0, $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $userId, string $firstName, string $lastName, ?int $departmentId, string $status, bool $isSuperadmin, ?string $jobTitle = null): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            UPDATE users 
            SET first_name = ?, last_name = ?, job_title = ?, department_id = ?, status = ?, is_superadmin = ?, updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([$firstName, $lastName, $jobTitle, $departmentId, $status, $isSuperadmin ? 1 : 0, $now, $userId]);
    }

    public function countActiveSuperadmins(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM users WHERE is_superadmin = 1 AND status = "active"');
        return (int)$stmt->fetchColumn();
    }

    public function updateEmail(int $userId, string $email): void
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('UPDATE users SET email = ?, updated_at = ? WHERE id = ?');
        $stmt->execute([$email, $now, $userId]);
    }

    public function delete(int $userId): void
    {
        // Eliminar conversaciones y mensajes asociados (cascada manual si no está en BD)
        $this->pdo->prepare('DELETE FROM messages WHERE user_id = ?')->execute([$userId]);
        $this->pdo->prepare('DELETE FROM conversations WHERE user_id = ?')->execute([$userId]);
        
        // Eliminar roles asociados
        $this->pdo->prepare('DELETE FROM user_roles WHERE user_id = ?')->execute([$userId]);
        
        // Eliminar usuario
        $this->pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    }
}
