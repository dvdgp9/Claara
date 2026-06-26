<?php
namespace Voices;

use App\DB;
use PDO;
use Repos\AccessLevelsRepo;
use Repos\VoiceAccessListRepo;
use Repos\VoicesRepo;

/**
 * Resolves, for a given user and voice, whether they can access the voice and
 * exactly which document folders they may read from.
 *
 * Access model — GLOBAL LEVELS:
 *   - Each user has ONE global access level (users.access_level_id), ranked.
 *   - Each voice declares an access policy:
 *       * 'level': enter if the user's level rank >= the voice's minimum level
 *                  rank. min_access_level_id NULL = everyone.
 *       * 'list' : enter only if explicitly on the voice's allow-list.
 *   - Each folder declares a minimum global level (voice_folders.required_level_id,
 *     NULL = everyone). In 'level' mode a folder is readable when the user's rank
 *     reaches it; in 'list' mode a listed user reads every folder of the voice.
 *
 * Security contract — FAIL CLOSED:
 *   - resolveAccessibleFolderIds() returning [] must be treated by callers as
 *     "no accessible documents", never as "no filter / show everything".
 *
 * Bypass (full access to every folder): superadmins and users responsible for
 * the voice.
 */
class VoiceAccessResolver
{
    private PDO $pdo;
    private AccessLevelsRepo $levels;
    private VoiceAccessListRepo $list;

    public function __construct(
        ?PDO $pdo = null,
        ?AccessLevelsRepo $levels = null,
        ?VoiceAccessListRepo $list = null
    ) {
        $this->pdo = $pdo ?? DB::pdo();
        $this->levels = $levels ?? new AccessLevelsRepo($this->pdo);
        $this->list = $list ?? new VoiceAccessListRepo($this->pdo);
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
     * Full bypass means "see every folder regardless of policy".
     */
    public function hasFullAccess(int $userId, array $voice): bool
    {
        return $this->isSuperadmin($userId)
            || $this->isResponsible($userId, (string)($voice['slug'] ?? ''));
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

        $policy = $this->voicePolicy($voiceId);
        if ($policy['mode'] === 'list') {
            return $this->list->isListed($userId, $voiceId);
        }

        // 'level' mode.
        if ($policy['min_rank'] === null) {
            return true; // everyone
        }
        return $this->userRank($userId) >= $policy['min_rank'];
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

        $policy = $this->voicePolicy($voiceId);

        if ($policy['mode'] === 'list') {
            // A listed user reads every folder of the voice; otherwise nothing.
            return $this->list->isListed($userId, $voiceId)
                ? $this->allFolderIds($voiceId)
                : [];
        }

        // 'level' mode: must clear the voice minimum first (fail closed).
        if (!$this->hasVoiceAccess($userId, $voice)) {
            return [];
        }

        // A folder is readable when the user's rank is at least the folder's
        // required rank. NULL required level = everyone (rank 0).
        $userRank = $this->userRank($userId);
        $stmt = $this->pdo->prepare(
            'SELECT f.id
             FROM voice_folders f
             LEFT JOIN access_levels req ON req.id = f.required_level_id
             WHERE f.voice_id = ? AND COALESCE(req.`rank`, 0) <= ?'
        );
        $stmt->execute([$voiceId, $userRank]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * @return array{mode:string,min_rank:?int}
     */
    private function voicePolicy(int $voiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT v.access_mode, al.`rank` AS min_rank, v.min_access_level_id
             FROM voices v
             LEFT JOIN access_levels al ON al.id = v.min_access_level_id
             WHERE v.id = ?'
        );
        $stmt->execute([$voiceId]);
        $row = $stmt->fetch();
        if (!$row) {
            // Unknown voice: fail closed as an empty list.
            return ['mode' => 'list', 'min_rank' => null];
        }
        $mode = ($row['access_mode'] ?? 'level') === 'list' ? 'list' : 'level';
        // min_rank is null only when no minimum level is set (everyone).
        $minRank = $row['min_access_level_id'] === null ? null : (int)$row['min_rank'];
        return ['mode' => $mode, 'min_rank' => $minRank];
    }

    /** The user's global level rank, 0 if they have none. */
    private function userRank(int $userId): int
    {
        $level = $this->levels->getUserLevel($userId);
        return $level ? (int)$level['rank'] : 0;
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
