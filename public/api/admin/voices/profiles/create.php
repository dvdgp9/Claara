<?php
/**
 * POST /api/admin/voices/profiles/create.php?slug=lex
 * Body JSON: { name, description? }
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use App\Session;
use Repos\VoiceProfilesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$name = clean_text($input, 'name', 120, true);
$description = clean_text($input, 'description', 300, false);

$voiceId = (int)$voice['id'];
$repo = new VoiceProfilesRepo();

$base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
if ($base === '') {
    $base = 'profile';
}
$slug = $base;
$n = 1;
while ($repo->getBySlug($voiceId, $slug) !== null) {
    $n += 1;
    $slug = $base . '-' . $n;
}

$id = $repo->create($voiceId, $name, $slug, $description, false);

Response::json(['success' => true, 'profile' => $repo->getById($id)], 201);
