<?php
/**
 * GET /api/admin/context/list.php?target=lex
 * 
 * Lists all context documents for a specific target.
 * Requires superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';

use App\Response;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

$target = $_GET['target'] ?? '';

if (!ContextDocsRepo::isValidTarget($target)) {
    Response::error('invalid_target', 'Invalid target. Allowed values: ' . implode(', ', ContextDocsRepo::getValidTargets()), 400);
}

$repo = new ContextDocsRepo();
$documents = $repo->listByTarget($target);
$stats = $repo->getStatsByTarget($target);

// Añadir información de ruta física (solo para debug/info)
$targetPath = ContextDocsRepo::getTargetPath($target);
$allowedExtensions = ContextDocsRepo::getAllowedExtensions($target);

Response::json([
    'target' => $target,
    'documents' => $documents,
    'stats' => $stats,
    'allowed_extensions' => $allowedExtensions,
    'target_path' => basename($targetPath) // Solo el nombre, no ruta completa por seguridad
]);
