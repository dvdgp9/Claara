<?php
/**
 * API: Obtener una ejecución de gesto por ID
 * GET /api/gestures/get.php?id=123
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Gestures\GestureExecutionsRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    Response::error('missing_id', 'ID requerido', 400);
}

$repo = new GestureExecutionsRepo();
$execution = $repo->findById($id);

if (!$execution) {
    Response::error('not_found', 'Ejecución no encontrada', 404);
}

// Verificar que pertenece al usuario
if ((int)$execution['user_id'] !== $user['id']) {
    Response::error('forbidden', 'No tienes acceso a esta ejecución', 403);
}

Response::json([
    'execution' => $execution
]);
