<?php
/**
 * API: Export Lead Finder run results as CSV
 * POST /api/gestures/lead-finder/export.php
 * Body JSON: { "id": 123, "format": "csv" }
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
$format = strtolower((string)($body['format'] ?? 'csv'));
if ($runId <= 0) {
    Response::error('missing_id', 'Run id is required', 400);
}
if ($format !== 'csv') {
    Response::error('invalid_format', 'Only csv format is supported right now', 400);
}

try {
    $repo = new LeadFinderRepo();
    $run = $repo->findRunForUser($runId, (int)$user['id']);
    if (!$run) {
        Response::error('not_found', 'Run not found', 404);
    }

    $rows = $repo->listResultsForRun($runId);

    $filename = 'lead-finder-' . $runId . '-' . date('Ymd-His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'wb');
    if ($out === false) {
        Response::error('export_error', 'Could not open output stream', 500);
    }

    fputcsv($out, ['Name', 'Website', 'Email', 'Phone', 'Address', 'Source URL', 'Confidence', 'Status']);
    foreach ($rows as $row) {
        fputcsv($out, [
            (string)($row['name'] ?? ''),
            (string)($row['website'] ?? ''),
            (string)($row['email'] ?? ''),
            (string)($row['phone'] ?? ''),
            (string)($row['address'] ?? ''),
            (string)($row['source_url'] ?? ''),
            (string)($row['confidence'] ?? ''),
            (string)($row['status'] ?? ''),
        ]);
    }
    fclose($out);
    exit;
} catch (\Throwable $e) {
    Response::serverError('lead_finder_export_error', $e, 'Could not export run');
}
