<?php
/**
 * API: Get Lead Finder run with results
 * GET /api/gestures/lead-finder/get.php?id=123
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';

use App\Response;
use App\Session;
use LeadFinder\LeadFinderRepo;
use Repos\UserFeatureAccessRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

$runId = (int)($_GET['id'] ?? 0);
if ($runId <= 0) {
    Response::error('missing_id', 'Run id is required', 400);
}

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'lead-finder')) {
    Response::error('forbidden', 'No access to Lead Finder', 403);
}

try {
    $repo = new LeadFinderRepo();
    $run = $repo->findRunForUser($runId, (int)$user['id']);
    if (!$run) {
        Response::error('not_found', 'Run not found', 404);
    }

    $results = $repo->listResultsForRun($runId);

    Response::json([
        'success' => true,
        'run' => $run,
        'results' => $results,
    ]);
} catch (\Throwable $e) {
    Response::serverError('lead_finder_get_error', $e, 'Could not load Lead Finder run');
}
