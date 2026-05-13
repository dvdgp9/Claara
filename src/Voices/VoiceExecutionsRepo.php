<?php
namespace Voices;

use App\DB;
use PDO;

/**
 * Repositorio para gestionar las ejecuciones de voces (historial de chats especializados)
 */
class VoiceExecutionsRepo
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::pdo();
    }

    /**
     * Crear una nueva ejecución
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO voice_executions 
                (user_id, voice_id, title, input_data, output_content, model, created_at, updated_at)
                VALUES (:user_id, :voice_id, :title, :input_data, :output_content, :model, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'voice_id' => $data['voice_id'],
            'title' => $data['title'] ?? 'New query',
            'input_data' => json_encode($data['input_data'] ?? []),
            'output_content' => $data['output_content'] ?? '',
            'model' => $data['model'] ?? null
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Actualizar una ejecución existente
     */
    public function update(int $id, int $userId, array $data): bool
    {
        $sets = ['updated_at = NOW()'];
        $params = ['id' => $id, 'user_id' => $userId];

        if (isset($data['title'])) {
            $sets[] = 'title = :title';
            $params['title'] = $data['title'];
        }
        if (isset($data['input_data'])) {
            $sets[] = 'input_data = :input_data';
            $params['input_data'] = json_encode($data['input_data']);
        }
        if (isset($data['output_content'])) {
            $sets[] = 'output_content = :output_content';
            $params['output_content'] = $data['output_content'];
        }

        $sql = "UPDATE voice_executions SET " . implode(', ', $sets) . 
               " WHERE id = :id AND user_id = :user_id";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Listar ejecuciones de una voz para un usuario
     */
    public function listByVoice(int $userId, string $voiceId, int $limit = 50): array
    {
        $sql = "SELECT id, voice_id, title, model, created_at, updated_at
                FROM voice_executions
                WHERE user_id = :user_id AND voice_id = :voice_id
                ORDER BY updated_at DESC
                LIMIT :limit";

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('voice_id', $voiceId, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtener una ejecución por ID
     */
    public function getById(int $id, int $userId): ?array
    {
        $sql = "SELECT * FROM voice_executions WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Eliminar una ejecución
     */
    public function delete(int $id, int $userId): bool
    {
        $sql = "DELETE FROM voice_executions WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        
        return $stmt->rowCount() > 0;
    }
}
