<?php
/**
 * API: Actualizar título de una ejecución de gesto
 * POST /api/gestures/update-title.php
 * Body JSON: { "id": 123, "title": "Nuevo título" }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Gestures/GestureExecutionsRepo.php';

use App\Session;
use App\Response;
use Gestures\GestureExecutionsRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];
$csrfBody = $body['csrf_token'] ?? '';

$validHeader = $csrfHeader && $csrfSession && hash_equals($csrfSession, $csrfHeader);
$validBody = $csrfBody && $csrfSession && hash_equals($csrfSession, $csrfBody);
if (!$validHeader && !$validBody) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$id = isset($body['id']) ? (int)$body['id'] : 0;
$title = trim((string)($body['title'] ?? ''));

if ($id <= 0 || $title === '') {
    Response::error('missing_params', 'ID y título requeridos', 400);
}

$repo = new GestureExecutionsRepo();
$ok = $repo->updateTitle($id, (int)$user['id'], $title);

if (!$ok) {
    Response::error('not_found', 'No se ha encontrado el elemento o no tienes permiso', 404);
}

Response::json(['success' => true]);
