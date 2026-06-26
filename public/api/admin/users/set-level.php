<?php
/**
 * POST /api/admin/users/set-level.php
 * Body JSON: { user_id, level_id }   (level_id null/0 = no level)
 * Assigns a user's single global access level.
 */
require_once __DIR__ . '/../access-levels/_helpers.php';

use App\Response;
use App\Session;
use Repos\AccessLevelsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

require_superadmin();
Session::requireCsrf();

$input = level_read_json_body();
$userId = (int)($input['user_id'] ?? 0);
$levelId = isset($input['level_id']) && (int)$input['level_id'] > 0 ? (int)$input['level_id'] : null;
if ($userId <= 0) {
    Response::error('validation_error', 'user_id is required', 400);
}

$repo = new AccessLevelsRepo();
if ($levelId !== null && $repo->getById($levelId) === null) {
    Response::error('invalid_level', 'Level not found', 400);
}

$repo->assignUser($userId, $levelId);

Response::json(['success' => true, 'user_id' => $userId, 'level_id' => $levelId]);
