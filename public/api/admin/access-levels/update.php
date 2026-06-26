<?php
/**
 * POST /api/admin/access-levels/update.php
 * Body JSON: { id, name?, is_default? }
 * Renames a level and/or makes it the default level for new users.
 */
require_once __DIR__ . '/_helpers.php';

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

$repo = new AccessLevelsRepo();
$level = $repo->getById($id);
if (!$level) {
    Response::error('not_found', 'Level not found', 404);
}

if (array_key_exists('name', $input)) {
    $repo->update($id, level_clean_name($input));
}
if (!empty($input['is_default'])) {
    $repo->setDefault($id);
}

Response::json(['success' => true, 'level' => $repo->getById($id)]);
