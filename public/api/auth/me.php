<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Response;
use App\Session;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}
Response::json(['user' => $user, 'csrf_token' => $_SESSION['csrf_token'] ?? null]);
