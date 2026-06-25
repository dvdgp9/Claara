<?php
namespace Repos;

use App\DB;
use PDO;

/**
 * Folder tree for a voice's knowledge documents.
 *
 * Each folder stores a materialized id-path including itself, e.g. '/12/' for a
 * root and '/12/30/' for its child. Descendants (inclusive) of a folder G are
 * every folder whose `path` starts with `G.path`. This is what lets a profile
 * grant on a folder inherit down to everything beneath it.
 */
class VoiceFoldersRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM voice_folders WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Returns the voice's root folder id, creating it the first time.
     */
    public function ensureRootFolder(int $voiceId, string $name = 'General'): int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM voice_folders WHERE voice_id = ? AND is_root = 1 ORDER BY id LIMIT 1'
        );
        $stmt->execute([$voiceId]);
        $existing = $stmt->fetch();
        if ($existing) {
            return (int)$existing['id'];
        }

        $this->pdo->prepare(
            'INSERT INTO voice_folders (voice_id, parent_id, name, path, depth, is_root, sort_order)
             VALUES (?, NULL, ?, "/", 0, 1, 0)'
        )->execute([$voiceId, $name]);

        $id = (int)$this->pdo->lastInsertId();
        $this->pdo->prepare('UPDATE voice_folders SET path = ? WHERE id = ?')
            ->execute(['/' . $id . '/', $id]);

        return $id;
    }

    /**
     * Creates a child folder under $parentId (or under the voice root when null)
     * and returns its id. Folder names are unique among siblings; an existing
     * sibling with the same name is reused so repeated structure uploads are
     * idempotent.
     */
    public function create(int $voiceId, ?int $parentId, string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('folder name is required');
        }

        if ($parentId === null) {
            $parentId = $this->ensureRootFolder($voiceId);
        }

        $parent = $this->getById($parentId);
        if (!$parent || (int)$parent['voice_id'] !== $voiceId) {
            throw new \InvalidArgumentException('parent folder does not belong to this voice');
        }

        $existing = $this->findChildByName($voiceId, $parentId, $name);
        if ($existing !== null) {
            return $existing;
        }

        $depth = (int)$parent['depth'] + 1;
        $this->pdo->prepare(
            'INSERT INTO voice_folders (voice_id, parent_id, name, path, depth, is_root, sort_order)
             VALUES (?, ?, ?, "/", ?, 0, 0)'
        )->execute([$voiceId, $parentId, $name, $depth]);

        $id = (int)$this->pdo->lastInsertId();
        $path = (string)$parent['path'] . $id . '/';
        $this->pdo->prepare('UPDATE voice_folders SET path = ? WHERE id = ?')->execute([$path, $id]);

        return $id;
    }

    public function findChildByName(int $voiceId, int $parentId, string $name): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM voice_folders WHERE voice_id = ? AND parent_id = ? AND name = ? LIMIT 1'
        );
        $stmt->execute([$voiceId, $parentId, $name]);
        $row = $stmt->fetch();
        return $row ? (int)$row['id'] : null;
    }

    /**
     * Ensures a nested path like ['Legal', 'Contracts', '2024'] exists under the
     * voice root, creating missing levels, and returns the leaf folder id.
     * Used by folder-structure uploads.
     */
    public function ensurePath(int $voiceId, array $segments): int
    {
        $parentId = $this->ensureRootFolder($voiceId);
        foreach ($segments as $segment) {
            $segment = trim((string)$segment);
            if ($segment === '' || $segment === '.' || $segment === '..') {
                continue;
            }
            $parentId = $this->create($voiceId, $parentId, $segment);
        }
        return $parentId;
    }

    public function listByVoice(int $voiceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM voice_folders WHERE voice_id = ? ORDER BY depth, sort_order, name'
        );
        $stmt->execute([$voiceId]);
        return $stmt->fetchAll();
    }

    /**
     * @return int[] every folder id belonging to the voice
     */
    public function allIdsForVoice(int $voiceId): array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM voice_folders WHERE voice_id = ?');
        $stmt->execute([$voiceId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    public function rename(int $id, string $name): void
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('folder name is required');
        }
        $this->pdo->prepare('UPDATE voice_folders SET name = ? WHERE id = ?')->execute([$name, $id]);
    }

    /**
     * Sets the minimum access level required to read a folder. NULL = everyone.
     */
    public function setRequiredLevel(int $id, ?int $levelId): void
    {
        $this->pdo->prepare('UPDATE voice_folders SET required_level_id = ? WHERE id = ?')
            ->execute([$levelId, $id]);
    }

    /**
     * Ids of a folder and all its descendants (via the materialized path).
     *
     * @return int[]
     */
    public function subtreeIds(array $folder): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM voice_folders WHERE voice_id = ? AND path LIKE ?'
        );
        $stmt->execute([(int)$folder['voice_id'], (string)$folder['path'] . '%']);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN) ?: []);
    }

    /**
     * Deletes a folder. Descendant folders and profile grants are removed by the
     * ON DELETE CASCADE foreign keys. Callers must reassign documents first
     * (context_documents.folder_id has no FK by design).
     */
    public function delete(int $id): void
    {
        $this->pdo->prepare('DELETE FROM voice_folders WHERE id = ?')->execute([$id]);
    }
}
