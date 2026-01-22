<?php

declare(strict_types=1);

namespace Gestures;

use App\DB;
use PDO;

/**
 * Repositorio para gestionar el historial de ejecuciones de gestos
 */
class GestureExecutionsRepo
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = DB::pdo();
    }

    /**
     * Crea una nueva ejecución de gesto
     */
    public function create(array $data): int
    {
        $sql = "INSERT INTO gesture_executions 
                (user_id, gesture_type, title, input_data, output_content, output_data, content_type, business_line, model, created_at, updated_at)
                VALUES (:user_id, :gesture_type, :title, :input_data, :output_content, :output_data, :content_type, :business_line, :model, NOW(), NOW())";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            'user_id' => $data['user_id'],
            'gesture_type' => $data['gesture_type'],
            'title' => $data['title'],
            'input_data' => json_encode($data['input_data'] ?? [], JSON_UNESCAPED_UNICODE),
            'output_content' => $data['output_content'],
            'output_data' => isset($data['output_data']) ? json_encode($data['output_data'], JSON_UNESCAPED_UNICODE) : null,
            'content_type' => $data['content_type'] ?? null,
            'business_line' => $data['business_line'] ?? null,
            'model' => $data['model'] ?? null,
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Obtiene una ejecución por ID
     */
    public function findById(int $id): ?array
    {
        $sql = "SELECT * FROM gesture_executions WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return null;
        
        $row['input_data'] = json_decode($row['input_data'] ?? '{}', true);
        $row['output_data'] = json_decode($row['output_data'] ?? '{}', true);
        return $row;
    }

    /**
     * Lista ejecuciones de un usuario para un tipo de gesto específico
     */
    public function listByUserAndType(int $userId, string $gestureType, int $limit = 20): array
    {
        $sql = "SELECT id, title, model, content_type, business_line, is_favorite, input_data, created_at
                FROM gesture_executions 
                WHERE user_id = :user_id AND gesture_type = :gesture_type
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('gesture_type', $gestureType, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decodificar input_data para cada fila
        foreach ($rows as &$row) {
            if (isset($row['input_data'])) {
                $row['input_data'] = json_decode($row['input_data'], true) ?? [];
            }
        }
        
        return $rows;
    }

    /**
     * Lista todas las ejecuciones de un usuario
     */
    public function listByUser(int $userId, int $limit = 50): array
    {
        $sql = "SELECT id, gesture_type, title, content_type, business_line, is_favorite, created_at
                FROM gesture_executions 
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Elimina una ejecución (solo si pertenece al usuario)
     * Implementa borrado en cascada para materiales complementarios del Course Creator
     */
    public function delete(int $id, int $userId): bool
    {
        // 1. Si es un curso del Course Creator, buscamos materiales que lo tengan como fuente
        $sqlCheck = "SELECT content_type FROM gesture_executions WHERE id = :id";
        $stmtCheck = $this->pdo->prepare($sqlCheck);
        $stmtCheck->execute(['id' => $id]);
        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if ($row && in_array($row['content_type'], ['course_outline', 'course_developed'])) {
            // Es un curso base, buscar materiales hijos
            // Nota: Usamos JSON_EXTRACT o LIKE para encontrar el source_execution_id en input_data
            $sqlDeleteChildren = "DELETE FROM gesture_executions 
                                WHERE user_id = :user_id 
                                AND content_type LIKE 'course_material_%'
                                AND (
                                    JSON_EXTRACT(input_data, '$.source_execution_id') = :id
                                    OR input_data LIKE :id_like
                                )";
            $stmtChildren = $this->pdo->prepare($sqlDeleteChildren);
            $stmtChildren->execute([
                'user_id' => $userId,
                'id' => $id,
                'id_like' => '%"source_execution_id":' . $id . '%'
            ]);
        }

        // 2. Eliminar el elemento principal
        $sql = "DELETE FROM gesture_executions WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Marca/desmarca como favorito
     */
    public function toggleFavorite(int $id, int $userId): bool
    {
        $sql = "UPDATE gesture_executions 
                SET is_favorite = NOT is_favorite, updated_at = NOW()
                WHERE id = :id AND user_id = :user_id";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['id' => $id, 'user_id' => $userId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtiene las últimas publicaciones originales de una línea de negocio
     */
    public function getRecentByBusinessLine(int $userId, string $gestureType, string $businessLine, int $limit = 5): array
    {
        $sql = "SELECT output_content 
                FROM gesture_executions 
                WHERE user_id = :user_id 
                  AND gesture_type = :gesture_type 
                  AND business_line = :business_line
                  AND content_type = 'original'
                ORDER BY created_at DESC 
                LIMIT :limit";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue('user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue('gesture_type', $gestureType, PDO::PARAM_STR);
        $stmt->bindValue('business_line', $businessLine, PDO::PARAM_STR);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
