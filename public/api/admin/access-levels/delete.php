<?php
/**
 * POST /api/admin/access-levels/delete.php
 * Body JSON: { id }
 * Deletes a level. Users at it move to the default level; voice/folder
 * minimums that referenced it fall back to "everyone" (NULL).
 * The default level and the last remaining level cannot be deleted.
 */
require_once __DIR__ . '/_helpers.php';

use App\DB;
use App\Response;
use App\Session;
use Repos\AccessLevelsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

require_superadmin();
Session::requireCsrf();

$input = level_read_json_body();
$id = (int)($input['id'] ?? 0);

$pdo = DB::pdo();
$repo = new AccessLevelsRepo($pdo);
$level = $repo->getById($id);
if (!$level) {
    Response::error('not_found', 'Level not found', 404);
}
if ((int)$level['is_default'] === 1) {
    Response::error('cannot_delete_default', 'The default level cannot be deleted. Set another level as default first.', 409);
}
if (count($repo->listAll()) <= 1) {
    Response::error('cannot_delete_last', 'At least one access level must remain', 409);
}

$default = $repo->getDefault();
$defaultId = $default ? (int)$default['id'] : null;

$pdo->beginTransaction();
try {
    // Users at this level fall back to the default level.
    $pdo->prepare('UPDATE users SET access_level_id = ? WHERE access_level_id = ?')
        ->execute([$defaultId, $id]);
    // Voice and folder minimums that referenced it become "everyone".
    $pdo->prepare('UPDATE voices SET min_access_level_id = NULL WHERE min_access_level_id = ?')
        ->execute([$id]);
    $pdo->prepare('UPDATE voice_folders SET required_level_id = NULL WHERE required_level_id = ?')
        ->execute([$id]);
    $repo->delete($id);
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    Response::serverError('delete_failed', $e, 'Could not delete the level');
}

Response::json(['success' => true]);
