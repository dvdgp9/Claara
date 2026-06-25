<?php
namespace Voices;

use App\DB;
use PDO;
use Repos\VoiceProfilesRepo;
use Repos\VoicesRepo;

/**
 * Resolves, for a given user and voice, whether they can access the voice and
 * exactly which document folders they may read from.
 *
 * Security contract — FAIL CLOSED:
 *   - A user with no profile in the voice (and who is not a superadmin or a
 *     voice responsible) gets NO access and an EMPTY folder set.
 *   - resolveAccessibleFolderIds() returning [] must be treated by callers as
 *     "no accessible documents", never as "no filter / show everything".
 *
 * Bypass (full access to every folder): superadmins and users responsible for
 * the voice. Everyone else is limited to the folders their profile is granted,
 * expanded down the tree via the materialized folder path.
 */
class VoiceAccessResolver
{
    private PDO $pdo;
    private VoiceProfilesRepo $profiles;

    public function __construct(?PDO $pdo = null, ?VoiceProfilesRepo $profiles = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
        $this->profiles = $profiles ?? new VoiceProfilesRepo($this->pdo);
    }

    public function isSuperadmin(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT is_superadmin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row && (int)$row['is_superadmin'] === 1;
    }

    public function isResponsible(int $userId, string $voiceSlug): bool
    {
        if ($voiceSlug === '') {
            return false;
        }
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM voice_responsibles WHERE voice_slug = ? AND user_id = ? LIMIT 1'
        );
        $stmt->execute([$voiceSlug, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Full bypass means "see every folder regardless of profile grants".
     */
    public function hasFullAccess(int $userId, array $voice): bool
    {
        return $this->isSuperadmin($userId)
            || $this->isResponsible($userId, (string)($voice['slug'] ?? ''));
    }

    public function getUserVoiceProfile(int $userId, int $voiceId): ?array
    {
        return $this->profiles->getUserProfile($userId, $voiceId);
    }

    /**
     * Convenience for callers that only have a voice slug: loads the voice and
     * checks access. Returns false for unknown voices.
     */
    public function canAccessSlug(int $userId, string $voiceSlug): bool
    {
        $voice = (new VoicesRepo())->findBySlug($voiceSlug);
        if (!$voice) {
            return false;
        }
        return $this->hasVoiceAccess($userId, $voice);
    }

    /**
     * Whether the user may open the voice at all.
     */
    public function hasVoiceAccess(int $userId, array $voice): bool
    {
        if ($this->hasFullAccess($userId, $voice)) {
            return true;
        }
        $voiceId = (int)($voice['id'] ?? 0);
        if ($voiceId <= 0) {
            return false;
        }
        return $this->getUserVoiceProfile($userId, $voiceId) !== null;
    }

    /**
     * Folder ids the user may read documents from, for this voice.
     *
     * @return int[] empty array = no accessible documents (fail closed)
     */
    public function resolveAccessibleFolderIds(int $userId, array $voice): array
    {
        $voiceId = (int)($voice['id'] ?? 0);
        if ($voiceId <= 0) {
            return [];
        }

        if ($this->hasFullAccess($userId, $voice)) {
            return $this->allFolderIds($voiceId);
        }

        $profile = $this->getUserVoiceProfile($userId, $voiceId);
        if ($profile === null) {
            return [];
        }

        // Every folder that is the granted folder itself or a descendant of one,
        // resolved through the materialized path so grants inherit down the tree.
        $stmt = $this->pdo->prepare(
            'SELECT DISTINCT child.id
             FROM folder_profile_access fpa
             JOIN voice_folders granted ON granted.id = fpa.folder_id
             JOIN voice_folders child
               ON child.voice_id = granted.voice_id
              AND child.path LIKE CONCAT(granted.path, "%")
             WHERE fpa.profile_id = ? AND granted.voice_id = ?'
        );
        $stmt->execute([(int)$profile['id'], $voiceId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return int[]
     */
    private function allFolderIds(int $voiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM voice_folders WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }
}
