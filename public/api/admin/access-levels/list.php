<?php
/**
 * GET /api/admin/access-levels/list.php
 * Global access levels (highest rank first) with per-level user counts.
 */
require_once __DIR__ . '/_helpers.php';

use App\Response;
use Repos\AccessLevelsRepo;

require_superadmin();

$repo = new AccessLevelsRepo();
$counts = $repo->userCounts();

$levels = array_map(static function (array $l) use ($counts): array {
    $id = (int)$l['id'];
    return [
        'id' => $id,
        'name' => (string)$l['name'],
        'slug' => (string)$l['slug'],
        'rank' => (int)$l['rank'],
        'is_default' => (int)$l['is_default'] === 1,
        'user_count' => $counts[$id] ?? 0,
    ];
}, $repo->listAll());

Response::json(['success' => true, 'levels' => $levels]);
