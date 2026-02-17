<?php
/**
 * GET /api/admin/context/stats.php?target=lex
 * 
 * Obtiene estadísticas de un target específico.
 * Requiere superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'Sólo GET', 405);
}

AdminGuard::requireSuperadmin();

$target = $_GET['target'] ?? '';

// Si no se especifica target, devolver stats de todos
if (empty($target)) {
    $repo = new ContextDocsRepo();
    $allStats = [];
    
    foreach (ContextDocsRepo::getValidTargets() as $t) {
        $allStats[$t] = $repo->getStatsByTarget($t);
    }
    
    Response::json(['stats' => $allStats]);
}

if (!ContextDocsRepo::isValidTarget($target)) {
    Response::error('invalid_target', 'Target inválido. Valores permitidos: ' . implode(', ', ContextDocsRepo::getValidTargets()), 400);
}

$repo = new ContextDocsRepo();
$stats = $repo->getStatsByTarget($target);

Response::json([
    'target' => $target,
    'stats' => $stats
]);
