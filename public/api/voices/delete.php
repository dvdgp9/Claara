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
use I18n\I18n;
use Voices\VoiceExecutionsRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', I18n::translate('auth.error.unauthorized'), 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', I18n::translate('auth.error.csrf_invalid'), 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', I18n::translate('auth.error.method_not_allowed'), 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    Response::error('missing_id', I18n::translate('voice_api.id_required'), 400);
}

$repo = new VoiceExecutionsRepo();
$ok = $repo->delete($id, (int)$user['id']);

if (!$ok) {
    Response::error('not_found', I18n::translate('voice_api.item_not_found'), 404);
}

Response::json(['success' => true]);
