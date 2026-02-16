<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Auth/RememberService.php';

use App\Session;
use Auth\RememberService;

Session::start();

// Limpiar remember tokens antes de logout
$user = Session::user();
if ($user && isset($user['id'])) {
    RememberService::clearAllForUser((int)$user['id']);
}

Session::logout();
header('Location: /login.php');
exit;
