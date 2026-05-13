<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/RememberService.php';

use App\Response;
use App\Session;
use Auth\RememberService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

Session::requireCsrf();

// Limpiar tokens de remember del usuario antes de logout
$user = Session::user();
if ($user && isset($user['id'])) {
    RememberService::clearAllForUser((int)$user['id']);
}

Session::logout();
http_response_code(204);
