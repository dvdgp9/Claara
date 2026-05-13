<?php
/**
 * API: Eliminar una ejecución de voz
 * POST /api/voices/delete.php
 * Body JSON: { "id": 123 }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Voices/VoiceExecutionsRepo.php';

use App\Session;
use App\Response;
use Voices\VoiceExecutionsRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Invalid CSRF token', 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    Response::error('missing_id', 'ID is required', 400);
}

$repo = new VoiceExecutionsRepo();
$ok = $repo->delete($id, (int)$user['id']);

if (!$ok) {
    Response::error('not_found', 'Item not found or you do not have permission', 404);
}

Response::json(['success' => true]);
