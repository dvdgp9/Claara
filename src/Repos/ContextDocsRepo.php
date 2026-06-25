<?php
namespace Repos;

use App\DB;
use PDO;
use Throwable;

/**
 * Repository for managing context documents.
 *
 * Supports three targets:
 * - lex: indexed documents for the legal voice
 * - eboniato: Claara quick-answer documents
 * - ebonia: general chat context documents
 */
class ContextDocsRepo
{
    private PDO $pdo;
    private static ?array $contextDocsColumns = null;

    /** Physical paths by target (relative to project root). */
    private const TARGET_PATHS = [
        'lex' => 'docs/context/voices/lex/knowledge-base',
        'eboniato' => 'docs/context_faq',
        'ebonia' => 'docs/context',
    ];

    /** Allowed extensions by target. */
    private const ALLOWED_EXTENSIONS = [
        'lex' => ['pdf', 'txt', 'md'],
        'eboniato' => ['md'],
        'ebonia' => ['md'],
    ];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /**
     * Lista todos los documentos de un target
     */
    public function listByTarget(string $target): array
    {
        $slugFilter = '';
        $params = [$target];
        if ($this->contextDocsHasColumn('target_slug')) {
            $slugFilter = ' AND (cd.target_slug = ? OR cd.target_slug IS NULL)';
            $params[] = $target;
        }

        $stmt = $this->pdo->prepare('
            SELECT cd.*, u.first_name, u.last_name, u.email as created_by_email
            FROM context_documents cd
            LEFT JOIN users u ON u.id = cd.created_by
            WHERE cd.target = ?' . $slugFilter . '
            ORDER BY cd.filename ASC
        ');
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public function listByVoice(string $slug): array
    {
        if ($this->contextDocsHasColumn('target_type') && $this->contextDocsHasColumn('target_slug')) {
            $stmt = $this->pdo->prepare('
                SELECT cd.*, u.first_name, u.last_name, u.email as created_by_email
                FROM context_documents cd
                LEFT JOIN users u ON u.id = cd.created_by
                WHERE cd.target_type = "voice" AND cd.target_slug = ?
                ORDER BY cd.filename ASC
            ');
            $stmt->execute([$slug]);
            return $stmt->fetchAll() ?: [];
        }

        return $slug === 'lex' ? $this->listByTarget('lex') : [];
    }

    /**
     * Document count per folder for a voice.
     *
     * @return array<int,int> folder_id => count
     */
    public function countByFolder(string $slug): array
    {
        if (!$this->contextDocsHasColumn('folder_id')) {
            return [];
        }
        $stmt = $this->pdo->prepare(
            'SELECT folder_id, COUNT(*) AS n FROM context_documents
              WHERE target_slug = ? AND folder_id IS NOT NULL
              GROUP BY folder_id'
        );
        $stmt->execute([$slug]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(int)$row['folder_id']] = (int)$row['n'];
        }
        return $out;
    }

    /**
     * Moves a single document into a folder.
     */
    public function setFolder(int $id, int $folderId): bool
    {
        if (!$this->contextDocsHasColumn('folder_id')) {
            return false;
        }
        $stmt = $this->pdo->prepare('UPDATE context_documents SET folder_id = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$folderId, $id]);
    }

    /**
     * Reassigns every document currently in any of $fromFolderIds to $toFolderId.
     * Used when deleting a folder subtree so documents are not orphaned.
     *
     * @param int[] $fromFolderIds
     * @return int rows affected
     */
    public function moveDocsToFolder(array $fromFolderIds, int $toFolderId): int
    {
        if (!$this->contextDocsHasColumn('folder_id') || empty($fromFolderIds)) {
            return 0;
        }
        $ids = array_values(array_map('intval', $fromFolderIds));
        $in = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare(
            "UPDATE context_documents SET folder_id = ?, updated_at = NOW() WHERE folder_id IN ($in)"
        );
        $stmt->execute(array_merge([$toFolderId], $ids));
        return $stmt->rowCount();
    }

    /**
     * Set of document filenames for a voice, restricted to a list of folder ids.
     *
     * Used as the join key between the filesystem document listing and the
     * folder-based access model. Keys are normalized (lowercased basename) for
     * fast membership checks.
     *
     * @param int[]|null $allowedFolderIds null = no restriction (all documents);
     *        [] = no accessible folders, returns an empty set (fail closed).
     * @return array<string,bool>
     */
    public function accessibleFilenameSet(string $slug, ?array $allowedFolderIds): array
    {
        $set = [];
        $hasFolderColumn = $this->contextDocsHasColumn('folder_id');

        foreach ($this->listByVoice($slug) as $doc) {
            if ($allowedFolderIds !== null) {
                $folderId = $hasFolderColumn && $doc['folder_id'] !== null ? (int)$doc['folder_id'] : 0;
                if (!in_array($folderId, array_map('intval', $allowedFolderIds), true)) {
                    continue;
                }
            }
            foreach ([(string)($doc['filename'] ?? ''), (string)($doc['original_filename'] ?? '')] as $name) {
                $key = self::normalizeDocFilename($name);
                if ($key !== '') {
                    $set[$key] = true;
                }
            }
        }

        return $set;
    }

    /**
     * Normalizes a document filename for folder-access membership checks.
     */
    public static function normalizeDocFilename(string $name): string
    {
        return mb_strtolower(trim(basename($name)));
    }

    /**
     * Obtiene un documento por ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT cd.*, u.first_name, u.last_name, u.email as created_by_email
            FROM context_documents cd
            LEFT JOIN users u ON u.id = cd.created_by
            WHERE cd.id = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene un documento por target y filename
     */
    public function getByFilename(string $target, string $filename): ?array
    {
        $slugFilter = '';
        $params = [$target, $filename];
        if ($this->contextDocsHasColumn('target_slug')) {
            $slugFilter = ' AND (target_slug = ? OR target_slug IS NULL)';
            $params[] = $target;
        }

        $stmt = $this->pdo->prepare('
            SELECT * FROM context_documents
            WHERE target = ? AND filename = ?' . $slugFilter . '
            LIMIT 1
        ');
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Crea un nuevo registro de documento
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');

        $isVoice = ($data['target_type'] ?? '') === 'voice' || ($data['target'] ?? '') === 'lex';
        $ragStatus = $isVoice ? 'pending' : 'not_applicable';

        $fields = ['target', 'filename', 'original_filename', 'file_extension', 'file_size', 'status', 'rag_status', 'description'];
        $values = [
            $data['target'],
            $data['filename'],
            $data['original_filename'],
            $data['file_extension'],
            $data['file_size'] ?? 0,
            $data['status'] ?? 'active',
            $ragStatus,
            $data['description'] ?? null,
        ];

        foreach (['document_date', 'is_official_source', 'source_authority'] as $field) {
            if ($this->contextDocsHasColumn($field)) {
                $fields[] = $field;
                $values[] = $data[$field] ?? ($field === 'is_official_source' ? 0 : null);
            }
        }

        if ($this->contextDocsHasColumn('target_type')) {
            $fields[] = 'target_type';
            $values[] = $data['target_type'] ?? $this->inferTargetType((string)$data['target']);
        }
        if ($this->contextDocsHasColumn('target_slug')) {
            $fields[] = 'target_slug';
            $values[] = $data['target_slug'] ?? (string)$data['target'];
        }
        if ($this->contextDocsHasColumn('voice_id')) {
            $fields[] = 'voice_id';
            $values[] = $data['voice_id'] ?? null;
        }
        if ($this->contextDocsHasColumn('folder_id')) {
            $fields[] = 'folder_id';
            $values[] = isset($data['folder_id']) ? (int)$data['folder_id'] : null;
        }

        $fields[] = 'created_by';
        $fields[] = 'created_at';
        $fields[] = 'updated_at';
        $values[] = $data['created_by'];
        $values[] = $now;
        $values[] = $now;

        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $stmt = $this->pdo->prepare('
            INSERT INTO context_documents
            (' . implode(', ', $fields) . ')
            VALUES (' . $placeholders . ')
        ');

        $stmt->execute($values);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualiza metadatos de un documento
     */
    public function update(int $id, array $data): bool
    {
        $fields = [];
        $values = [];

        $allowedFields = ['filename', 'original_filename', 'file_size', 'status', 'description'];
        foreach (['document_date', 'is_official_source', 'source_authority'] as $field) {
            if ($this->contextDocsHasColumn($field)) {
                $allowedFields[] = $field;
            }
        }

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = ?";
                $values[] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = 'updated_at = ?';
        $values[] = date('Y-m-d H:i:s');
        $values[] = $id;

        $sql = 'UPDATE context_documents SET ' . implode(', ', $fields) . ' WHERE id = ?';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Updates index status fields for a document.
     */
    public function updateRagStatus(int $id, string $ragStatus, ?int $chunkCount = null, ?string $errorMessage = null): bool
    {
        $sql = 'UPDATE context_documents SET rag_status = ?, updated_at = ?';
        $values = [$ragStatus, date('Y-m-d H:i:s')];

        if ($chunkCount !== null) {
            $sql .= ', rag_chunk_count = ?';
            $values[] = $chunkCount;
        }

        if ($errorMessage !== null) {
            $sql .= ', rag_error_message = ?';
            $values[] = $errorMessage;
        } elseif ($ragStatus !== 'error') {
            $sql .= ', rag_error_message = NULL';
        }

        $sql .= ' WHERE id = ?';
        $values[] = $id;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($values);
    }

    /**
     * Elimina un documento
     */
    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM context_documents WHERE id = ?');
        return $stmt->execute([$id]);
    }

    /**
     * Obtiene estadísticas de un target
     */
    public function getStatsByTarget(string $target): array
    {
        $hasFileSize = $this->contextDocsHasColumn('file_size');
        $hasStatus = $this->contextDocsHasColumn('status');
        $hasRagChunkCount = $this->contextDocsHasColumn('rag_chunk_count');
        $hasRagStatus = $this->contextDocsHasColumn('rag_status');

        $selectParts = [
            'COUNT(*) as total_documents',
            $hasFileSize ? 'SUM(file_size) as total_size' : '0 as total_size',
            $hasRagChunkCount ? 'SUM(rag_chunk_count) as total_chunks' : '0 as total_chunks',
            $hasStatus ? 'SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) as active_count' : 'COUNT(*) as active_count',
            $hasRagStatus ? 'SUM(CASE WHEN rag_status = "processed" THEN 1 ELSE 0 END) as rag_processed_count' : '0 as rag_processed_count',
            $hasRagStatus ? 'SUM(CASE WHEN rag_status = "pending" THEN 1 ELSE 0 END) as rag_pending_count' : '0 as rag_pending_count',
            $hasRagStatus ? 'SUM(CASE WHEN rag_status = "error" THEN 1 ELSE 0 END) as rag_error_count' : '0 as rag_error_count',
        ];

        try {
            $slugFilter = '';
            $params = [$target];
            if ($this->contextDocsHasColumn('target_slug')) {
                $slugFilter = ' AND (target_slug = ? OR target_slug IS NULL)';
                $params[] = $target;
            }

            $stmt = $this->pdo->prepare('
                SELECT
                    ' . implode(",\n                    ", $selectParts) . '
                FROM context_documents
                WHERE target = ?' . $slugFilter . '
            ');
            $stmt->execute($params);
            $row = $stmt->fetch();
        } catch (Throwable $e) {
            $slugFilter = '';
            $params = [$target];
            if ($this->contextDocsHasColumn('target_slug')) {
                $slugFilter = ' AND (target_slug = ? OR target_slug IS NULL)';
                $params[] = $target;
            }
            $stmt = $this->pdo->prepare('
                SELECT
                    COUNT(*) as total_documents,
                    0 as total_size,
                    0 as total_chunks,
                    COUNT(*) as active_count,
                    0 as rag_processed_count,
                    0 as rag_pending_count,
                    0 as rag_error_count
                FROM context_documents
                WHERE target = ?' . $slugFilter . '
            ');
            $stmt->execute($params);
            $row = $stmt->fetch();
        }

        return [
            'total_documents' => (int)($row['total_documents'] ?? 0),
            'total_size' => (int)($row['total_size'] ?? 0),
            'total_chunks' => (int)($row['total_chunks'] ?? 0),
            'active_count' => (int)($row['active_count'] ?? 0),
            'rag_processed_count' => (int)($row['rag_processed_count'] ?? 0),
            'rag_pending_count' => (int)($row['rag_pending_count'] ?? 0),
            'rag_error_count' => (int)($row['rag_error_count'] ?? 0),
        ];
    }

    /**
     * Obtiene la ruta física del directorio para un target
     */
    public static function getTargetPath(string $target): ?string
    {
        if (!isset(self::TARGET_PATHS[$target])) {
            return null;
        }

        $basePath = dirname(dirname(__DIR__));
        return $basePath . '/' . self::TARGET_PATHS[$target];
    }

    public static function getVoicePath(string $slug): string
    {
        $safeSlug = preg_replace('/[^a-z0-9-]/', '', strtolower($slug));
        $basePath = dirname(dirname(__DIR__));
        return $basePath . '/docs/context/voices/' . $safeSlug . '/knowledge-base';
    }

    /**
     * Obtiene las extensiones permitidas para un target
     */
    public static function getAllowedExtensions(string $target): array
    {
        if (str_starts_with($target, 'voice:')) {
            return ['pdf', 'txt', 'md'];
        }
        return self::ALLOWED_EXTENSIONS[$target] ?? [];
    }

    /**
     * Verifica si una extensión es válida para un target
     */
    public static function isExtensionAllowed(string $target, string $extension): bool
    {
        $allowed = self::getAllowedExtensions($target);
        return in_array(strtolower($extension), $allowed, true);
    }

    /**
     * Sanitiza un nombre de archivo
     */
    public static function sanitizeFilename(string $filename): string
    {
        // Obtener extensión
        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Remover caracteres peligrosos, mantener solo alfanuméricos, guiones, underscores y puntos
        $name = preg_replace('/[^a-zA-Z0-9_\-\.\s]/', '', $name);
        $name = preg_replace('/\s+/', '_', $name);
        $name = trim($name, '._-');

        // Limitar longitud
        if (strlen($name) > 200) {
            $name = substr($name, 0, 200);
        }

        // Si el nombre quedó vacío, usar timestamp
        if (empty($name)) {
            $name = 'document_' . time();
        }

        return $name . '.' . strtolower($ext);
    }

    /**
     * Genera un nombre único si ya existe
     */
    public function generateUniqueFilename(string $target, string $filename): string
    {
        $sanitized = self::sanitizeFilename($filename);
        $existing = $this->getByFilename($target, $sanitized);

        if (!$existing) {
            return $sanitized;
        }

        // Añadir sufijo numérico
        $ext = pathinfo($sanitized, PATHINFO_EXTENSION);
        $name = pathinfo($sanitized, PATHINFO_FILENAME);

        $counter = 1;
        do {
            $newFilename = "{$name}_{$counter}.{$ext}";
            $existing = $this->getByFilename($target, $newFilename);
            $counter++;
        } while ($existing && $counter < 100);

        return $newFilename;
    }

    /**
     * Lista todos los targets válidos
     */
    public static function getValidTargets(): array
    {
        return array_keys(self::TARGET_PATHS);
    }

    /**
     * Verifica si un target es válido
     */
    public static function isValidTarget(string $target): bool
    {
        if (str_starts_with($target, 'voice:')) {
            return (bool)preg_match('/^voice:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $target);
        }
        return isset(self::TARGET_PATHS[$target]);
    }

    private function contextDocsHasColumn(string $column): bool
    {
        if (self::$contextDocsColumns === null) {
            self::$contextDocsColumns = [];
            try {
                $rows = $this->pdo->query('SHOW COLUMNS FROM context_documents')->fetchAll();
                foreach ($rows as $row) {
                    if (!empty($row['Field'])) {
                        self::$contextDocsColumns[$row['Field']] = true;
                    }
                }
            } catch (Throwable $e) {
                self::$contextDocsColumns = [];
            }
        }

        return !empty(self::$contextDocsColumns[$column]);
    }

    private function inferTargetType(string $target): string
    {
        if ($target === 'lex') {
            return 'voice';
        }
        if ($target === 'eboniato') {
            return 'faq';
        }
        if ($target === 'ebonia') {
            return 'chat';
        }
        return 'legacy';
    }
}
