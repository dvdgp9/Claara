<?php
/**
 * API: Eliminar una ejecución de gesto
 * POST /api/gestures/delete.php
 * Body JSON: { "id": 123 }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Gestures/GestureExecutionsRepo.php';

use App\Session;
use App\Response;
use Gestures\GestureExecutionsRepo;

// Sesión ya iniciada en bootstrap
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';

// Leer cuerpo por si llega el token en JSON (fallback)
$rawBody = file_get_contents('php://input');
$body = json_decode($rawBody, true) ?? [];
$csrfBody = $body['csrf_token'] ?? '';

$validHeader = $csrfHeader && $csrfSession && hash_equals($csrfSession, $csrfHeader);
$validBody = $csrfBody && $csrfSession && hash_equals($csrfSession, $csrfBody);
if (!$validHeader && !$validBody) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$body = $body ?: [];
$id = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    Response::error('missing_id', 'ID requerido', 400);
}

$repo = new GestureExecutionsRepo();
$ok = $repo->delete($id, (int)$user['id']);

if (!$ok) {
    Response::error('not_found', 'No se ha encontrado el elemento o no tienes permiso', 404);
}

Response::json(['success' => true]);
