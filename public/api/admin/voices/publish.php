<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoicesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

require_voice_editor();
Session::requireCsrf();

$input = read_json_body();
$slug = clean_voice_slug($input);
$repo = new VoicesRepo();
$voice = $repo->findBySlug($slug, true);
if (!$voice) {
    respond_voice_not_found();
}
if (trim((string)$voice['instructions']) === '') {
    Response::error('publish_blocked', 'Añade instrucciones antes de publicar la voz', 400);
}

try {
    $repo->publish($slug);
    $repo->syncAvailableFeature($slug);
    Response::json([
        'success' => true,
        'voice' => $repo->findBySlug($slug, true),
    ]);
} catch (\Throwable $e) {
    Response::serverError('voice_publish_failed', $e, 'No se pudo publicar la voz');
}
