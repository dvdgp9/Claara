<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoicesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = require_voice_editor();
Session::requireCsrf();

$input = read_json_body();
$slug = clean_voice_slug($input);
$payload = clean_voice_payload($input, true);
$payload['slug'] = $slug;
$payload['status'] = 'draft';
$payload['rag_collection'] = 'voice_' . str_replace('-', '_', $slug);
$payload['created_by'] = (int)$user['id'];

$repo = new VoicesRepo();
if ($repo->findBySlug($slug, true)) {
    Response::error('duplicate_voice', 'Ya existe una voz con ese slug', 409);
}

try {
    $id = $repo->create($payload);
    $voice = $repo->findBySlug($slug, true);
    Response::json([
        'success' => true,
        'id' => $id,
        'voice' => $voice,
    ], 201);
} catch (\Throwable $e) {
    Response::serverError('voice_create_failed', $e, 'No se pudo crear la voz');
}
