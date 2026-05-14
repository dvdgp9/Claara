<?php
/**
 * API: List Lead Finder runs for user
 * GET /api/gestures/lead-finder/history.php?limit=20
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

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'lead-finder')) {
    Response::error('forbidden', 'No access to Lead Finder', 403);
}

$limit = max(1, min((int)($_GET['limit'] ?? 20), 50));

try {
    $repo = new LeadFinderRepo();
    $runs = $repo->listRunsForUser((int)$user['id'], $limit);

    Response::json([
        'success' => true,
        'items' => $runs,
        'history' => $runs,
    ]);
} catch (\Throwable $e) {
    Response::serverError('lead_finder_history_error', $e, 'Could not load Lead Finder history');
}
