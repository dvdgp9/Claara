<?php

declare(strict_types=1);

namespace Connectors;

use App\DB;
use PDO;

class ConnectorItemsRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function upsertSelected(int $accountId, string $providerKey, array $item): int
    {
        $externalId = trim((string)($item['external_item_id'] ?? $item['id'] ?? ''));
        $name = trim((string)($item['name'] ?? $item['title'] ?? ''));
        if ($externalId === '' || $name === '') {
            throw new \InvalidArgumentException('Connector item requires external id and name');
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO connector_items
                (account_id, provider_key, external_item_id, item_type, name, mime_type, source_url, external_version, checksum, size_bytes, status, metadata, selected_at, created_at, updated_at)
            VALUES
                (:account_id, :provider_key, :external_item_id, :item_type, :name, :mime_type, :source_url, :external_version, :checksum, :size_bytes, "selected", :metadata, NOW(), NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                item_type = VALUES(item_type),
                name = VALUES(name),
                mime_type = VALUES(mime_type),
                source_url = VALUES(source_url),
                external_version = VALUES(external_version),
                checksum = VALUES(checksum),
                size_bytes = VALUES(size_bytes),
                status = "selected",
                metadata = VALUES(metadata),
                updated_at = NOW()
        ');
        $stmt->execute([
            'account_id' => $accountId,
            'provider_key' => $providerKey,
            'external_item_id' => mb_substr($externalId, 0, 512),
            'item_type' => $this->itemType($item['item_type'] ?? $item['type'] ?? 'unknown'),
            'name' => mb_substr($name, 0, 255),
            'mime_type' => $this->nullableString($item['mime_type'] ?? $item['mimeType'] ?? null, 190),
            'source_url' => $this->nullableString($item['source_url'] ?? $item['url'] ?? null, 1024),
            'external_version' => $this->nullableString($item['external_version'] ?? $item['version'] ?? null, 190),
            'checksum' => $this->nullableString($item['checksum'] ?? null, 128),
            'size_bytes' => isset($item['size_bytes']) ? max(0, (int)$item['size_bytes']) : null,
            'metadata' => json_encode($item, JSON_UNESCAPED_UNICODE),
        ]);

        $id = (int)$this->pdo->lastInsertId();
        if ($id > 0) {
            return $id;
        }

        $existing = $this->findByExternal($accountId, $externalId);
        if (!$existing) {
            throw new \RuntimeException('Could not resolve connector item after upsert');
        }
        return (int)$existing['id'];
    }

    public function findForUser(int $itemId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT i.*
            FROM connector_items i
            INNER JOIN connector_accounts a ON a.id = i.account_id
            WHERE i.id = ? AND a.user_id = ?
        ');
        $stmt->execute([$itemId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->decodeRow($row ?: null);
    }

    public function findByExternal(int $accountId, string $externalItemId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_items WHERE account_id = ? AND external_item_id = ?');
        $stmt->execute([$accountId, $externalItemId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->decodeRow($row ?: null);
    }

    public function listForAccount(int $accountId, int $limit = 100): array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM connector_items
            WHERE account_id = ?
            ORDER BY selected_at DESC, id DESC
            LIMIT ?
        ');
        $stmt->bindValue(1, $accountId, PDO::PARAM_INT);
        $stmt->bindValue(2, max(1, min($limit, 500)), PDO::PARAM_INT);
        $stmt->execute();
        return array_map([$this, 'decodeRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function updateStatus(int $itemId, string $status, ?string $errorMessage = null): bool
    {
        $allowed = ['selected', 'queued', 'importing', 'imported', 'error', 'removed'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException('Invalid connector item status');
        }

        $stmt = $this->pdo->prepare('
            UPDATE connector_items
            SET status = ?, last_error_message = ?, last_imported_at = IF(? = "imported", NOW(), last_imported_at), updated_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([$status, $errorMessage, $status, $itemId]);
    }

    private function decodeRow(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $row['metadata'] = $row['metadata'] ? json_decode($row['metadata'], true) : null;
        return $row;
    }

    private function itemType(mixed $type): string
    {
        $type = (string)$type;
        $allowed = ['file', 'folder', 'channel', 'message', 'team', 'chat', 'unknown'];
        return in_array($type, $allowed, true) ? $type : 'unknown';
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $value = trim((string)($value ?? ''));
        return $value === '' ? null : mb_substr($value, 0, $maxLength);
    }
}

