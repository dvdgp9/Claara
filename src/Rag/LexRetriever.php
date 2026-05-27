<?php
namespace Rag;

/**
 * Specialized retriever for the Lex voice.
 */
class LexRetriever
{
    private QdrantClient $qdrant;
    private EmbeddingService $embeddings;
    private string $collection;

    public function __construct(
        QdrantClient $qdrant,
        EmbeddingService $embeddings,
        string $collection = 'lex_knowledge_base'
    ) {
        $this->qdrant = $qdrant;
        $this->embeddings = $embeddings;
        $this->collection = $collection;
    }

    /**
     * Busca los chunks más relevantes para una pregunta
     * 
     * @param string $query Pregunta del usuario
     * @param int $topK Número de chunks a recuperar
     * @param string|null $documentFilter Filtrar por documento específico
     * @return array Chunks relevantes con metadatos
     */
    public function retrieve(string $query, int $topK = 15, ?string $documentFilter = null): array
    {
        // Generar embedding de la pregunta
        $queryVector = $this->embeddings->embed($query);

        // Construir filtro si se especifica documento
        $filter = null;
        if ($documentFilter !== null) {
            $filter = [
                'must' => [
                    ['key' => 'document_id', 'match' => ['value' => $documentFilter]]
                ]
            ];
        }

        // Buscar en Qdrant
        $results = $this->qdrant->search($this->collection, $queryVector, $topK, $filter);

        // Formatear resultados
        $chunks = [];
        foreach ($results as $result) {
            $chunks[] = [
                'text' => $result['payload']['text'] ?? '',
                'document_id' => $result['payload']['document_id'] ?? '',
                'document_name' => $result['payload']['document_name'] ?? '',
                'document_date' => $result['payload']['document_date'] ?? null,
                'is_official_source' => !empty($result['payload']['is_official_source']),
                'source_authority' => $result['payload']['source_authority'] ?? null,
                'chunk_index' => $result['payload']['chunk_index'] ?? 0,
                'section' => $result['payload']['section'] ?? '',
                'score' => $result['score'] ?? 0
            ];
        }

        return $chunks;
    }

    /**
     * Formatea los chunks recuperados para inyectar en el prompt del LLM
     */
    public function formatForPrompt(array $chunks): string
    {
        if (empty($chunks)) {
            return "No relevant information was found in the knowledge base.";
        }

        $formatted = "## Relevant knowledge base excerpts\n\n";
        $formatted .= "Use the following excerpts to answer the request. Cite the source when you rely on this information.\n\n";

        foreach ($chunks as $i => $chunk) {
            $num = $i + 1;
            $formatted .= "### [{$num}] {$chunk['document_name']}";
            $metadata = [];
            if (!empty($chunk['document_date'])) {
                $metadata[] = 'date: ' . $chunk['document_date'];
            }
            $metadata[] = !empty($chunk['is_official_source']) ? 'official source: yes' : 'official source: no';
            if (!empty($chunk['source_authority'])) {
                $metadata[] = 'authority: ' . $chunk['source_authority'];
            }
            if (!empty($metadata)) {
                $formatted .= " (" . implode('; ', $metadata) . ")";
            }
            if (!empty($chunk['section'])) {
                $formatted .= " - {$chunk['section']}";
            }
            $formatted .= "\n";
            $formatted .= $chunk['text'] . "\n\n";
        }

        return $formatted;
    }

    /**
     * Verifica si la colección existe y tiene datos
     */
    public function isReady(): bool
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            return false;
        }
        return $this->qdrant->countPoints($this->collection) > 0;
    }

    /**
     * Obtiene estadísticas de la colección
     */
    public function getStats(): array
    {
        if (!$this->qdrant->collectionExists($this->collection)) {
            return ['exists' => false, 'points' => 0];
        }

        return [
            'exists' => true,
            'points' => $this->qdrant->countPoints($this->collection)
        ];
    }
}
