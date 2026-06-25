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

require_voice_editor();
Session::requireCsrf();

$input = read_json_body();
$slug = clean_voice_slug($input);
$payload = clean_voice_payload($input, false);
$responsibleUserIds = array_key_exists('responsible_user_ids', $input)
    ? clean_user_id_list($input['responsible_user_ids'])
    : null;

$pdo = DB::pdo();
$repo = new VoicesRepo($pdo);
if (!$repo->findBySlug($slug, true)) {
    respond_voice_not_found();
}

try {
    $pdo->beginTransaction();
    $repo->update($slug, $payload);
    if ($responsibleUserIds !== null) {
        save_voice_responsibles($pdo, $slug, $responsibleUserIds);
    }
    $repo->syncAvailableFeature($slug);
    $pdo->commit();
    $voice = $repo->findBySlug($slug, true);
    $voice['responsible_users'] = (new OrganizationResponsibilityRepo($pdo))->getVoiceResponsibles($slug);
    Response::json([
        'success' => true,
        'voice' => $voice,
    ]);
} catch (\Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    Response::serverError('voice_update_failed', $e, 'Could not update the voice');
}
