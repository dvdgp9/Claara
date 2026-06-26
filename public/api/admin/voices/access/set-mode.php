<?php
/**
 * POST /api/admin/voices/access/set-mode.php?slug=lex
 * Body JSON: { mode: 'level'|'list', min_access_level_id?: int|null }
 * Sets the voice access policy. In 'level' mode min_access_level_id NULL =
 * everyone; it is ignored (cleared) in 'list' mode.
 */
require_once __DIR__ . '/../documents/_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/AccessLevelsRepo.php';

use App\DB;
use App\Response;
use App\Session;
use Repos\AccessLevelsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[, $voice] = require_voice_document_context();
Session::requireCsrf();

$input = read_json_body();
$mode = ($input['mode'] ?? '') === 'list' ? 'list' : 'level';

$minLevelId = null;
if ($mode === 'level' && isset($input['min_access_level_id']) && (int)$input['min_access_level_id'] > 0) {
    $minLevelId = (int)$input['min_access_level_id'];
    if ((new AccessLevelsRepo())->getById($minLevelId) === null) {
        Response::error('invalid_level', 'Level not found', 400);
    }
}

$pdo = DB::pdo();
$pdo->prepare('UPDATE voices SET access_mode = ?, min_access_level_id = ? WHERE id = ?')
    ->execute([$mode, $minLevelId, (int)$voice['id']]);

Response::json(['success' => true, 'access_mode' => $mode, 'min_access_level_id' => $minLevelId]);
