<?php
namespace Repos;

use App\DB;
use PDO;

class DepartmentsRepo {
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function listAll(): array
    {
        $stmt = $this->pdo->prepare('
            SELECT d.id, d.name, d.slug, COUNT(u.id) AS user_count
            FROM departments d
            LEFT JOIN users u ON u.department_id = d.id
            GROUP BY d.id, d.name, d.slug
            ORDER BY d.name ASC
        ');
        $stmt->execute();
        return $stmt->fetchAll() ?: [];
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT d.id, d.name, d.slug, COUNT(u.id) AS user_count
            FROM departments d
            LEFT JOIN users u ON u.department_id = d.id
            WHERE d.id = ?
            GROUP BY d.id, d.name, d.slug
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function create(string $name): int
    {
        $slug = $this->uniqueSlug($name);
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare('
            INSERT INTO departments (company_id, name, slug, created_at, updated_at)
            VALUES (NULL, ?, ?, ?, ?)
        ');
        $stmt->execute([$name, $slug, $now, $now]);
        return (int)$this->pdo->lastInsertId();
    }

    public function update(int $id, string $name): bool
    {
        $slug = $this->uniqueSlug($name, $id);
        $stmt = $this->pdo->prepare('
            UPDATE departments
            SET name = ?, slug = ?, updated_at = ?
            WHERE id = ?
        ');
        $stmt->execute([$name, $slug, date('Y-m-d H:i:s'), $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM departments WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = $this->slugify($name);
        $slug = $base;
        $suffix = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        if ($ignoreId) {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM departments WHERE slug = ? AND id <> ?');
            $stmt->execute([$slug, $ignoreId]);
        } else {
            $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM departments WHERE slug = ?');
            $stmt->execute([$slug]);
        }

        return (int)$stmt->fetchColumn() > 0;
    }

    private function slugify(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if ($ascii !== false) {
            $value = $ascii;
        }

        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?: '';
        $value = trim($value, '-');

        return $value !== '' ? substr($value, 0, 120) : 'departamento';
    }
}
