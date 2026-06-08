<?php
require_once __DIR__ . '/_helpers.php';
require_once __DIR__ . '/../../../../src/App/DB.php';
require_once __DIR__ . '/../../../../src/Repos/OrganizationResponsibilityRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UserFeatureAccessRepo.php';

use App\Response;
use App\Session;
use App\DB;
use Repos\OrganizationResponsibilityRepo;
use Repos\UserFeatureAccessRepo;
use Repos\VoicesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = require_voice_editor();
Session::requireCsrf();

$input = read_json_body();
$slug = clean_voice_slug($input);
$payload = clean_voice_payload($input, true);
$responsibleUserIds = clean_user_id_list($input['responsible_user_ids'] ?? []);
$payload['slug'] = $slug;
$payload['status'] = 'draft';
$payload['rag_collection'] = 'voice_' . str_replace('-', '_', $slug);
$payload['created_by'] = (int)$user['id'];

$pdo = DB::pdo();
$repo = new VoicesRepo($pdo);
if ($repo->findBySlug($slug, true)) {
    Response::error('duplicate_voice', 'Ya existe una voz con ese slug', 409);
}

try {
    $pdo->beginTransaction();
    $id = $repo->create($payload);
    save_voice_responsibles($pdo, $slug, $responsibleUserIds);
    $pdo->commit();
    $voice = $repo->findBySlug($slug, true);
    $voice['responsible_users'] = (new OrganizationResponsibilityRepo($pdo))->getVoiceResponsibles($slug);
    Response::json([
        'success' => true,
        'id' => $id,
        'voice' => $voice,
    ], 201);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::serverError('voice_create_failed', $e, 'No se pudo crear la voz');
}
