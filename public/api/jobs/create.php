<?php
/**
 * API: Crear job en background
 * POST /api/jobs/create.php
 * 
 * Body: { job_type: string, input_data: object }
 * Response: { success: true, job_id: int }
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

$jobType = $body['job_type'] ?? '';
$inputData = $body['input_data'] ?? [];

if (empty($jobType)) {
    Response::error('missing_job_type', 'Se requiere job_type', 400);
}

// Tipos de job permitidos
$allowedTypes = ['podcast'];
if (!in_array($jobType, $allowedTypes)) {
    Response::error('invalid_job_type', 'Tipo de job no válido', 400);
}

try {
    $repo = new BackgroundJobsRepo();
    
    // Limitar jobs activos por usuario (máximo 3)
    $activeCount = $repo->countActiveByUser($user['id']);
    if ($activeCount >= 3) {
        Response::error('too_many_jobs', 'Ya tienes demasiados trabajos en cola. Espera a que terminen.', 429);
    }
    
    $jobId = $repo->create([
        'user_id' => $user['id'],
        'job_type' => $jobType,
        'input_data' => $inputData
    ]);
    
    Response::json([
        'success' => true,
        'job_id' => $jobId,
        'message' => 'Job creado correctamente'
    ]);
    
} catch (\Exception $e) {
    Response::serverError('server_error', $e, 'Error al crear job');
}
