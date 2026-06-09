<?php
namespace Repos;

use App\DB;
use PDO;

class ConversationAccessRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function getAccess(int $conversationId, array $user): ?array
    {
        $userId = (int)($user['id'] ?? 0);
        if ($conversationId <= 0 || $userId <= 0) {
            return null;
        }

        $stmt = $this->pdo->prepare('
            SELECT c.id, c.user_id, c.title, c.status, c.folder_id, c.is_favorite,
                   c.created_at, c.updated_at,
                   u.first_name AS owner_first_name, u.last_name AS owner_last_name, u.email AS owner_email
            FROM conversations c
            LEFT JOIN users u ON u.id = c.user_id
            WHERE c.id = ?
            LIMIT 1
        ');
        $stmt->execute([$conversationId]);
        $conversation = $stmt->fetch();
        if (!$conversation) {
            return null;
        }

        $isOwner = (int)$conversation['user_id'] === $userId;
        $isSuperadmin = !empty($user['is_superadmin']);

        if ($isOwner || $isSuperadmin) {
            return $this->formatAccess($conversation, 'owner', true, true, true, null, $this->countShares($conversationId));
        }

        $share = $this->bestShareForUser($conversationId, $user);
        if (!$share) {
            return null;
        }

        $permission = (string)$share['permission'];
        return $this->formatAccess(
            $conversation,
            $permission,
            true,
            $permission === 'chat',
            false,
            [
                'target_type' => $share['target_type'],
                'target_id' => (int)$share['target_id'],
            ],
            $this->countShares($conversationId)
        );
    }

    public function canView(int $conversationId, array $user): bool
    {
        $access = $this->getAccess($conversationId, $user);
        return $access !== null && !empty($access['can_view']);
    }

    public function canChat(int $conversationId, array $user): bool
    {
        $access = $this->getAccess($conversationId, $user);
        return $access !== null && !empty($access['can_chat']);
    }

    public function canManage(int $conversationId, array $user): bool
    {
        $access = $this->getAccess($conversationId, $user);
        return $access !== null && !empty($access['can_manage']);
    }

    public function listShares(int $conversationId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT cs.id, cs.conversation_id, cs.target_type, cs.target_id, cs.permission, cs.created_at, cs.updated_at,
                   CASE
                     WHEN cs.target_type = "user" THEN CONCAT(u.first_name, " ", u.last_name)
                     WHEN cs.target_type = "department" THEN d.name
                     ELSE NULL
                   END AS target_name,
                   u.email AS target_email
            FROM conversation_shares cs
            LEFT JOIN users u ON cs.target_type = "user" AND u.id = cs.target_id
            LEFT JOIN departments d ON cs.target_type = "department" AND d.id = cs.target_id
            WHERE cs.conversation_id = ?
            ORDER BY cs.target_type ASC, target_name ASC
        ');
        $stmt->execute([$conversationId]);
        return $stmt->fetchAll() ?: [];
    }

    public function countShares(int $conversationId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM conversation_shares WHERE conversation_id = ?');
        $stmt->execute([$conversationId]);
        return (int)$stmt->fetchColumn();
    }

    public function upsertShare(int $conversationId, string $targetType, int $targetId, string $permission, ?int $createdBy): void
    {
        $targetType = $this->normalizeTargetType($targetType);
        $permission = $this->normalizePermission($permission);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare('
            INSERT INTO conversation_shares
                (conversation_id, target_type, target_id, permission, created_by, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                permission = VALUES(permission),
                updated_at = VALUES(updated_at)
        ');
        $stmt->execute([$conversationId, $targetType, $targetId, $permission, $createdBy, $now, $now]);
    }

    public function removeShare(int $conversationId, int $shareId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM conversation_shares WHERE conversation_id = ? AND id = ?');
        $stmt->execute([$conversationId, $shareId]);
        return $stmt->rowCount() > 0;
    }

    public function listSharedWithUser(array $user): array
    {
        $userId = (int)($user['id'] ?? 0);
        $departmentId = isset($user['department_id']) ? (int)$user['department_id'] : 0;
        if ($userId <= 0) {
            return ['shared_with_me' => [], 'department_shared' => []];
        }

        return [
            'shared_with_me' => $this->listSharedByTarget('user', $userId),
            'department_shared' => $departmentId > 0 ? $this->listSharedByTarget('department', $departmentId) : [],
        ];
    }

    private function listSharedByTarget(string $targetType, int $targetId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT c.id, c.title, c.status, c.folder_id, c.is_favorite, c.created_at, c.updated_at,
                   cs.permission AS effective_permission,
                   cs.target_type AS share_target_type,
                   cs.target_id AS share_target_id,
                   owner.first_name AS owner_first_name,
                   owner.last_name AS owner_last_name,
                   owner.email AS owner_email
            FROM conversation_shares cs
            INNER JOIN conversations c ON c.id = cs.conversation_id
            LEFT JOIN users owner ON owner.id = c.user_id
            WHERE cs.target_type = ? AND cs.target_id = ?
            ORDER BY c.updated_at DESC
        ');
        $stmt->execute([$targetType, $targetId]);
        $rows = $stmt->fetchAll() ?: [];
        return array_map([$this, 'formatSharedConversation'], $rows);
    }

    private function bestShareForUser(int $conversationId, array $user): ?array
    {
        $userId = (int)($user['id'] ?? 0);
        $departmentId = isset($user['department_id']) ? (int)$user['department_id'] : 0;
        $params = [$conversationId, $userId];
        $departmentClause = '';

        if ($departmentId > 0) {
            $departmentClause = ' OR (target_type = "department" AND target_id = ?)';
            $params[] = $departmentId;
        }

        $stmt = $this->pdo->prepare("
            SELECT target_type, target_id, permission
            FROM conversation_shares
            WHERE conversation_id = ?
              AND ((target_type = 'user' AND target_id = ?){$departmentClause})
            ORDER BY FIELD(permission, 'chat', 'view') ASC
            LIMIT 1
        ");
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function formatAccess(array $conversation, string $permission, bool $canView, bool $canChat, bool $canManage, ?array $share, int $shareCount): array
    {
        return [
            'conversation' => [
                'id' => (int)$conversation['id'],
                'title' => $conversation['title'],
                'status' => $conversation['status'],
                'folder_id' => isset($conversation['folder_id']) ? (int)$conversation['folder_id'] : null,
                'is_favorite' => !empty($conversation['is_favorite']),
                'created_at' => $conversation['created_at'],
                'updated_at' => $conversation['updated_at'],
                'owner' => [
                    'id' => (int)$conversation['user_id'],
                    'name' => trim(($conversation['owner_first_name'] ?? '') . ' ' . ($conversation['owner_last_name'] ?? '')),
                    'email' => $conversation['owner_email'] ?? null,
                ],
            ],
            'permission' => $permission,
            'can_view' => $canView,
            'can_chat' => $canChat,
            'can_manage' => $canManage,
            'is_shared' => $shareCount > 0,
            'share_count' => $shareCount,
            'share' => $share,
        ];
    }

    private function formatSharedConversation(array $row): array
    {
        $ownerName = trim(($row['owner_first_name'] ?? '') . ' ' . ($row['owner_last_name'] ?? ''));
        return [
            'id' => (int)$row['id'],
            'title' => $row['title'],
            'status' => $row['status'],
            'folder_id' => isset($row['folder_id']) ? (int)$row['folder_id'] : null,
            'is_favorite' => !empty($row['is_favorite']),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
            'effective_permission' => $row['effective_permission'],
            'share_target_type' => $row['share_target_type'],
            'share_target_id' => (int)$row['share_target_id'],
            'owner_name' => $ownerName !== '' ? $ownerName : ($row['owner_email'] ?? 'Owner'),
            'owner_email' => $row['owner_email'] ?? null,
        ];
    }

    private function normalizeTargetType(string $targetType): string
    {
        if (!in_array($targetType, ['user', 'department'], true)) {
            throw new \InvalidArgumentException('Invalid share target type');
        }
        return $targetType;
    }

    private function normalizePermission(string $permission): string
    {
        if (!in_array($permission, ['view', 'chat'], true)) {
            throw new \InvalidArgumentException('Invalid share permission');
        }
        return $permission;
    }
}
