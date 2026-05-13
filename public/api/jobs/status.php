<?php
/**
 * API: Consultar estado de un job
 * GET /api/jobs/status.php?id=123
 * 
 * Response: { success: true, job: { id, status, progress_text, output_data, error_message } }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Jobs\BackgroundJobsRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

$jobId = (int)($_GET['id'] ?? 0);

if (!$jobId) {
    Response::error('missing_id', 'Se requiere id del job', 400);
}

try {
    $repo = new BackgroundJobsRepo();
    $job = $repo->findById($jobId);
    
    if (!$job) {
        Response::error('not_found', 'Job no encontrado', 404);
    }
    
    // Verificar que el job pertenece al usuario
    if ((int)$job['user_id'] !== $user['id']) {
        Response::error('forbidden', 'No tienes acceso a este job', 403);
    }
    
    // Devolver solo los campos necesarios para el frontend
    Response::json([
        'success' => true,
        'job' => [
            'id' => (int)$job['id'],
            'job_type' => $job['job_type'],
            'status' => $job['status'],
            'progress_text' => $job['progress_text'],
            'output_data' => $job['output_data'],
            'error_message' => $job['error_message'],
            'created_at' => $job['created_at'],
            'started_at' => $job['started_at'],
            'completed_at' => $job['completed_at']
        ]
    ]);
    
} catch (\Exception $e) {
    Response::error('server_error', 'Error al consultar job: ' . $e->getMessage(), 500);
}
