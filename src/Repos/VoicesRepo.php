<?php
namespace Repos;

use App\DB;
use PDO;

class VoicesRepo
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    public function findBySlug(string $slug, bool $includeArchived = false): ?array
    {
        $sql = '
            SELECT *
            FROM voices
            WHERE slug = ?
        ';
        if (!$includeArchived) {
            $sql .= ' AND status != "archived"';
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->normalizeVoice($row) : null;
    }

    public function listAll(bool $includeArchived = false): array
    {
        $sql = '
            SELECT *
            FROM voices
        ';
        if (!$includeArchived) {
            $sql .= ' WHERE status != "archived"';
        }
        $sql .= ' ORDER BY status = "published" DESC, name ASC';

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn(array $row) => $this->normalizeVoice($row), $rows);
    }

    public function listPublished(): array
    {
        $stmt = $this->pdo->query('
            SELECT *
            FROM voices
            WHERE status = "published"
            ORDER BY name ASC
        ');

        return array_map(fn(array $row) => $this->normalizeVoice($row), $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    public function create(array $data): int
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO voices (
                slug, name, role, description, provider, model, system_prompt, instructions,
                trigger_guidance, status, rag_collection, icon, color, visibility, metadata,
                created_by, created_at, updated_at
            ) VALUES (
                :slug, :name, :role, :description, :provider, :model, :system_prompt, :instructions,
                :trigger_guidance, :status, :rag_collection, :icon, :color, :visibility, :metadata,
                :created_by, NOW(), NOW()
            )
        ');

        $stmt->execute([
            'slug' => $data['slug'],
            'name' => $data['name'],
            'role' => $data['role'] ?? null,
            'description' => $data['description'] ?? null,
            'provider' => $data['provider'] ?? 'other',
            'model' => $data['model'] ?? 'google/gemini-3-flash-preview',
            'system_prompt' => $data['system_prompt'] ?? null,
            'instructions' => $data['instructions'] ?? null,
            'trigger_guidance' => $data['trigger_guidance'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'rag_collection' => $data['rag_collection'] ?? ('voice_' . $data['slug']),
            'icon' => $data['icon'] ?? 'iconoir-voice-square',
            'color' => $data['color'] ?? 'slate',
            'visibility' => $data['visibility'] ?? 'global',
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata'], JSON_UNESCAPED_UNICODE) : null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function update(string $slug, array $data): bool
    {
        $allowed = [
            'name', 'role', 'description', 'provider', 'model', 'system_prompt', 'instructions',
            'trigger_guidance', 'status', 'rag_collection', 'icon', 'color', 'visibility',
            'scope_company_id', 'scope_department_id', 'scope_user_id', 'temperature', 'top_p',
            'max_output_tokens', 'metadata',
        ];

        $sets = [];
        $params = [];
        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }
            $sets[] = "{$field} = :{$field}";
            $params[$field] = $field === 'metadata' && is_array($data[$field])
                ? json_encode($data[$field], JSON_UNESCAPED_UNICODE)
                : $data[$field];
        }

        if (!$sets) {
            return true;
        }

        $sets[] = 'updated_at = NOW()';
        $params['slug'] = $slug;

        $stmt = $this->pdo->prepare('
            UPDATE voices
            SET ' . implode(', ', $sets) . '
            WHERE slug = :slug
        ');

        return $stmt->execute($params);
    }

    public function publish(string $slug): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE voices
            SET status = "published", published_at = COALESCE(published_at, NOW()), updated_at = NOW()
            WHERE slug = ?
        ');

        return $stmt->execute([$slug]);
    }

    public function archive(string $slug): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE voices
            SET status = "archived", updated_at = NOW()
            WHERE slug = ?
        ');

        return $stmt->execute([$slug]);
    }

    public function syncAvailableFeature(string $slug): bool
    {
        $voice = $this->findBySlug($slug, true);
        if (!$voice) {
            return false;
        }

        $stmt = $this->pdo->prepare('
            INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active)
            VALUES ("voice", :slug, :name, :description, :icon, 10, :is_active)
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                icon = VALUES(icon),
                is_active = VALUES(is_active)
        ');

        return $stmt->execute([
            'slug' => $voice['slug'],
            'name' => $voice['name'],
            'description' => $voice['description'] ?? '',
            'icon' => $voice['icon'] ?? 'iconoir-voice-square',
            'is_active' => $voice['status'] === 'published' ? 1 : 0,
        ]);
    }

    private function normalizeVoice(array $row): array
    {
        $metadata = [];
        if (!empty($row['metadata'])) {
            $decoded = json_decode((string)$row['metadata'], true);
            $metadata = is_array($decoded) ? $decoded : [];
        }

        $slug = (string)($row['slug'] ?? '');

        return [
            'id' => isset($row['id']) ? (int)$row['id'] : null,
            'slug' => $slug,
            'name' => (string)($row['name'] ?? $slug),
            'role' => (string)($row['role'] ?? 'Specialized Assistant'),
            'description' => (string)($row['description'] ?? ''),
            'personality' => (string)($metadata['personality'] ?? 'Professional, precise, and clear. Cite sources whenever possible.'),
            'instructions' => (string)($row['instructions'] ?? $row['system_prompt'] ?? ''),
            'trigger_guidance' => (string)($row['trigger_guidance'] ?? ''),
            'status' => (string)($row['status'] ?? 'draft'),
            'folder' => $slug,
            'rag_enabled' => true,
            'rag_collection' => (string)($row['rag_collection'] ?? ('voice_' . $slug)),
            'icon' => (string)($row['icon'] ?? 'iconoir-voice-square'),
            'color' => (string)($row['color'] ?? 'slate'),
            'provider' => (string)($row['provider'] ?? 'other'),
            'model' => (string)($row['model'] ?? 'google/gemini-3-flash-preview'),
            'metadata' => $metadata,
        ];
    }
}
