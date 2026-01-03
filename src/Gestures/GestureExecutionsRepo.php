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
     * Lista ejecuciones de un usuario para un tipo de gesto específico (optimizado para imágenes)
     */
    public function listByUserAndType(int $userId, string $gestureType, int $limit = 20): array
    {
        // En gestos de imagen, evitamos traer output_data que contiene el base64 pesado
        $sql = "SELECT id, title, input_data, content_type, business_line, is_favorite, created_at
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
        foreach ($rows as &$row) {
            $row['input_data'] = json_decode($row['input_data'] ?? '{}', true);
        }
        return $rows;
    }

    /**
     * Lista todas las ejecuciones de un usuario (optimizado)
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
     */
    public function delete(int $id, int $userId): bool
    {
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
