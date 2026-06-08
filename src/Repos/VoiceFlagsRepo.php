<?php
namespace Repos;

use App\DB;
use PDO;

class VoiceFlagsRepo
{
    private PDO $pdo;

    private const VALID_TYPES = ['missing_info', 'incorrect', 'other'];
    private const VALID_STATUSES = ['open', 'in_progress', 'resolved', 'dismissed'];

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /**
     * Crea un flag. Devuelve el id insertado.
     */
    public function create(array $data): int
    {
        $type = in_array($data['type'] ?? '', self::VALID_TYPES, true) ? $data['type'] : 'missing_info';
        $voiceSlug = isset($data['voice_slug']) && $data['voice_slug'] !== '' ? (string)$data['voice_slug'] : null;

        $stmt = $this->pdo->prepare('
            INSERT INTO voice_flags
                (voice_slug, raised_by_user_id, conversation_id, message_id, type, note, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, "open", NOW(), NOW())
        ');
        $stmt->execute([
            $voiceSlug,
            isset($data['raised_by_user_id']) ? (int)$data['raised_by_user_id'] : null,
            isset($data['conversation_id']) && $data['conversation_id'] ? (int)$data['conversation_id'] : null,
            isset($data['message_id']) && $data['message_id'] ? (int)$data['message_id'] : null,
            $type,
            isset($data['note']) ? trim((string)$data['note']) : null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Flags de las voces de las que el usuario es responsable.
     */
    public function listForResponsible(int $userId, array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = '
            SELECT ' . $this->selectColumns() . '
            FROM voice_flags f
            INNER JOIN voice_responsibles vr ON vr.voice_slug = f.voice_slug AND vr.user_id = ?
            ' . $this->joins() . '
            WHERE 1=1 ' . $where . '
            ORDER BY (f.status = "open") DESC, f.created_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(array_merge([$userId], $params));
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Todos los flags (vista admin).
     */
    public function listAll(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = '
            SELECT ' . $this->selectColumns() . '
            FROM voice_flags f
            ' . $this->joins() . '
            WHERE 1=1 ' . $where . '
            ORDER BY (f.status = "open") DESC, f.created_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Flags de voces que SÍ tienen responsable asignado (lista principal del admin,
     * para no duplicar con la bandeja "sin asignar").
     */
    public function listAssigned(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = '
            SELECT ' . $this->selectColumns() . '
            FROM voice_flags f
            ' . $this->joins() . '
            WHERE 1=1 ' . $where . '
              AND f.voice_slug IS NOT NULL
              AND EXISTS (
                  SELECT 1 FROM voice_responsibles vr WHERE vr.voice_slug = f.voice_slug
              )
            ORDER BY (f.status = "open") DESC, f.created_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Flags de voces sin responsable asignado (o sin voz). Bandeja "sin asignar" para admins.
     */
    public function listUnassigned(array $filters = []): array
    {
        [$where, $params] = $this->buildFilters($filters);
        $sql = '
            SELECT ' . $this->selectColumns() . '
            FROM voice_flags f
            ' . $this->joins() . '
            WHERE 1=1 ' . $where . '
              AND (f.voice_slug IS NULL OR NOT EXISTS (
                  SELECT 1 FROM voice_responsibles vr WHERE vr.voice_slug = f.voice_slug
              ))
            ORDER BY (f.status = "open") DESC, f.created_at DESC
        ';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $this->normalizeRows($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function get(int $id): ?array
    {
        $stmt = $this->pdo->prepare('
            SELECT ' . $this->selectColumns() . '
            FROM voice_flags f
            ' . $this->joins() . '
            WHERE f.id = ?
            LIMIT 1
        ');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? $this->normalizeRow($row) : null;
    }

    /**
     * Cambia el estado de un flag. Si pasa a resolved/dismissed, sella quién y nota.
     */
    public function updateStatus(int $id, string $status, ?int $resolvedBy = null, ?string $note = null): bool
    {
        if (!in_array($status, self::VALID_STATUSES, true)) {
            return false;
        }
        $isClosing = in_array($status, ['resolved', 'dismissed'], true);
        $stmt = $this->pdo->prepare('
            UPDATE voice_flags
            SET status = ?,
                resolved_by_user_id = ?,
                resolution_note = ?,
                updated_at = NOW()
            WHERE id = ?
        ');
        $stmt->execute([
            $status,
            $isClosing ? $resolvedBy : null,
            $isClosing ? ($note !== null ? trim($note) : null) : null,
            $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * ¿Es el usuario responsable de la voz de este flag? (para autorizar acciones)
     */
    public function userCanManage(int $flagId, int $userId): bool
    {
        $stmt = $this->pdo->prepare('
            SELECT 1
            FROM voice_flags f
            INNER JOIN voice_responsibles vr ON vr.voice_slug = f.voice_slug
            WHERE f.id = ? AND vr.user_id = ?
            LIMIT 1
        ');
        $stmt->execute([$flagId, $userId]);
        return (bool)$stmt->fetchColumn();
    }

    /**
     * Nº de flags abiertos de las voces del usuario (para el badge del sidebar).
     */
    public function countOpenForResponsible(int $userId): int
    {
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*)
            FROM voice_flags f
            INNER JOIN voice_responsibles vr ON vr.voice_slug = f.voice_slug AND vr.user_id = ?
            WHERE f.status = "open"
        ');
        $stmt->execute([$userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Nº de flags abiertos en total (badge admin).
     */
    public function countOpenAll(): int
    {
        return (int)$this->pdo->query('SELECT COUNT(*) FROM voice_flags WHERE status = "open"')->fetchColumn();
    }

    /**
     * ¿El usuario es responsable de al menos una voz? (gate de acceso al panel)
     */
    public function isResponsibleForAnyVoice(int $userId): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM voice_responsibles WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    // ---- helpers ----

    private function selectColumns(): string
    {
        return '
            f.id, f.voice_slug, f.raised_by_user_id, f.conversation_id, f.message_id,
            f.type, f.note, f.status, f.resolved_by_user_id, f.resolution_note,
            f.created_at, f.updated_at,
            COALESCE(v.name, f.voice_slug) AS voice_name,
            ru.first_name AS raiser_first_name, ru.last_name AS raiser_last_name, ru.email AS raiser_email,
            su.first_name AS solver_first_name, su.last_name AS solver_last_name,
            m.content AS message_content
        ';
    }

    private function joins(): string
    {
        return '
            LEFT JOIN voices v ON v.slug = f.voice_slug
            LEFT JOIN users ru ON ru.id = f.raised_by_user_id
            LEFT JOIN users su ON su.id = f.resolved_by_user_id
            LEFT JOIN messages m ON m.id = f.message_id
        ';
    }

    private function buildFilters(array $filters): array
    {
        $where = '';
        $params = [];
        if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES, true)) {
            $where .= ' AND f.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['voice_slug'])) {
            $where .= ' AND f.voice_slug = ?';
            $params[] = (string)$filters['voice_slug'];
        }
        return [$where, $params];
    }

    private function normalizeRows(array $rows): array
    {
        return array_map(fn(array $row): array => $this->normalizeRow($row), $rows);
    }

    private function normalizeRow(array $row): array
    {
        $raiserName = trim((string)($row['raiser_first_name'] ?? '') . ' ' . (string)($row['raiser_last_name'] ?? ''));
        $solverName = trim((string)($row['solver_first_name'] ?? '') . ' ' . (string)($row['solver_last_name'] ?? ''));
        return [
            'id' => (int)$row['id'],
            'voice_slug' => $row['voice_slug'] !== null ? (string)$row['voice_slug'] : null,
            'voice_name' => (string)($row['voice_name'] ?? ''),
            'type' => (string)$row['type'],
            'note' => $row['note'] !== null ? (string)$row['note'] : '',
            'status' => (string)$row['status'],
            'conversation_id' => $row['conversation_id'] !== null ? (int)$row['conversation_id'] : null,
            'message_id' => $row['message_id'] !== null ? (int)$row['message_id'] : null,
            'message_content' => $row['message_content'] !== null ? (string)$row['message_content'] : '',
            'raised_by' => [
                'id' => $row['raised_by_user_id'] !== null ? (int)$row['raised_by_user_id'] : null,
                'name' => $raiserName !== '' ? $raiserName : (string)($row['raiser_email'] ?? 'Unknown'),
                'email' => (string)($row['raiser_email'] ?? ''),
            ],
            'resolved_by' => $row['resolved_by_user_id'] !== null ? [
                'id' => (int)$row['resolved_by_user_id'],
                'name' => $solverName,
            ] : null,
            'resolution_note' => $row['resolution_note'] !== null ? (string)$row['resolution_note'] : '',
            'created_at' => (string)$row['created_at'],
            'updated_at' => (string)$row['updated_at'],
        ];
    }
}
