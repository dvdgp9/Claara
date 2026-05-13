<?php
/**
 * API: Listar jobs activos del usuario (pending o processing)
 * GET /api/jobs/active.php
 * 
 * Response: { success: true, jobs: [...], count: int }
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

try {
    $repo = new BackgroundJobsRepo();
    
    // Obtener jobs pending y processing
    $pending = $repo->findByUserAndStatus($user['id'], 'pending');
    $processing = $repo->findByUserAndStatus($user['id'], 'processing');
    
    $activeJobs = array_merge($processing, $pending);
    
    // Formatear para el frontend
    $jobs = array_map(function($job) {
        return [
            'id' => (int)$job['id'],
            'job_type' => $job['job_type'],
            'status' => $job['status'],
            'progress_text' => $job['progress_text'],
            'created_at' => $job['created_at']
        ];
    }, $activeJobs);
    
    Response::json([
        'success' => true,
        'jobs' => $jobs,
        'count' => count($jobs)
    ]);
    
} catch (\Exception $e) {
    Response::error('server_error', 'Error al listar jobs: ' . $e->getMessage(), 500);
}
