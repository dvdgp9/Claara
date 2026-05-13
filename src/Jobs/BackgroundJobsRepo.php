<?php

namespace Jobs;

use App\DB;
use PDO;

class BackgroundJobsRepo
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::pdo();
    }

    /**
     * Crear un nuevo job
     */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO background_jobs (user_id, job_type, status, input_data, created_at)
            VALUES (:user_id, :job_type, 'pending', :input_data, NOW())
        ");

        $stmt->execute([
            'user_id' => $data['user_id'],
            'job_type' => $data['job_type'],
            'input_data' => json_encode($data['input_data'] ?? [])
        ]);

        return (int)$this->db->lastInsertId();
    }

    /**
     * Obtener job por ID
     */
    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM background_jobs WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return null;
        
        // Decodificar JSON
        $row['input_data'] = json_decode($row['input_data'], true);
        $row['output_data'] = $row['output_data'] ? json_decode($row['output_data'], true) : null;
        
        return $row;
    }

    /**
     * Obtener jobs de un usuario por estado
     */
    public function findByUserAndStatus(int $userId, ?string $status = null): array
    {
        $sql = "SELECT * FROM background_jobs WHERE user_id = :user_id";
        $params = ['user_id' => $userId];
        
        if ($status) {
            $sql .= " AND status = :status";
            $params['status'] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as &$row) {
            $row['input_data'] = json_decode($row['input_data'], true);
            $row['output_data'] = $row['output_data'] ? json_decode($row['output_data'], true) : null;
        }
        
        return $rows;
    }

    /**
     * Contar jobs activos de un usuario (pending o processing)
     */
    public function countActiveByUser(int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM background_jobs 
            WHERE user_id = :user_id AND status IN ('pending', 'processing')
        ");
        $stmt->execute(['user_id' => $userId]);
        return (int)$stmt->fetchColumn();
    }

    /**
     * Obtener el siguiente job pendiente para procesar (FIFO)
     */
    public function getNextPending(): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM background_jobs 
            WHERE status = 'pending' 
            ORDER BY created_at ASC 
            LIMIT 1
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$row) return null;
        
        $row['input_data'] = json_decode($row['input_data'], true);
        $row['output_data'] = $row['output_data'] ? json_decode($row['output_data'], true) : null;
        
        return $row;
    }

    /**
     * Marcar job como processing
     */
    public function markProcessing(int $id, ?string $progressText = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs 
            SET status = 'processing', started_at = NOW(), progress_text = :progress_text
            WHERE id = :id AND status = 'pending'
        ");
        return $stmt->execute(['id' => $id, 'progress_text' => $progressText]);
    }

    /**
     * Actualizar texto de progreso
     */
    public function updateProgress(int $id, string $progressText): bool
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs SET progress_text = :progress_text WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'progress_text' => $progressText]);
    }

    /**
     * Update progress and partial output snapshot while job is processing.
     */
    public function updateProcessingSnapshot(int $id, string $progressText, array $outputData): bool
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs
            SET progress_text = :progress_text, output_data = :output_data
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'progress_text' => $progressText,
            'output_data' => json_encode($outputData),
        ]);
    }

    /**
     * Marcar job como completed
     */
    public function markCompleted(int $id, array $outputData): bool
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs 
            SET status = 'completed', output_data = :output_data, completed_at = NOW(), progress_text = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'output_data' => json_encode($outputData)]);
    }

    /**
     * Marcar job como failed
     */
    public function markFailed(int $id, string $errorMessage): bool
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs 
            SET status = 'failed', error_message = :error_message, completed_at = NOW(), progress_text = NULL
            WHERE id = :id
        ");
        return $stmt->execute(['id' => $id, 'error_message' => $errorMessage]);
    }

    /**
     * Limpiar jobs antiguos completados/fallidos (más de X días)
     */
    public function cleanupOld(int $daysOld = 7): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM background_jobs 
            WHERE status IN ('completed', 'failed') 
            AND completed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $daysOld]);
        return $stmt->rowCount();
    }

    /**
     * Recuperar jobs "colgados" (processing por más de X minutos)
     */
    public function resetStuckJobs(int $minutesStuck = 10): int
    {
        $stmt = $this->db->prepare("
            UPDATE background_jobs 
            SET status = 'pending', started_at = NULL, progress_text = NULL
            WHERE status = 'processing' 
            AND started_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute(['minutes' => $minutesStuck]);
        return $stmt->rowCount();
    }
}
