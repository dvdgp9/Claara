<?php
/**
 * API: Update a Lead Finder result row
 * POST /api/gestures/lead-finder/update-result.php
 * Body JSON: { "id": 123, "name": "...", "status": "validated", ... }
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
$resultId = (int)($body['id'] ?? 0);
if ($resultId <= 0) {
    Response::error('missing_id', 'Result id is required', 400);
}

try {
    $repo = new LeadFinderRepo();
    $ok = $repo->updateResultForUser($resultId, (int)$user['id'], [
        'name' => $body['name'] ?? '',
        'website' => $body['website'] ?? null,
        'email' => $body['email'] ?? null,
        'phone' => $body['phone'] ?? null,
        'address' => $body['address'] ?? null,
        'source_url' => $body['source_url'] ?? null,
        'confidence' => $body['confidence'] ?? null,
        'status' => $body['status'] ?? 'pending',
    ]);

    if (!$ok) {
        Response::error('not_found', 'Result not found or not editable', 404);
    }

    Response::json([
        'success' => true,
        'id' => $resultId,
    ]);
} catch (\Throwable $e) {
    Response::serverError('lead_finder_update_result_error', $e, 'Could not update result');
}
