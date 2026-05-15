<?php

declare(strict_types=1);

namespace Connectors;

use App\DB;
use PDO;

class ConnectorAccountsRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function createOrUpdate(int $userId, string $providerKey, array $profile, array $scopes = []): int
    {
        $externalId = trim((string)($profile['id'] ?? $profile['external_account_id'] ?? ''));
        if ($externalId === '') {
            throw new \InvalidArgumentException('Connector account profile is missing external id');
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO connector_accounts
                (user_id, provider_key, external_account_id, external_email, external_name, display_name, scopes, status, connected_at, created_at, updated_at)
            VALUES
                (:user_id, :provider_key, :external_account_id, :external_email, :external_name, :display_name, :scopes, "connected", NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                external_email = VALUES(external_email),
                external_name = VALUES(external_name),
                display_name = VALUES(display_name),
                scopes = VALUES(scopes),
                status = "connected",
                connected_at = NOW(),
                disconnected_at = NULL,
                last_error_message = NULL,
                updated_at = NOW()
        ');
        $stmt->execute([
            'user_id' => $userId,
            'provider_key' => $providerKey,
            'external_account_id' => $externalId,
            'external_email' => $this->nullableString($profile['email'] ?? null, 190),
            'external_name' => $this->nullableString($profile['name'] ?? null, 190),
            'display_name' => $this->nullableString($profile['display_name'] ?? $profile['name'] ?? $profile['email'] ?? null, 190),
            'scopes' => implode(' ', $scopes),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $existing = $this->findByExternal($userId, $providerKey, $externalId);
        if (!$existing) {
            throw new \RuntimeException('Could not resolve connector account after upsert');
        }
        return (int)$existing['id'];
    }

    public function findForUser(int $accountId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_accounts WHERE id = ? AND user_id = ?');
        $stmt->execute([$accountId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findByExternal(int $userId, string $providerKey, string $externalAccountId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM connector_accounts
            WHERE user_id = ? AND provider_key = ? AND external_account_id = ?
        ');
        $stmt->execute([$userId, $providerKey, $externalAccountId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT a.*, p.display_name AS provider_name, p.icon AS provider_icon
            FROM connector_accounts a
            INNER JOIN connector_providers p ON p.provider_key = a.provider_key
            WHERE a.user_id = ?
            ORDER BY p.sort_order, a.connected_at DESC
        ');
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listProviderStatusForUser(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT
                p.provider_key,
                p.display_name,
                p.description,
                p.icon,
                p.is_enabled,
                a.id AS account_id,
                a.external_email,
                a.external_name,
                a.display_name AS account_display_name,
                a.status AS account_status,
                a.last_sync_at,
                a.last_error_message,
                a.connected_at,
                COUNT(DISTINCT i.id) AS item_count,
                COUNT(DISTINCT CASE WHEN i.status = "imported" THEN i.id END) AS imported_count
            FROM connector_providers p
            LEFT JOIN connector_accounts a
                ON a.provider_key = p.provider_key
                AND a.user_id = :user_id
                AND a.status <> "disconnected"
            LEFT JOIN connector_items i
                ON i.account_id = a.id
                AND i.status <> "removed"
            GROUP BY
                p.provider_key, p.display_name, p.description, p.icon, p.is_enabled,
                a.id, a.external_email, a.external_name, a.display_name, a.status,
                a.last_sync_at, a.last_error_message, a.connected_at
            ORDER BY p.sort_order, p.display_name
        ');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function adminSummary(): array
    {
        $stmt = $this->pdo->query('
            SELECT
                p.provider_key,
                p.display_name,
                p.description,
                p.icon,
                p.is_enabled,
                COUNT(DISTINCT CASE WHEN a.status = "connected" THEN a.id END) AS connected_accounts,
                COUNT(DISTINCT CASE WHEN a.status = "error" THEN a.id END) AS error_accounts,
                COUNT(DISTINCT i.id) AS selected_items,
                COUNT(DISTINCT CASE WHEN i.status = "imported" THEN i.id END) AS imported_items,
                MAX(a.last_sync_at) AS last_sync_at,
                MAX(a.connected_at) AS last_connected_at
            FROM connector_providers p
            LEFT JOIN connector_accounts a
                ON a.provider_key = p.provider_key
                AND a.status <> "disconnected"
            LEFT JOIN connector_items i
                ON i.account_id = a.id
                AND i.status <> "removed"
            GROUP BY p.provider_key, p.display_name, p.description, p.icon, p.is_enabled
            ORDER BY p.sort_order, p.display_name
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markDisconnected(int $accountId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_accounts
            SET status = "disconnected", disconnected_at = NOW(), updated_at = NOW()
            WHERE id = ? AND user_id = ?
        ');
        $stmt->execute([$accountId, $userId]);
        return $stmt->rowCount() > 0;
    }

    public function markError(int $accountId, string $message): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_accounts
            SET status = "error", last_error_message = ?, updated_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([mb_substr($message, 0, 2000), $accountId]);
    }

    public function markSynced(int $accountId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_accounts
            SET last_sync_at = NOW(), last_error_message = NULL, updated_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([$accountId]);
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}
