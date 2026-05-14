<?php
/**
 * API: Create Lead Finder run and queue background job
 * POST /api/gestures/lead-finder/search.php
 * Body JSON: { "query": "...", "max_results": 25 }
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';

use App\Response;
use App\Session;
use Jobs\BackgroundJobsRepo;
use LeadFinder\LeadFinderRepo;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$query = trim((string)($body['query'] ?? ''));
$maxResults = (int)($body['max_results'] ?? 25);
$maxResults = max(1, min($maxResults, 100));

if ($query === '') {
    Response::error('missing_query', 'Query is required', 400);
}

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'lead-finder')) {
    Response::error('forbidden', 'No access to Lead Finder', 403);
}

try {
    $leadRepo = new LeadFinderRepo();
    $jobsRepo = new BackgroundJobsRepo();

    $runId = $leadRepo->createRun((int)$user['id'], $query, $maxResults, 'mock');

    $jobId = $jobsRepo->create([
        'user_id' => (int)$user['id'],
        'job_type' => 'lead-finder',
        'input_data' => [
            'run_id' => $runId,
            'query' => $query,
            'max_results' => $maxResults,
            'provider' => 'mock',
        ],
    ]);

    $leadRepo->attachJob($runId, (int)$user['id'], $jobId);

    Response::json([
        'success' => true,
        'run_id' => $runId,
        'job_id' => $jobId,
        'status' => 'pending',
    ]);
} catch (\Throwable $e) {
    Response::serverError('lead_finder_search_error', $e, 'Could not start Lead Finder search');
}
