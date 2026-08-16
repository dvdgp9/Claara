<?php
/**
 * API: Obtener una ejecución de voz por ID
 * GET /api/voices/get.php?id=123
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

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    Response::error('missing_id', I18n::translate('voice_api.id_required'), 400);
}

$repo = new VoiceExecutionsRepo();
$item = $repo->getById($id, (int)$user['id']);

if (!$item) {
    Response::error('not_found', I18n::translate('voice_api.item_not_found'), 404);
}

Response::json($item);
