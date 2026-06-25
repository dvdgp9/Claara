<?php
namespace Repos;

use App\DB;
use PDO;

/**
 * Access profiles scoped to a voice, the folder grants attached to them, and the
 * user -> profile assignment that decides who can access a voice and at what level.
 */
class VoiceProfilesRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voice_access_profiles WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function getBySlug(int $voiceId, string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voice_access_profiles WHERE voice_id = ? AND slug = ?');
        $stmt->execute([$voiceId, $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function listByVoice(int $voiceId): array
    {
        // Highest level (most access) first.
        $stmt = $this->pdo->prepare(
            'SELECT * FROM voice_access_profiles WHERE voice_id = ? ORDER BY `rank` DESC, name'
        );
        $stmt->execute([$voiceId]);
        return $stmt->fetchAll();
    }

    public function create(int $voiceId, string $name, string $slug, ?string $description = null, bool $isDefault = false, ?int $rank = null): int
    {
        if ($rank === null) {
            $rank = $this->nextRank($voiceId);
        }
        $this->pdo->prepare(
            'INSERT INTO voice_access_profiles (voice_id, name, slug, description, is_default, `rank`)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([$voiceId, $name, $slug, $description, $isDefault ? 1 : 0, $rank]);
        return (int)$this->pdo->lastInsertId();
    }

    /** Next available rank for a new level in this voice (highest + 1). */
    public function nextRank(int $voiceId): int
    {
        $stmt = $this->pdo->prepare('SELECT COALESCE(MAX(`rank`), 0) + 1 FROM voice_access_profiles WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        return (int)$stmt->fetchColumn();
    }

    public function setRank(int $id, int $rank): void
    {
        $this->pdo->prepare('UPDATE voice_access_profiles SET `rank` = ? WHERE id = ?')->execute([$rank, $id]);
    }

    /**
     * Returns the voice's seeded full-access profile id, creating it if absent.
     */
    public function ensureDefaultProfile(int $voiceId, string $name = 'Full access', string $slug = 'full-access'): int
    {
        $existing = $this->getBySlug($voiceId, $slug);
        if ($existing) {
            return (int)$existing['id'];
        }
        return $this->create(
            $voiceId,
            $name,
            $slug,
            'Top access level: can read every folder in this voice.',
            true,
            100
        );
    }

    public function update(int $id, string $name, ?string $description): void
    {
        $this->pdo->prepare(
            'UPDATE voice_access_profiles SET name = ?, description = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$name, $description, $id]);
    }

    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM voice_access_profiles WHERE id = ?')->execute([$id]);
    }

    /**
     * @return int[] folder ids granted to a profile
     */
    public function folderIdsForProfile(int $profileId): array
    {
        $stmt = $this->pdo->prepare('SELECT folder_id FROM folder_profile_access WHERE profile_id = ?');
        $stmt->execute([$profileId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return array<int,int> profile_id => assigned user count for a voice
     */
    public function assignedUserCounts(int $voiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT profile_id, COUNT(*) AS n FROM user_voice_profiles WHERE voice_id = ? GROUP BY profile_id'
        );
        $stmt->execute([$voiceId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['profile_id']] = (int)$row['n'];
        }
        return $out;
    }

    public function grantFolder(int $folderId, int $profileId): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO folder_profile_access (folder_id, profile_id) VALUES (?, ?)'
        )->execute([$folderId, $profileId]);
    }

    public function revokeFolder(int $folderId, int $profileId): void
    {
        $this->pdo->prepare(
            'DELETE FROM folder_profile_access WHERE folder_id = ? AND profile_id = ?'
        )->execute([$folderId, $profileId]);
    }

    /**
     * @return int[] profile ids granted directly on a folder
     */
    public function profileIdsForFolder(int $folderId): array
    {
        $stmt = $this->pdo->prepare('SELECT profile_id FROM folder_profile_access WHERE folder_id = ?');
        $stmt->execute([$folderId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Assigns (or reassigns) a user's profile within a voice. One profile per
     * (user, voice). Having a row here means the user can access the voice.
     */
    public function assignUser(int $userId, int $voiceId, int $profileId): void
    {
        $this->pdo->prepare(
            'INSERT INTO user_voice_profiles (user_id, voice_id, profile_id)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE profile_id = VALUES(profile_id), updated_at = NOW()'
        )->execute([$userId, $voiceId, $profileId]);
    }

    public function unassignUser(int $userId, int $voiceId): void
    {
        $this->pdo->prepare(
            'DELETE FROM user_voice_profiles WHERE user_id = ? AND voice_id = ?'
        )->execute([$userId, $voiceId]);
    }

    public function getUserProfile(int $userId, int $voiceId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*
             FROM user_voice_profiles uvp
             JOIN voice_access_profiles p ON p.id = uvp.profile_id
             WHERE uvp.user_id = ? AND uvp.voice_id = ?'
        );
        $stmt->execute([$userId, $voiceId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * @return array<int,int> user_id => profile_id for a voice
     */
    public function assignmentsForVoice(int $voiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id, profile_id FROM user_voice_profiles WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['user_id']] = (int)$row['profile_id'];
        }
        return $out;
    }
}
