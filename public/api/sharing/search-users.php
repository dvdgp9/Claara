<?php
/**
 * Busca usuarios para autocompletado en el modal de compartición
 * GET /api/sharing/search-users.php?q=angel
 */
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/SharingRepo.php';

use App\Response;
use Auth\AuthService;
use Repos\SharingRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'Sólo GET', 405);
}

$user = AuthService::requireAuth();

$query = isset($_GET['q']) ? trim((string)$_GET['q']) : '';
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 20) : 10;

if (mb_strlen($query) < 2) {
    Response::json(['users' => []]);
}

$repo = new SharingRepo();
$users = $repo->searchUsers($query, (int)$user['id'], $limit);

Response::json([
    'users' => array_map(function($u) {
        return [
            'id' => (int)$u['id'],
            'first_name' => $u['first_name'],
            'last_name' => $u['last_name'],
            'email' => $u['email'],
            'initials' => strtoupper(substr($u['first_name'], 0, 1) . substr($u['last_name'], 0, 1))
        ];
    }, $users)
]);
