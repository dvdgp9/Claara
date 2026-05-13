<?php
/**
 * Lex RAG ingestion script.
 * Processes documents and indexes them in Qdrant.
 * 
 * Uso: php scripts/rag/ingest_lex.php [--reset]
 *   --reset  Elimina la colección existente y la recrea
 */

require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../src/Rag/EmbeddingService.php';

use App\Env;
use Rag\QdrantClient;
use Rag\EmbeddingService;

// Configuración
define('COLLECTION_NAME', 'lex_knowledge_base');
define('VECTOR_SIZE', 4096);  // qwen/qwen3-embedding-8b
define('CHUNK_SIZE', 500);    // tokens aproximados por chunk
define('CHUNK_OVERLAP', 50);  // tokens de overlap entre chunks
define('BATCH_SIZE', 10);     // chunks por batch de embeddings

$knowledgeBasePath = __DIR__ . '/../../docs/context/voices/lex/knowledge-base';

// Parsear argumentos
$reset = in_array('--reset', $argv);

echo "=== Ingesta RAG para Lex ===\n\n";

// Verificar API key de OpenRouter (usada para embeddings)
$openrouterKey = Env::get('OPENROUTER_API_KEY');
if (!$openrouterKey) {
    die("ERROR: OPENROUTER_API_KEY no configurada en .env\n");
}

// Inicializar clientes
$qdrant = new QdrantClient(
    Env::get('QDRANT_HOST', 'localhost'),
    (int) Env::get('QDRANT_PORT', 6333)
);

$embeddings = new EmbeddingService($openrouterKey);

// Verificar conexión con Qdrant
echo "Verificando conexión con Qdrant... ";
if (!$qdrant->health()) {
    die("ERROR: No se puede conectar con Qdrant. ¿Está corriendo?\n");
}
echo "OK\n";

// Gestionar colección
if ($reset && $qdrant->collectionExists(COLLECTION_NAME)) {
    echo "Eliminando colección existente... ";
    $qdrant->deleteCollection(COLLECTION_NAME);
    echo "OK\n";
}

if (!$qdrant->collectionExists(COLLECTION_NAME)) {
    echo "Creando colección '" . COLLECTION_NAME . "'... ";
    $qdrant->createCollection(COLLECTION_NAME, VECTOR_SIZE, 'Cosine');
    echo "OK\n";
}

// Buscar archivos a procesar
$files = array_merge(
    glob($knowledgeBasePath . '/*.pdf'),
    glob($knowledgeBasePath . '/*.txt'),
    glob($knowledgeBasePath . '/*.md')
);

if (empty($files)) {
    die("\nNo files found in: {$knowledgeBasePath}\n" .
        "Add PDFs, TXT, or Markdown files to that folder and run the script again.\n");
}

echo "\nArchivos encontrados: " . count($files) . "\n";

$totalChunks = 0;
$pointId = 1;

foreach ($files as $file) {
    $filename = basename($file);
    $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    
    echo "\n--- Procesando: {$filename} ---\n";
    
    // Extraer texto según formato
    $text = extractText($file, $extension);
    
    if (empty(trim($text))) {
        echo "  AVISO: Archivo vacío o no se pudo extraer texto\n";
        continue;
    }
    
    echo "  Texto extraído: " . strlen($text) . " caracteres\n";
    
    // Dividir en chunks
    $chunks = chunkText($text, CHUNK_SIZE, CHUNK_OVERLAP);
    echo "  Chunks generados: " . count($chunks) . "\n";
    
    // Procesar en batches
    $batches = array_chunk($chunks, BATCH_SIZE);
    
    foreach ($batches as $batchIndex => $batch) {
        echo "  Procesando batch " . ($batchIndex + 1) . "/" . count($batches) . "... ";
        
        // Generar embeddings
        $batchTexts = array_column($batch, 'text');
        $vectors = $embeddings->embedBatch($batchTexts);
        
        // Preparar puntos para Qdrant
        $points = [];
        foreach ($batch as $i => $chunk) {
            $points[] = [
                'id' => $pointId++,
                'vector' => $vectors[$i],
                'payload' => [
                    'text' => $chunk['text'],
                    'document_id' => pathinfo($filename, PATHINFO_FILENAME),
                    'document_name' => $filename,
                    'chunk_index' => $chunk['index'],
                    'section' => $chunk['section'] ?? '',
                    'char_start' => $chunk['char_start'],
                    'char_end' => $chunk['char_end']
                ]
            ];
        }
        
        // Insertar en Qdrant
        $qdrant->upsertPoints(COLLECTION_NAME, $points);
        echo "OK\n";
        
        $totalChunks += count($batch);
    }
}

echo "\n=== Ingesta completada ===\n";
echo "Total de chunks indexados: {$totalChunks}\n";

// Verificar
$count = $qdrant->countPoints(COLLECTION_NAME);
echo "Puntos en colección: {$count}\n";

// ============================================================================
// Funciones auxiliares
// ============================================================================

/**
 * Extrae texto de un archivo según su formato
 */
function extractText(string $file, string $extension): string
{
    switch ($extension) {
        case 'pdf':
            return extractTextFromPdf($file);
        case 'txt':
        case 'md':
            return file_get_contents($file);
        default:
            return '';
    }
}

/**
 * Extrae texto de un PDF usando pdftotext (poppler-utils)
 */
function extractTextFromPdf(string $file): string
{
    // Verificar que pdftotext está disponible
    exec('which pdftotext', $output, $returnCode);
    if ($returnCode !== 0) {
        echo "  AVISO: pdftotext no instalado. Instalar con: brew install poppler (Mac) o apt install poppler-utils (Linux)\n";
        return '';
    }
    
    $tempFile = sys_get_temp_dir() . '/lex_pdf_' . uniqid() . '.txt';
    $escapedFile = escapeshellarg($file);
    $escapedTemp = escapeshellarg($tempFile);
    
    // -layout mantiene el formato, -enc UTF-8 asegura codificación
    exec("pdftotext -layout -enc UTF-8 {$escapedFile} {$escapedTemp} 2>&1", $output, $returnCode);
    
    if ($returnCode !== 0 || !file_exists($tempFile)) {
        return '';
    }
    
    $text = file_get_contents($tempFile);
    unlink($tempFile);
    
    return $text;
}

/**
 * Divide texto en chunks con overlap
 * Intenta cortar en límites de párrafo/oración
 */
function chunkText(string $text, int $targetTokens, int $overlap): array
{
    // Aproximación: 1 token ≈ 4 caracteres en español
    $charsPerToken = 4;
    $targetChars = $targetTokens * $charsPerToken;
    $overlapChars = $overlap * $charsPerToken;
    
    // Limpiar texto
    $text = preg_replace('/\s+/', ' ', $text);
    $text = trim($text);
    
    $chunks = [];
    $start = 0;
    $index = 0;
    $length = strlen($text);
    
    while ($start < $length) {
        $end = min($start + $targetChars, $length);
        
        // Intentar cortar en fin de oración o párrafo
        if ($end < $length) {
            $lastPeriod = strrpos(substr($text, $start, $end - $start), '. ');
            $lastNewline = strrpos(substr($text, $start, $end - $start), "\n");
            $cutPoint = max($lastPeriod, $lastNewline);
            
            if ($cutPoint !== false && $cutPoint > ($targetChars * 0.5)) {
                $end = $start + $cutPoint + 1;
            }
        }
        
        $chunkText = trim(substr($text, $start, $end - $start));
        
        if (!empty($chunkText)) {
            // Detectar sección (si hay encabezado al inicio)
            $section = '';
            if (preg_match('/^(Artículo\s+\d+|CAPÍTULO\s+[IVXLC]+|Sección\s+\d+)[.:]\s*(.+?)(?:\n|$)/i', $chunkText, $matches)) {
                $section = trim($matches[1] . ': ' . $matches[2]);
            }
            
            $chunks[] = [
                'text' => $chunkText,
                'index' => $index++,
                'section' => $section,
                'char_start' => $start,
                'char_end' => $end
            ];
        }
        
        // Siguiente chunk con overlap
        $start = $end - $overlapChars;
        if ($start >= $length - $overlapChars) {
            break;
        }
    }
    
    return $chunks;
}
