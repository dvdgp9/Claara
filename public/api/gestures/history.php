<?php
/**
 * API: Listar historial de ejecuciones de gestos
 * GET /api/gestures/history.php?type=write-article
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Gestures\GestureExecutionsRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

$id = $_GET['id'] ?? null;
$gestureType = $_GET['type'] ?? null;
$limit = min((int)($_GET['limit'] ?? 20), 50);

$repo = new GestureExecutionsRepo();

if ($id) {
    $item = $repo->findById((int)$id);
    if (!$item || $item['user_id'] != $user['id']) {
        Response::error('not_found', 'Elemento no encontrado', 404);
    }
    Response::json([
        'success' => true,
        'item' => $item
    ]);
}

if ($gestureType) {
    $items = $repo->listByUserAndType($user['id'], $gestureType, $limit);
} else {
    $items = $repo->listByUser($user['id'], $limit);
}

if ($gestureType === 'image-editor' && !empty($items)) {
    // El repo ya devuelve mode/intent extraídos vía JSON_EXTRACT sin traer
    // el blob base64 de input_data. Solo normalizamos valores por defecto.
    foreach ($items as &$item) {
        $item['mode'] = $item['mode'] ?? 'generate';
        $item['intent'] = $item['intent'] ?? null;
    }
    unset($item);
}

Response::json([
    'success' => true,
    'items' => $items,    // Compatibilidad con gestos existentes
    'history' => $items   // Para el nuevo SOP generator
]);
