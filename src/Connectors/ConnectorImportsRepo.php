<?php

declare(strict_types=1);

namespace Connectors;

use App\DB;
use PDO;

class ConnectorImportsRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function create(int $itemId, int $accountId, int $userId, string $contextTarget = 'lex', ?int $jobId = null): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO connector_imports
                (item_id, account_id, user_id, job_id, context_target, status, created_at, updated_at)
            VALUES
                (:item_id, :account_id, :user_id, :job_id, :context_target, "queued", NOW(), NOW())
        ');
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $accountId,
            'user_id' => $userId,
            'job_id' => $jobId,
            'context_target' => mb_substr($contextTarget, 0, 60),
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function attachJob(int $importId, int $jobId): bool
    {
        $stmt = $this->pdo->prepare('UPDATE connector_imports SET job_id = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$jobId, $importId]);
    }

    public function find(int $importId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_imports WHERE id = ?');
        $stmt->execute([$importId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->decodeRow($row ?: null);
    }

    public function findForUser(int $importId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM connector_imports WHERE id = ? AND user_id = ?');
        $stmt->execute([$importId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $this->decodeRow($row ?: null);
    }

    public function markProcessing(int $importId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_imports
            SET status = "processing", started_at = COALESCE(started_at, NOW()), updated_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([$importId]);
    }

    public function markCompleted(int $importId, ?int $contextDocumentId, array $metadata = []): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_imports
            SET status = "completed",
                context_document_id = :context_document_id,
                import_metadata = :metadata,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ');
        return $stmt->execute([
            'id' => $importId,
            'context_document_id' => $contextDocumentId,
            'metadata' => json_encode($metadata, JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function markFailed(int $importId, string $message): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE connector_imports
            SET status = "failed", error_message = ?, completed_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ');
        return $stmt->execute([mb_substr($message, 0, 2000), $importId]);
    }

    private function decodeRow(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $row['import_metadata'] = $row['import_metadata'] ? json_decode($row['import_metadata'], true) : null;
        return $row;
    }
}

