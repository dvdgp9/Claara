<?php
/**
 * API: Delete Lead Finder run (and its results by cascade)
 * POST /api/gestures/lead-finder/delete.php
 * Body JSON: { "id": 123 }
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

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasGestureAccess((int)$user['id'], 'lead-finder')) {
    Response::error('forbidden', 'No access to Lead Finder', 403);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$runId = (int)($body['id'] ?? 0);
if ($runId <= 0) {
    Response::error('missing_id', 'Run id is required', 400);
}

try {
    $repo = new LeadFinderRepo();
    $ok = $repo->deleteRunForUser($runId, (int)$user['id']);
    if (!$ok) {
        Response::error('not_found', 'Run not found or not deletable', 404);
    }

    Response::json([
        'success' => true,
        'id' => $runId,
    ]);
} catch (\Throwable $e) {
    Response::serverError('lead_finder_delete_error', $e, 'Could not delete run');
}
