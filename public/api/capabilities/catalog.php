<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';

use App\Response;
use Auth\AuthService;
use Claara\CapabilityCatalogService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();
$service = new CapabilityCatalogService();

Response::json([
    'success' => true,
    'catalog' => $service->forUser($user),
]);
