<?php
/**
 * POST /api/admin/access-levels/reorder.php
 * Body JSON: { order: [levelId, ...] }  highest rank first.
 * Reassigns ranks so the given order holds (first = highest rank).
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
$order = $input['order'] ?? [];
if (!is_array($order) || $order === '') {
    Response::error('validation_error', 'order must be a non-empty array', 400);
}

$ids = [];
foreach ($order as $v) {
    $id = (int)$v;
    if ($id > 0) {
        $ids[] = $id;
    }
}
if ($ids === []) {
    Response::error('validation_error', 'order must contain level ids', 400);
}

$pdo = DB::pdo();
$repo = new AccessLevelsRepo($pdo);

// Highest rank to the first id, descending by 10 so there is room between them.
$rank = count($ids) * 10;
$pdo->beginTransaction();
try {
    foreach ($ids as $id) {
        if ($repo->getById($id) !== null) {
            $repo->setRank($id, $rank);
            $rank -= 10;
        }
    }
    $pdo->commit();
} catch (\Throwable $e) {
    $pdo->rollBack();
    Response::serverError('reorder_failed', $e, 'Could not reorder levels');
}

Response::json(['success' => true]);
