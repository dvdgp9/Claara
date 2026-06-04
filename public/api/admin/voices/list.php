<?php
require_once __DIR__ . '/_helpers.php';

use App\Response;
use Repos\VoicesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

require_voice_editor();

$includeArchived = ($_GET['include_archived'] ?? '') === '1';
$repo = new VoicesRepo();

Response::json([
    'success' => true,
    'voices' => $repo->listAll($includeArchived),
]);
