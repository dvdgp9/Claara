<?php
/**
 * GET /api/admin/context/list.php?target=lex
 * 
 * Lista todos los documentos de contexto de un target específico.
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

if (!ContextDocsRepo::isValidTarget($target)) {
    Response::error('invalid_target', 'Target inválido. Valores permitidos: ' . implode(', ', ContextDocsRepo::getValidTargets()), 400);
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
