<?php
namespace Repos;

use App\DB;
use PDO;

/**
 * Explicit per-voice allow-list, used when a voice's access_mode = 'list'.
 * A row (voice_id, user_id) means that user may enter the voice regardless of
 * their global access level.
 */
class VoiceAccessListRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function isListed(int $userId, int $voiceId): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM voice_access_list WHERE voice_id = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$voiceId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /** @return int[] user ids on the voice's allow-list */
    public function userIds(int $voiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT user_id FROM voice_access_list WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function add(int $voiceId, int $userId): void
    {
        $this->pdo->prepare(
            'INSERT IGNORE INTO voice_access_list (voice_id, user_id) VALUES (?, ?)'
        )->execute([$voiceId, $userId]);
    }

    public function remove(int $voiceId, int $userId): void
    {
        $this->pdo->prepare(
            'DELETE FROM voice_access_list WHERE voice_id = ? AND user_id = ?'
        )->execute([$voiceId, $userId]);
    }

    public function countByVoice(int $voiceId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM voice_access_list WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        return (int)$stmt->fetchColumn();
    }
}
