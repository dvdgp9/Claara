<?php
namespace Repos;

use App\DB;
use PDO;

/**
 * Global, org-wide access levels. Higher `rank` = more access. One level per
 * user (users.access_level_id). Voices and folders compare against a level's
 * rank to decide who may read what.
 */
class AccessLevelsRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /** @return array<int,array> highest rank first */
    public function listAll(): array
    {
        return $this->pdo
            ->query('SELECT * FROM access_levels ORDER BY `rank` DESC, name ASC')
            ->fetchAll();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM access_levels WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM access_levels WHERE slug = ?');
        $stmt->execute([$slug]);
        return $stmt->fetch() ?: null;
    }

    public function getDefault(): ?array
    {
        $row = $this->pdo
            ->query('SELECT * FROM access_levels WHERE is_default = 1 ORDER BY `rank` DESC LIMIT 1')
            ->fetch();
        return $row ?: null;
    }

    /** The level assigned to a user, or null if they have none. */
    public function getUserLevel(int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT al.*
             FROM users u
             JOIN access_levels al ON al.id = u.access_level_id
             WHERE u.id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** Next free rank (highest + 1) for a new level. */
    public function nextRank(): int
    {
        return (int)$this->pdo
            ->query('SELECT COALESCE(MAX(`rank`), 0) + 1 FROM access_levels')
            ->fetchColumn();
    }

    public function create(string $name, string $slug, ?int $rank = null, bool $isDefault = false): int
    {
        if ($rank === null) {
            $rank = $this->nextRank();
        }
        if ($isDefault) {
            $this->clearDefault();
        }
        $this->pdo->prepare(
            'INSERT INTO access_levels (name, slug, `rank`, is_default) VALUES (?, ?, ?, ?)'
        )->execute([$name, $slug, $rank, $isDefault ? 1 : 0]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name): void
    {
        $this->pdo->prepare('UPDATE access_levels SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    public function setRank(int $id, int $rank): void
    {
        $this->pdo->prepare('UPDATE access_levels SET `rank` = ? WHERE id = ?')->execute([$rank, $id]);
    }

    public function setDefault(int $id): void
    {
        $this->clearDefault();
        $this->pdo->prepare('UPDATE access_levels SET is_default = 1 WHERE id = ?')->execute([$id]);
    }

    private function clearDefault(): void
    {
        $this->pdo->exec('UPDATE access_levels SET is_default = 0 WHERE is_default = 1');
    }

    public function delete(int $id): void
    {
        // users.access_level_id and voices.min_access_level_id are nullable; the
        // app reassigns affected users to the default level before deleting.
        $this->pdo->prepare('DELETE FROM access_levels WHERE id = ?')->execute([$id]);
    }

    /** Assign a user's single global level. */
    public function assignUser(int $userId, ?int $levelId): void
    {
        $this->pdo->prepare('UPDATE users SET access_level_id = ? WHERE id = ?')
            ->execute([$levelId, $userId]);
    }

    /** @return array<int,int> level_id => number of users at that level */
    public function userCounts(): array
    {
        $rows = $this->pdo
            ->query('SELECT access_level_id, COUNT(*) AS n FROM users WHERE access_level_id IS NOT NULL GROUP BY access_level_id')
            ->fetchAll();
        $out = [];
        foreach ($rows as $r) {
            $out[(int)$r['access_level_id']] = (int)$r['n'];
        }
        return $out;
    }
}
