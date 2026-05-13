<?php
/**
 * API: Cancelar job en background
 * POST /api/jobs/cancel.php
 * 
 * Body: { job_id: int }
 * Response: { success: true, message: string }
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

Session::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$jobId = (int)($body['job_id'] ?? 0);

if (!$jobId) {
    Response::error('missing_job_id', 'Se requiere job_id', 400);
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
    
    // Solo se pueden cancelar jobs pending o processing
    if (!in_array($job['status'], ['pending', 'processing'])) {
        Response::error('invalid_status', 'El job ya no se puede cancelar (estado: ' . $job['status'] . ')', 400);
    }
    
    // Marcar como failed con mensaje de cancelación
    $repo->markFailed($jobId, 'Cancelado por el usuario');
    
    Response::json([
        'success' => true,
        'message' => 'Job cancelado correctamente',
        'job_id' => $jobId
    ]);
    
} catch (\Exception $e) {
    Response::error('server_error', 'Error al cancelar job: ' . $e->getMessage(), 500);
}
