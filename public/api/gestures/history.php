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
    foreach ($items as &$item) {
        $full = $repo->findById((int)$item['id']);
        $outputData = is_array($full['output_data'] ?? null) ? $full['output_data'] : [];
        $inputData = is_array($item['input_data'] ?? null) ? $item['input_data'] : [];
        $item['thumbnail_image'] = $outputData['image_thumbnail'] ?? null;
        $item['mode'] = $inputData['mode'] ?? 'generate';
        $item['intent'] = $inputData['intent'] ?? null;
    }
    unset($item);
}

Response::json([
    'success' => true,
    'items' => $items,    // Compatibilidad con gestos existentes
    'history' => $items   // Para el nuevo SOP generator
]);
