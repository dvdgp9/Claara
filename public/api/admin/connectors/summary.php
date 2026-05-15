<?php
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';

use App\Response;
use Auth\AuthService;
use Connectors\ConnectorAccountsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
if (!$user['is_superadmin'] && !in_array('admin', $user['roles'] ?? [], true)) {
    Response::error('forbidden', 'Access denied', 403);
}

$repo = new ConnectorAccountsRepo();

Response::json([
    'providers' => $repo->adminSummary(),
]);

