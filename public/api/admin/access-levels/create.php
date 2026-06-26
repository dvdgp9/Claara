<?php
/**
 * POST /api/admin/access-levels/create.php
 * Body JSON: { name }
 * Creates a new global level at the top of the ladder (highest rank).
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
$name = level_clean_name($input);

$repo = new AccessLevelsRepo();
$slug = level_slugify($name, static fn(string $s): bool => $repo->getBySlug($s) !== null);

$id = $repo->create($name, $slug);

Response::json(['success' => true, 'level' => $repo->getById($id)], 201);
