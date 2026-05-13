<?php

declare(strict_types=1);

namespace LeadFinder;

use App\DB;
use PDO;

class LeadFinderRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function createRun(int $userId, string $query, int $maxResults, string $provider = 'mock'): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO lead_finder_runs (user_id, query, max_results, provider, status, created_at, updated_at)
            VALUES (:user_id, :query, :max_results, :provider, "pending", NOW(), NOW())
        ');
        $stmt->execute([
            'user_id' => $userId,
            'query' => $query,
            'max_results' => $maxResults,
            'provider' => $provider,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function attachJob(int $runId, int $userId, int $jobId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_runs
            SET job_id = :job_id, updated_at = NOW()
            WHERE id = :id AND user_id = :user_id
        ');
        $stmt->execute(['id' => $runId, 'user_id' => $userId, 'job_id' => $jobId]);
        return $stmt->rowCount() > 0;
    }

    public function markRunProcessing(int $runId): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_runs
            SET status = "processing", started_at = COALESCE(started_at, NOW()), updated_at = NOW()
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $runId]);
    }

    public function markRunCompleted(int $runId): bool
    {
        $this->refreshCounts($runId);
        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_runs
            SET status = "completed", completed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $runId]);
    }

    public function markRunFailed(int $runId, string $errorMessage): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_runs
            SET status = "failed", error_message = :error_message, completed_at = NOW(), updated_at = NOW()
            WHERE id = :id
        ');
        return $stmt->execute(['id' => $runId, 'error_message' => $errorMessage]);
    }

    public function findRunForUser(int $runId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lead_finder_runs WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $runId, 'user_id' => $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findRun(int $runId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM lead_finder_runs WHERE id = :id');
        $stmt->execute(['id' => $runId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listRunsForUser(int $userId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, query, max_results, provider, status, results_count, validated_count, rejected_count, created_at, completed_at
            FROM lead_finder_runs
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT :limit
        ');
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', max(1, min($limit, 50)), PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deleteRunForUser(int $runId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM lead_finder_runs WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $runId, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     */
    public function replaceResults(int $runId, array $results): int
    {
        $this->pdo->prepare('DELETE FROM lead_finder_results WHERE run_id = ?')->execute([$runId]);

        $stmt = $this->pdo->prepare('
            INSERT INTO lead_finder_results
                (run_id, name, website, email, phone, address, source_url, confidence, status, raw_data, created_at, updated_at)
            VALUES
                (:run_id, :name, :website, :email, :phone, :address, :source_url, :confidence, "pending", :raw_data, NOW(), NOW())
        ');

        $count = 0;
        foreach ($this->dedupeResults($results) as $result) {
            $name = trim((string)($result['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $stmt->execute([
                'run_id' => $runId,
                'name' => mb_substr($name, 0, 255),
                'website' => $this->nullableString($result['website'] ?? null, 512),
                'email' => $this->nullableString($result['email'] ?? null, 255),
                'phone' => $this->nullableString($result['phone'] ?? null, 80),
                'address' => $this->nullableString($result['address'] ?? null, 512),
                'source_url' => $this->nullableString($result['source_url'] ?? null, 1024),
                'confidence' => isset($result['confidence']) ? max(0, min(1, (float)$result['confidence'])) : null,
                'raw_data' => json_encode($result['raw_data'] ?? $result, JSON_UNESCAPED_UNICODE),
            ]);
            $count++;
        }

        $this->refreshCounts($runId);
        return $count;
    }

    public function listResultsForRun(int $runId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT id, run_id, name, website, email, phone, address, source_url, confidence, status, raw_data, created_at, updated_at
            FROM lead_finder_results
            WHERE run_id = :run_id
            ORDER BY id ASC
        ');
        $stmt->execute(['run_id' => $runId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['raw_data'] = $row['raw_data'] ? json_decode($row['raw_data'], true) : null;
        }
        return $rows;
    }

    public function updateResultForUser(int $resultId, int $userId, array $data): bool
    {
        $allowedStatuses = ['pending', 'validated', 'rejected'];
        $status = (string)($data['status'] ?? 'pending');
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_results r
            INNER JOIN lead_finder_runs run ON run.id = r.run_id
            SET r.name = :name,
                r.website = :website,
                r.email = :email,
                r.phone = :phone,
                r.address = :address,
                r.source_url = :source_url,
                r.confidence = :confidence,
                r.status = :status,
                r.updated_at = NOW()
            WHERE r.id = :id AND run.user_id = :user_id
        ');
        $stmt->execute([
            'id' => $resultId,
            'user_id' => $userId,
            'name' => mb_substr(trim((string)($data['name'] ?? '')), 0, 255),
            'website' => $this->nullableString($data['website'] ?? null, 512),
            'email' => $this->nullableString($data['email'] ?? null, 255),
            'phone' => $this->nullableString($data['phone'] ?? null, 80),
            'address' => $this->nullableString($data['address'] ?? null, 512),
            'source_url' => $this->nullableString($data['source_url'] ?? null, 1024),
            'confidence' => isset($data['confidence']) ? max(0, min(1, (float)$data['confidence'])) : null,
            'status' => $status,
        ]);

        if ($stmt->rowCount() > 0) {
            $runId = $this->runIdForResult($resultId);
            if ($runId) {
                $this->refreshCounts($runId);
            }
            return true;
        }
        return false;
    }

    private function refreshCounts(int $runId): void
    {
        $stmt = $this->pdo->prepare('
            UPDATE lead_finder_runs run
            SET results_count = (SELECT COUNT(*) FROM lead_finder_results WHERE run_id = :id_count),
                validated_count = (SELECT COUNT(*) FROM lead_finder_results WHERE run_id = :id_validated AND status = "validated"),
                rejected_count = (SELECT COUNT(*) FROM lead_finder_results WHERE run_id = :id_rejected AND status = "rejected"),
                updated_at = NOW()
            WHERE id = :id
        ');
        $stmt->execute([
            'id' => $runId,
            'id_count' => $runId,
            'id_validated' => $runId,
            'id_rejected' => $runId,
        ]);
    }

    private function runIdForResult(int $resultId): ?int
    {
        $stmt = $this->pdo->prepare('SELECT run_id FROM lead_finder_results WHERE id = ?');
        $stmt->execute([$resultId]);
        $runId = $stmt->fetchColumn();
        return $runId ? (int)$runId : null;
    }

    /**
     * @param array<int, array<string, mixed>> $results
     * @return array<int, array<string, mixed>>
     */
    private function dedupeResults(array $results): array
    {
        $seen = [];
        $deduped = [];
        foreach ($results as $result) {
            $key = mb_strtolower(trim((string)($result['website'] ?? '')));
            if ($key === '') {
                $key = mb_strtolower(trim((string)($result['email'] ?? '')));
            }
            if ($key === '') {
                $key = mb_strtolower(trim((string)($result['name'] ?? '')));
            }
            if ($key === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $result;
        }
        return $deduped;
    }

    private function nullableString(mixed $value, int $maxLength): ?string
    {
        $string = trim((string)($value ?? ''));
        if ($string === '') {
            return null;
        }
        return mb_substr($string, 0, $maxLength);
    }
}
