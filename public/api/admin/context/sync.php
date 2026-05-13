<?php
/**
 * GET /api/admin/context/sync.php
 * 
 * Syncs existing filesystem documents with the DB.
 * Useful to initialize the table with pre-existing documents.
 * Requires superadmin.
 */
require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AdminGuard.php';
require_once __DIR__ . '/../../../../src/Repos/ContextDocsRepo.php';
require_once __DIR__ . '/../../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../../src/App/Env.php';

use App\Response;
use App\Env;
use Auth\AdminGuard;
use Repos\ContextDocsRepo;
use Repos\UsersRepo;
use Rag\QdrantClient;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

AdminGuard::requireSuperadmin();

// Obtener un usuario superadmin para asignar como creador
$usersRepo = new UsersRepo();
$users = $usersRepo->listAll();
$adminUser = null;
foreach ($users as $u) {
    if ($u['is_superadmin']) {
        $adminUser = $u;
        break;
    }
}

if (!$adminUser) {
    Response::error('no_admin', 'No superadmin user found', 500);
}

$repo = new ContextDocsRepo();

// Configurar Qdrant
$qdrant = null;
try {
    $qdrant = new QdrantClient(
        Env::get('QDRANT_HOST', 'localhost'),
        (int)Env::get('QDRANT_PORT', 6333)
    );
    if (!$qdrant->health()) {
        $qdrant = null;
    }
} catch (\Exception $e) {
    $qdrant = null;
}

$targets = ['lex', 'eboniato', 'ebonia'];
$results = [
    'synced' => 0,
    'skipped' => 0,
    'errors' => 0,
    'details' => []
];

foreach ($targets as $target) {
    $targetPath = ContextDocsRepo::getTargetPath($target);
    $allowedExtensions = ContextDocsRepo::getAllowedExtensions($target);
    
    if (!is_dir($targetPath)) {
        $results['details'][] = [
            'target' => $target,
            'status' => 'skipped',
            'message' => 'Directory does not exist'
        ];
        continue;
    }
    
    // Buscar archivos
    $patterns = array_map(fn($ext) => $targetPath . '/*.' . $ext, $allowedExtensions);
    $files = [];
    foreach ($patterns as $pattern) {
        $files = array_merge($files, glob($pattern) ?: []);
    }
    
    // Excluir archivos de sistema
    $files = array_filter($files, function($f) {
        $basename = basename($f);
        return !in_array($basename, ['README.md', '.gitkeep', '.DS_Store']);
    });
    
    foreach ($files as $file) {
        $filename = basename($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $size = filesize($file);
        
        // Verificar si ya existe
        $existing = $repo->getByFilename($target, $filename);
        
        if ($existing) {
            $results['skipped']++;
            continue;
        }
        
        try {
            // Determine index status for Lex
            $ragStatus = 'not_applicable';
            $ragChunkCount = 0;
            
            if ($target === 'lex' && $qdrant) {
                $documentId = pathinfo($filename, PATHINFO_FILENAME);
                $ragChunkCount = $qdrant->countPointsByFilter('lex_knowledge_base', [
                    'must' => [
                        ['key' => 'document_id', 'match' => ['value' => $documentId]]
                    ]
                ]);
                
                if ($ragChunkCount > 0) {
                    $ragStatus = 'processed';
                } else {
                    $ragStatus = 'pending';
                }
            }
            
            // Crear registro
            $docId = $repo->create([
                'target' => $target,
                'filename' => $filename,
                'original_filename' => $filename,
                'file_extension' => $ext,
                'file_size' => $size,
                'status' => 'active',
                'created_by' => $adminUser['id'],
            ]);
            
            // Update index status
            if ($target === 'lex') {
                $repo->updateRagStatus($docId, $ragStatus, $ragChunkCount);
            }
            
            $results['synced']++;
            
        } catch (\Exception $e) {
            $results['errors']++;
            $results['details'][] = [
                'target' => $target,
                'file' => $filename,
                'error' => $e->getMessage()
            ];
        }
    }
    
    $results['details'][] = [
        'target' => $target,
        'status' => 'ok',
        'files_found' => count($files)
    ];
}

Response::json([
    'success' => true,
    'message' => "Synced: {$results['synced']}, Skipped: {$results['skipped']}, Errors: {$results['errors']}",
    'results' => $results
]);
