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
if (!$repo->findBySlug($slug, true)) {
    respond_voice_not_found();
}

try {
    $repo->archive($slug);
    $repo->syncAvailableFeature($slug);
    Response::json([
        'success' => true,
        'voice' => $repo->findBySlug($slug, true),
    ]);
} catch (\Throwable $e) {
    Response::serverError('voice_archive_failed', $e, 'No se pudo archivar la voz');
}
