<?php
/**
 * Script de sincronización de documentos de contexto
 * 
 * Escanea los directorios de contexto y registra los archivos existentes
 * en la tabla context_documents para poder gestionarlos desde la UI.
 * 
 * Uso: php scripts/sync_context_docs.php [--dry-run]
 *   --dry-run  Solo muestra qué archivos se sincronizarían, sin modificar BD
 */

require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Repos/ContextDocsRepo.php';
require_once __DIR__ . '/../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../src/App/Env.php';

use App\Env;
use Repos\ContextDocsRepo;
use Repos\UsersRepo;
use Rag\QdrantClient;

// Parsear argumentos
$dryRun = in_array('--dry-run', $argv);

echo "=== Sincronización de documentos de contexto ===\n";
if ($dryRun) {
    echo "MODO: Dry run (no se modificará la BD)\n";
}
echo "\n";

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
    die("ERROR: No se encontró ningún usuario superadmin para asignar como creador.\n");
}

echo "Usando usuario: {$adminUser['first_name']} {$adminUser['last_name']} (ID: {$adminUser['id']})\n\n";

$repo = new ContextDocsRepo();

// Configurar Qdrant para verificar RAG
$qdrant = null;
try {
    $qdrant = new QdrantClient(
        Env::get('QDRANT_HOST', 'localhost'),
        (int)Env::get('QDRANT_PORT', 6333)
    );
    if (!$qdrant->health()) {
        echo "AVISO: Qdrant no está disponible. No se podrá verificar estado RAG.\n\n";
        $qdrant = null;
    }
} catch (\Exception $e) {
    echo "AVISO: No se pudo conectar con Qdrant: {$e->getMessage()}\n\n";
}

$targets = ['lex', 'eboniato', 'ebonia'];
$totalSynced = 0;
$totalSkipped = 0;
$totalErrors = 0;

foreach ($targets as $target) {
    $targetPath = ContextDocsRepo::getTargetPath($target);
    $allowedExtensions = ContextDocsRepo::getAllowedExtensions($target);
    
    echo "--- Target: {$target} ---\n";
    echo "Ruta: {$targetPath}\n";
    echo "Extensiones: " . implode(', ', $allowedExtensions) . "\n\n";
    
    if (!is_dir($targetPath)) {
        echo "  AVISO: El directorio no existe. Saltando.\n\n";
        continue;
    }
    
    // Buscar archivos
    $patterns = array_map(fn($ext) => $targetPath . '/*.' . $ext, $allowedExtensions);
    $files = [];
    foreach ($patterns as $pattern) {
        $files = array_merge($files, glob($pattern) ?: []);
    }
    
    // Excluir README.md y otros archivos de sistema
    $files = array_filter($files, function($f) {
        $basename = basename($f);
        return !in_array($basename, ['README.md', '.gitkeep', '.DS_Store']);
    });
    
    echo "Archivos encontrados: " . count($files) . "\n";
    
    foreach ($files as $file) {
        $filename = basename($file);
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $size = filesize($file);
        
        // Verificar si ya existe en BD
        $existing = $repo->getByFilename($target, $filename);
        
        if ($existing) {
            echo "  [SKIP] {$filename} - Ya existe en BD\n";
            $totalSkipped++;
            continue;
        }
        
        echo "  [SYNC] {$filename} ({$ext}, " . number_format($size) . " bytes)";
        
        if ($dryRun) {
            echo " - (dry run)\n";
            $totalSynced++;
            continue;
        }
        
        try {
            // Determinar estado RAG para Lex
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
            
            // Actualizar estado RAG si corresponde
            if ($target === 'lex') {
                $repo->updateRagStatus($docId, $ragStatus, $ragChunkCount);
            }
            
            echo " - OK (ID: {$docId}";
            if ($target === 'lex') {
                echo ", RAG: {$ragStatus}";
                if ($ragChunkCount > 0) {
                    echo " [{$ragChunkCount} chunks]";
                }
            }
            echo ")\n";
            
            $totalSynced++;
        } catch (\Exception $e) {
            echo " - ERROR: {$e->getMessage()}\n";
            $totalErrors++;
        }
    }
    
    echo "\n";
}

echo "=== Resumen ===\n";
echo "Sincronizados: {$totalSynced}\n";
echo "Saltados (ya existían): {$totalSkipped}\n";
echo "Errores: {$totalErrors}\n";

if ($dryRun) {
    echo "\nEsto fue un dry run. Ejecuta sin --dry-run para sincronizar.\n";
}
