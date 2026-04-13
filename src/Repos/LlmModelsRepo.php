<?php
namespace Repos;

use App\DB;
use PDO;

class LlmModelsRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function listActive(): array
    {
        $stmt = $this->pdo->query('SELECT id, model_key, label, is_active, sort_order FROM llm_models WHERE is_active = 1 ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->query('SELECT id, model_key, label, is_active, sort_order FROM llm_models ORDER BY sort_order ASC, id ASC');
        return $stmt->fetchAll() ?: [];
    }

    public function create(string $modelKey, string $label, bool $isActive = true): int
    {
        $sortOrder = $this->nextSortOrder();
        $stmt = $this->pdo->prepare('INSERT INTO llm_models (model_key, label, is_active, sort_order) VALUES (?, ?, ?, ?)');
        $stmt->execute([$modelKey, $label, $isActive ? 1 : 0, $sortOrder]);
        return (int)$this->pdo->lastInsertId();
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM llm_models WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function nextSortOrder(): int
    {
        $max = (int)($this->pdo->query('SELECT COALESCE(MAX(sort_order), 0) FROM llm_models')->fetchColumn() ?: 0);
        return $max + 10;
    }
}
