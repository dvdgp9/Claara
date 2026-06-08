<?php
namespace Repos;

use App\DB;
use PDO;

class OrganizationResponsibilityRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function getDepartmentResponsiblesMap(): array
    {
        $stmt = $this->pdo->query('
            SELECT dr.department_id, u.id, u.email, u.first_name, u.last_name, u.job_title
            FROM department_responsibles dr
            INNER JOIN users u ON u.id = dr.user_id
            ORDER BY u.first_name, u.last_name
        ');

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $departmentId = (int)$row['department_id'];
            $map[$departmentId][] = $this->normalizeUser($row);
        }
        return $map;
    }

    public function getVoiceResponsiblesMap(): array
    {
        $stmt = $this->pdo->query('
            SELECT vr.voice_slug, u.id, u.email, u.first_name, u.last_name, u.job_title
            FROM voice_responsibles vr
            INNER JOIN users u ON u.id = vr.user_id
            ORDER BY u.first_name, u.last_name
        ');

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $slug = (string)$row['voice_slug'];
            $map[$slug][] = $this->normalizeUser($row);
        }
        return $map;
    }

    public function getUserDepartmentResponsibilitiesMap(): array
    {
        $stmt = $this->pdo->query('
            SELECT dr.user_id, d.id, d.name, d.slug
            FROM department_responsibles dr
            INNER JOIN departments d ON d.id = dr.department_id
            ORDER BY d.name
        ');

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $userId = (int)$row['user_id'];
            $map[$userId][] = [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'slug' => (string)$row['slug'],
            ];
        }
        return $map;
    }

    public function getUserVoiceResponsibilitiesMap(): array
    {
        $stmt = $this->pdo->query('
            SELECT vr.user_id, v.slug, COALESCE(v.name, vr.voice_slug) AS name, v.role, v.status
            FROM voice_responsibles vr
            LEFT JOIN voices v ON v.slug = vr.voice_slug
            ORDER BY name
        ');

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $userId = (int)$row['user_id'];
            $map[$userId][] = [
                'slug' => (string)$row['slug'],
                'name' => (string)$row['name'],
                'role' => (string)($row['role'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
            ];
        }
        return $map;
    }

    public function getDepartmentResponsibles(int $departmentId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.email, u.first_name, u.last_name, u.job_title
            FROM department_responsibles dr
            INNER JOIN users u ON u.id = dr.user_id
            WHERE dr.department_id = ?
            ORDER BY u.first_name, u.last_name
        ');
        $stmt->execute([$departmentId]);
        return array_map(fn(array $row): array => $this->normalizeUser($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function setDepartmentResponsibles(int $departmentId, array $userIds): void
    {
        $userIds = $this->cleanUserIds($userIds);
        $this->pdo->prepare('DELETE FROM department_responsibles WHERE department_id = ?')->execute([$departmentId]);

        if (!$userIds) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT IGNORE INTO department_responsibles (department_id, user_id)
            VALUES (?, ?)
        ');
        foreach ($userIds as $userId) {
            $stmt->execute([$departmentId, $userId]);
        }
    }

    public function getVoiceResponsibles(string $voiceSlug): array
    {
        $stmt = $this->pdo->prepare('
            SELECT u.id, u.email, u.first_name, u.last_name, u.job_title
            FROM voice_responsibles vr
            INNER JOIN users u ON u.id = vr.user_id
            WHERE vr.voice_slug = ?
            ORDER BY u.first_name, u.last_name
        ');
        $stmt->execute([$voiceSlug]);
        return array_map(fn(array $row): array => $this->normalizeUser($row), $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function setVoiceResponsibles(string $voiceSlug, array $userIds): void
    {
        $userIds = $this->cleanUserIds($userIds);
        $this->pdo->prepare('DELETE FROM voice_responsibles WHERE voice_slug = ?')->execute([$voiceSlug]);

        if (!$userIds) {
            return;
        }

        $stmt = $this->pdo->prepare('
            INSERT IGNORE INTO voice_responsibles (voice_slug, user_id)
            VALUES (?, ?)
        ');
        foreach ($userIds as $userId) {
            $stmt->execute([$voiceSlug, $userId]);
        }
    }

    private function cleanUserIds(array $userIds): array
    {
        $clean = [];
        foreach ($userIds as $userId) {
            $id = (int)$userId;
            if ($id > 0) {
                $clean[$id] = $id;
            }
        }
        return array_values($clean);
    }

    private function normalizeUser(array $row): array
    {
        return [
            'id' => (int)$row['id'],
            'email' => (string)$row['email'],
            'first_name' => (string)$row['first_name'],
            'last_name' => (string)$row['last_name'],
            'job_title' => (string)($row['job_title'] ?? ''),
        ];
    }
}
