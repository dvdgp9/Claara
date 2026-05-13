<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Auth/RememberService.php';
require_once __DIR__ . '/../../../src/Auth/RateLimiter.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Auth\RememberService;
use Auth\RateLimiter;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

// Rate limiting: máx 5 intentos cada 15 minutos por IP
$clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$clientIp = explode(',', $clientIp)[0]; // Tomar primera IP si hay varias
$rateLimiter = new RateLimiter(5, 900);

if ($rateLimiter->isBlocked($clientIp)) {
    $remaining = $rateLimiter->getBlockedSeconds($clientIp);
    $minutes = ceil($remaining / 60);
    Response::error('rate_limited', "Too many failed attempts. Try again in {$minutes} minutes.", 429);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = (string)($input['password'] ?? '');
$remember = !empty($input['remember']);

if ($email === '' || $password === '') {
    Response::error('validation_error', 'Email and password are required', 400);
}

try {
    $user = AuthService::login($email, $password);
    // Login exitoso: limpiar intentos
    $rateLimiter->clearAttempts($clientIp);
} catch (\Exception $e) {
    // Login fallido: registrar intento
    $rateLimiter->recordAttempt($clientIp);
    throw $e;
}

if ($remember) {
    // Crear token persistente en BD + cookie
    RememberService::createToken((int)$user['id']);
}

Response::json([
    'user' => $user,
    'csrf_token' => $_SESSION['csrf_token'] ?? null,
]);
