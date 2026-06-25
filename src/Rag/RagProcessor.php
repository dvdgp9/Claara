<?php
namespace Rag;

use App\Env;
use Smalot\PdfParser\Parser;

/**
 * Procesador RAG reutilizable para documentos de Lex.
 * Extrae texto, hace chunking, genera embeddings e inserta en Qdrant.
 */
class RagProcessor
{
    private QdrantClient $qdrant;
    private EmbeddingService $embeddings;
    private string $collection;
    
    private const VECTOR_SIZE = 4096;  // qwen/qwen3-embedding-8b
    private const CHUNK_SIZE = 500;    // tokens aproximados por chunk
    private const CHUNK_OVERLAP = 50;  // tokens de overlap
    private const BATCH_SIZE = 10;     // chunks por batch de embeddings

    public function __construct(
        ?QdrantClient $qdrant = null,
        ?EmbeddingService $embeddings = null,
        string $collection = 'lex_knowledge_base'
    ) {
        $this->collection = $collection;
        
        $this->qdrant = $qdrant ?? new QdrantClient(
            Env::get('QDRANT_HOST', 'localhost'),
            (int)Env::get('QDRANT_PORT', 6333)
        );
        
        $openrouterKey = Env::get('OPENROUTER_API_KEY');
        $this->embeddings = $embeddings ?? new EmbeddingService($openrouterKey);
    }

    /**
     * Verifica si Qdrant está disponible
     */
    public function isQdrantHealthy(): bool
    {
        return $this->qdrant->health();
    }

    /**
     * Asegura que la colección existe
     */
    public function ensureCollection(): void
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            $this->qdrant->createCollection($this->collection, self::VECTOR_SIZE, 'Cosine');
        }
    }

    /**
     * Procesa un documento y lo indexa en Qdrant
     * 
     * @param string $filePath Ruta completa al archivo
     * @param string $documentId ID único del documento (normalmente filename sin extensión)
     * @param string $documentName Nombre para mostrar
     * @return array Resultado con estadísticas
     */
    public function processDocument(string $filePath, string $documentId, string $documentName, array $metadata = []): array
    {
        if (!file_exists($filePath)) {
            throw new \Exception("Archivo no encontrado: {$filePath}");
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        // 1. Extraer texto
        $text = $this->normalizeExtractedText($this->extractText($filePath, $extension));
        if (empty(trim($text))) {
            throw new \Exception("No se pudo extraer texto del archivo");
        }

        // 2. Dividir en chunks
        $chunks = $this->chunkText($text, self::CHUNK_SIZE, self::CHUNK_OVERLAP);
        if (empty($chunks)) {
            throw new \Exception("No se generaron chunks del documento");
        }

        // 3. Asegurar que la colección existe
        $this->ensureCollection();

        // 4. Eliminar vectores previos del mismo documento
        $this->deleteDocumentVectors($documentId);

        // 5. Obtener siguiente ID disponible
        $pointId = $this->qdrant->getNextPointId($this->collection);

        // 6. Procesar en batches
        $batches = array_chunk($chunks, self::BATCH_SIZE);
        $totalChunks = 0;

        foreach ($batches as $batch) {
            // Generar embeddings
            $batchTexts = array_column($batch, 'text');
            $vectors = $this->embeddings->embedBatch($batchTexts);

            // Preparar puntos para Qdrant
            $points = [];
            foreach ($batch as $i => $chunk) {
                $points[] = [
                    'id' => $pointId++,
                    'vector' => $vectors[$i],
                    'payload' => [
                        'text' => $chunk['text'],
                        'document_id' => $documentId,
                        'document_name' => $documentName,
                        // Folder this document belongs to. Used at query time to
                        // restrict retrieval to the folders a user's profile may read.
                        'folder_id' => isset($metadata['folder_id']) ? (int)$metadata['folder_id'] : null,
                        'document_date' => $metadata['document_date'] ?? null,
                        'is_official_source' => !empty($metadata['is_official_source']),
                        'source_authority' => $metadata['source_authority'] ?? null,
                        'chunk_index' => $chunk['index'],
                        'section' => $chunk['section'] ?? '',
                        'char_start' => $chunk['char_start'],
                        'char_end' => $chunk['char_end']
                    ]
                ];
            }

            // Insertar en Qdrant
            $this->qdrant->upsertPoints($this->collection, $points);
            $totalChunks += count($batch);
        }

        return [
            'success' => true,
            'document_id' => $documentId,
            'chunks_processed' => $totalChunks,
            'text_length' => strlen($text),
            'collection' => $this->collection
        ];
    }

    /**
     * Elimina todos los vectores de un documento
     */
    public function deleteDocumentVectors(string $documentId): int
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            return 0;
        }

        $filter = [
            'must' => [
                ['key' => 'document_id', 'match' => ['value' => $documentId]]
            ]
        ];

        $count = $this->qdrant->countPointsByFilter($this->collection, $filter);
        
        if ($count > 0) {
            $this->qdrant->deletePointsByFilter($this->collection, $filter);
        }

        return $count;
    }

    /**
     * Obtiene el número de chunks de un documento
     */
    public function getDocumentChunkCount(string $documentId): int
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            return 0;
        }

        return $this->qdrant->countPointsByFilter($this->collection, [
            'must' => [
                ['key' => 'document_id', 'match' => ['value' => $documentId]]
            ]
        ]);
    }

    /**
     * Extrae texto de un archivo según su formato
     */
    private function extractText(string $file, string $extension): string
    {
        switch ($extension) {
            case 'pdf':
                return $this->extractTextFromPdf($file);
            case 'txt':
            case 'md':
                return file_get_contents($file);
            default:
                return '';
        }
    }

    /**
     * Extrae texto de un PDF usando PDFParser (librería PHP)
     */
    private function extractTextFromPdf(string $file): string
    {
        try {
            $parser = new Parser();
            $pdf = $parser->parseFile($file);
            $text = $pdf->getText();
            
            if (empty(trim($text))) {
                throw new \Exception("No se pudo extraer texto del PDF");
            }
            
            return $text;
        } catch (\Exception $e) {
            throw new \Exception("Error al extraer texto del PDF: " . $e->getMessage());
        }
    }

    /**
     * Normaliza caracteres basura habituales de extracción PDF antes de indexar.
     */
    private function normalizeExtractedText(string $text): string
    {
        $text = str_replace(["\0", "\u{FFFD}", "\u{FFFF}"], ' ', $text);

        if (function_exists('mb_check_encoding') && !mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8');
            if (is_string($converted)) {
                $text = $converted;
            }
        }

        $text = preg_replace('/[\p{C}]+/u', ' ', $text);
        if ($text === null) {
            return '';
        }

        return trim(preg_replace('/[ \t]+/', ' ', $text) ?? $text);
    }

    /**
     * Divide texto en chunks con overlap
     */
    private function chunkText(string $text, int $targetTokens, int $overlap): array
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

    /**
     * Obtiene estadísticas de la colección
     */
    public function getCollectionStats(): array
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            return ['exists' => false, 'points' => 0];
        }

        return [
            'exists' => true,
            'points' => $this->qdrant->countPoints($this->collection),
            'collection' => $this->collection
        ];
    }
}
