<?php
namespace Voices;

use Repos\VoicesRepo;
use Rag\QdrantClient;
use Rag\EmbeddingService;
use Rag\LexRetriever;

/**
 * Builds specialized context for each Voice.
 * Reads docs/context/voices/{voice_id}/ for specific knowledge.
 * For index-enabled Voices, uses semantic search instead of loading all context.
 */
class VoiceContextBuilder
{
    private string $voiceId;
    private string $contextPath;
    private ?LexRetriever $retriever = null;
    private ?array $voice = null;
    private array $lastChunks = [];
    
    // Available Voice definitions.
    private static array $voices = [
        'lex' => [
            'name' => 'Lex',
            'role' => 'Legal Assistant',
            'description' => 'Expert in collective agreements, labor rules, and legal reference documents.',
            'personality' => 'Professional, precise, and clear. Cite sources whenever possible.',
            'folder' => 'lex',
            'rag_enabled' => true,
            'rag_collection' => 'lex_knowledge_base'
        ],
        'cubo' => [
            'name' => 'Operations',
            'role' => 'Operations Assistant',
            'description' => 'Specialist in operational knowledge, procedures, and service details.',
            'personality' => 'Practical, clear, and solution-oriented.',
            'folder' => 'cubo'
        ],
        'uniges' => [
            'name' => 'Knowledge',
            'role' => 'Knowledge Assistant',
            'description' => 'Specialist in internal knowledge and documentation.',
            'personality' => 'Professional, efficient, and precise.',
            'folder' => 'uniges'
        ]
    ];

    public function __construct(string $voiceId)
    {
        $this->voiceId = $voiceId;
        $this->contextPath = dirname(dirname(__DIR__)) . '/docs/context/voices/' . $voiceId;
        $this->voice = $this->loadVoiceDefinition($voiceId);
    }

    private function loadVoiceDefinition(string $voiceId): ?array
    {
        try {
            $repo = new VoicesRepo();
            $voice = $repo->findBySlug($voiceId);
            if ($voice) {
                return $voice;
            }
        } catch (\Throwable $e) {
            // Keep legacy voices working if the dynamic schema is not available yet.
        }

        return self::$voices[$voiceId] ?? null;
    }

    /**
     * Checks whether the Voice exists.
     */
    public function voiceExists(): bool
    {
        return $this->voice !== null;
    }

    /**
     * Gets Voice information.
     */
    public function getVoiceInfo(): ?array
    {
        return $this->voice;
    }

    /**
     * Builds the full system prompt for the Voice.
     */
    public function buildSystemPrompt(): ?string
    {
        if (!$this->voiceExists()) {
            return null;
        }

        $voice = $this->voice;
        
        // Base system prompt.
        $prompt = "# Identity\n";
        $prompt .= "You are **{$voice['name']}**, {$voice['role']}.\n\n";
        $prompt .= "## Description\n{$voice['description']}\n\n";
        $prompt .= "## Personality\n{$voice['personality']}\n\n";
        if (!empty($voice['instructions'])) {
            $prompt .= "## Voice-specific instructions\n";
            $prompt .= trim((string)$voice['instructions']) . "\n\n";
        }
        
        // General instructions.
        $prompt .= "## Instructions\n";
        $prompt .= "- Respond in English by default, unless the user asks for another language.\n";
        $prompt .= "- Be concise but complete.\n";
        $prompt .= "- When citing documents, name the source.\n";
        $prompt .= "- If you do not have enough information, say so clearly.\n";
        $prompt .= "- Keep a professional, accessible tone.\n\n";

        // Cargar documentos de contexto específicos
        $contextDocs = $this->loadContextDocuments();
        if ($contextDocs) {
            $prompt .= "## Reference Documents\n";
            $prompt .= "Use the following documentation to answer questions:\n\n";
            $prompt .= $contextDocs;
        }

        return $prompt;
    }

    /**
     * Loads all .md documents from the Voice context folder.
     */
    private function loadContextDocuments(): string
    {
        if (!is_dir($this->contextPath)) {
            return '';
        }

        $content = '';
        $files = glob($this->contextPath . '/*.md');
        
        foreach ($files as $file) {
            $filename = basename($file, '.md');
            $fileContent = file_get_contents($file);
            
            if ($fileContent) {
                $content .= "### Document: " . ucfirst(str_replace('_', ' ', $filename)) . "\n";
                $content .= $fileContent . "\n\n";
            }
        }

        return $content;
    }

    /**
     * Lists available documents for this Voice, including indexed documents.
     *
     * @param int[]|null $allowedFolderIds Restricts the list to documents in
     *        these folders. null = no restriction (full access). [] = no
     *        accessible folders, returns an empty list (fail closed).
     */
    public function listDocuments(?array $allowedFolderIds = null): array
    {
        $docs = [];
        
        // 1. Static documents (.md)
        if (is_dir($this->contextPath)) {
            $files = glob($this->contextPath . '/*.md');
            foreach ($files as $file) {
                $filename = basename($file, '.md');
                $docs[] = [
                    'id' => $filename,
                    'name' => ucfirst(str_replace('_', ' ', $filename)),
                    'type' => 'static',
                    'path' => $file,
                    'size' => filesize($file)
                ];
            }
        }

        // 2. Indexed documents (PDF/TXT/MD inside the knowledge-base subfolder).
        $ragPath = $this->contextPath . '/knowledge-base';
        if (is_dir($ragPath)) {
            $files = glob($ragPath . '/*.{pdf,txt,md}', GLOB_BRACE);
            foreach ($files as $file) {
                $filename = basename($file);
                if ($filename === 'README.md') continue;
                
                $docs[] = [
                    'id' => 'indexed_' . md5($filename),
                    'name' => $filename,
                    'type' => 'indexed',
                    'path' => $file,
                    'size' => filesize($file)
                ];
            }
        }

        // Folder-based access filter. Cross-reference the filesystem listing with
        // the document folder assignments in the database. Documents without a
        // matching accessible record (e.g. legacy static files for a restricted
        // user) are dropped — fail closed.
        if ($allowedFolderIds !== null) {
            try {
                $allowed = (new \Repos\ContextDocsRepo())
                    ->accessibleFilenameSet($this->voiceId, $allowedFolderIds);
                $docs = array_values(array_filter($docs, static function (array $d) use ($allowed): bool {
                    $key = \Repos\ContextDocsRepo::normalizeDocFilename(
                        (string)($d['path'] ?? $d['name'] ?? '')
                    );
                    return $key !== '' && isset($allowed[$key]);
                }));
            } catch (\Throwable $e) {
                // Fail closed: if the access set cannot be resolved, expose nothing.
                return [];
            }
        }

        return $docs;
    }

    /**
     * Gets all available Voices.
     */
    public static function getAllVoices(): array
    {
        try {
            $repo = new VoicesRepo();
            $voices = [];
            foreach ($repo->listPublished() as $voice) {
                $voices[$voice['slug']] = $voice;
            }
            if ($voices) {
                return $voices;
            }
        } catch (\Throwable $e) {
            // Fall back to legacy definitions.
        }

        return self::$voices;
    }

    /**
     * Checks whether the Voice has semantic index retrieval enabled.
     */
    public function hasRagEnabled(): bool
    {
        $voice = $this->voice;
        return $voice && ($voice['rag_enabled'] ?? false);
    }

    /**
     * Initializes the semantic retriever for this Voice.
     */
    public function initRetriever(string $openaiKey, string $qdrantHost = 'localhost', int $qdrantPort = 6333): void
    {
        if (!$this->hasRagEnabled()) {
            return;
        }

        $voice = $this->voice;
        $collection = $voice['rag_collection'] ?? 'default';

        $qdrant = new QdrantClient($qdrantHost, $qdrantPort);
        $embeddings = new EmbeddingService($openaiKey);
        $this->retriever = new LexRetriever($qdrant, $embeddings, $collection);
    }

    /**
     * Gets the semantic retriever.
     */
    public function getRetriever(): ?LexRetriever
    {
        return $this->retriever;
    }

    /**
     * Builds the system prompt with indexed context.
     * Uses semantic search to find relevant chunks.
     */
    public function buildSystemPromptWithRag(string $userQuery, int $topK = 15, ?array $allowedFolderIds = null): ?string
    {
        if (!$this->voiceExists()) {
            return null;
        }

        $voice = $this->voice;

        // Get the list of accessible documents so the assistant knows what is
        // available WITHOUT leaking names of documents outside the user's folders.
        $allDocs = $this->listDocuments($allowedFolderIds);
        $docListText = "";
        foreach ($allDocs as $doc) {
            $docListText .= "- " . $doc['name'] . "\n";
        }

        // Base system prompt.
        $prompt = "# Identity\n";
        $prompt .= "You are **{$voice['name']}**, {$voice['role']}.\n\n";
        $prompt .= "## Description\n{$voice['description']}\n\n";
        $prompt .= "## Personality\n{$voice['personality']}\n\n";
        if (!empty($voice['instructions'])) {
            $prompt .= "## Voice-specific instructions\n";
            $prompt .= trim((string)$voice['instructions']) . "\n\n";
        }
        
        $prompt .= "## Available Documentation\n";
        $prompt .= "You have access to the following documents and collective agreements:\n";
        $prompt .= $docListText . "\n";

        // General instructions.
        $prompt .= "## Instructions\n";
        $prompt .= "- Respond in English by default, unless the user asks for another language.\n";
        $prompt .= "- Be concise but complete.\n";
        $prompt .= "- **IMPORTANT**: Always cite the exact document name used as the source.\n";
        $prompt .= "- If the user asks what documents or agreements you have, provide the list above.\n";
        $prompt .= "- If the retrieved fragments are not sufficient, say so clearly.\n";
        $prompt .= "- Keep a professional, accessible tone.\n";
        $prompt .= "- Do not invent information that is not in the provided documentation.\n\n";

        // Structured output contract (parsed by the backend).
        $prompt .= "## Output Format (STRICT)\n";
        $prompt .= "Return ONLY a single valid JSON object, with no surrounding text and no Markdown code fences. Use exactly this shape:\n";
        $prompt .= "{\n";
        $prompt .= "  \"answer_markdown\": \"<your full answer, formatted in Markdown>\",\n";
        $prompt .= "  \"sources\": [\"<exact document name you relied on>\"],\n";
        $prompt .= "  \"conflicts\": [{\"topic\": \"<short topic>\", \"sources\": [\"<doc A>\", \"<doc B>\"], \"note\": \"<how they disagree>\"}],\n";
        $prompt .= "  \"conflict_summary\": {\n";
        $prompt .= "    \"has_conflict\": false,\n";
        $prompt .= "    \"topic\": \"<topic analysed>\",\n";
        $prompt .= "    \"documents_considered\": 0,\n";
        $prompt .= "    \"positions\": [{\"claim\": \"<distinct answer/position>\", \"document_count\": 0, \"sources\": [\"<doc>\"]}],\n";
        $prompt .= "    \"most_recent\": {\"claim\": \"<claim from most recent document>\", \"source\": \"<doc>\", \"date\": \"<YYYY-MM-DD or unknown>\"},\n";
        $prompt .= "    \"official_source_note\": \"<state whether any source is explicitly marked official; say unknown/not marked when absent>\"\n";
        $prompt .= "  }\n";
        $prompt .= "}\n";
        $prompt .= "- \"sources\": exact names of the documents you actually used. Use an empty array if you used none.\n";
        $prompt .= "- \"conflicts\": include an entry ONLY when the retrieved excerpts contradict each other on a point relevant to your answer. Use an empty array when there is no conflict.\n";
        $prompt .= "- \"conflict_summary\": analyse only retrieved excerpts relevant to the user's question. Set has_conflict=true only when two or more retrieved documents give incompatible answers, values, dates, rules, thresholds, or obligations for the same topic.\n";
        $prompt .= "- For conflict_summary.positions, group equivalent claims and count distinct documents, not chunks.\n";
        $prompt .= "- For most_recent, use explicit dates from the document text or filename only. If no clear date exists, use date=\"unknown\" and do not infer recency.\n";
        $prompt .= "- For official_source_note, never infer official status from confidence or wording. Say sources are not marked as official when no retrieved excerpt explicitly marks them so.\n";
        $prompt .= "- Never output anything outside the JSON object.\n\n";

        // Get relevant context through semantic index.
        if ($this->retriever && $this->retriever->isReady()) {
            // Detect whether the user mentioned a specific agreement to filter by.
            $documentFilter = $this->detectMentionedDocument($userQuery, $allDocs);

            $chunks = $this->retriever->retrieve($userQuery, $topK, $documentFilter, $allowedFolderIds);
            $this->lastChunks = $chunks;
            $ragContext = $this->retriever->formatForPrompt($chunks);
            $prompt .= $ragContext;
            $prompt .= $this->buildRetrievedDocumentSummary($chunks);
            
            // If filtered by document, state it.
            if ($documentFilter) {
                $prompt .= "\n*Search filtered to document: {$documentFilter}*\n";
            }
        } else {
            // Fallback to static documents if semantic index is not available.
            $contextDocs = $this->loadContextDocuments();
            if ($contextDocs) {
                $prompt .= "## Reference Documents\n";
                $prompt .= "Use the following documentation to answer questions:\n\n";
                $prompt .= $contextDocs;
            }
        }

        return $prompt;
    }

    private function buildRetrievedDocumentSummary(array $chunks): string
    {
        if (empty($chunks)) {
            return '';
        }

        $documents = [];
        foreach ($chunks as $chunk) {
            $name = (string)($chunk['document_name'] ?? 'Unknown document');
            if (!isset($documents[$name])) {
                $documents[$name] = [
                    'chunks' => 0,
                    'max_score' => 0.0,
                    'sections' => [],
                    'document_date' => $chunk['document_date'] ?? null,
                    'is_official_source' => !empty($chunk['is_official_source']),
                    'source_authority' => $chunk['source_authority'] ?? null,
                ];
            }
            $documents[$name]['chunks']++;
            $documents[$name]['max_score'] = max($documents[$name]['max_score'], (float)($chunk['score'] ?? 0));
            $section = trim((string)($chunk['section'] ?? ''));
            if ($section !== '') {
                $documents[$name]['sections'][$section] = true;
            }
        }

        $summary = "\n## Retrieved document coverage\n";
        $summary .= "Use this only to reason about whether multiple documents discuss the same topic and may disagree. Scores are retrieval relevance, not authority.\n";
        foreach ($documents as $name => $info) {
            $sections = array_keys($info['sections']);
            $summary .= "- {$name}: {$info['chunks']} relevant excerpt(s), max retrieval score " . round($info['max_score'], 3);
            if (!empty($info['document_date'])) {
                $summary .= ", document date: {$info['document_date']}";
            } else {
                $summary .= ", document date: unknown";
            }
            $summary .= !empty($info['is_official_source']) ? ", official source: yes" : ", official source: no";
            if (!empty($info['source_authority'])) {
                $summary .= ", authority: {$info['source_authority']}";
            }
            if (!empty($sections)) {
                $summary .= ", sections: " . implode('; ', array_slice($sections, 0, 3));
            }
            $summary .= "\n";
        }
        $summary .= "\n";

        return $summary;
    }

    /**
     * Computes a deterministic "source match" indicator from the last
     * retrieved chunks' similarity scores. This reflects how well the
     * knowledge base matched the question, NOT factual accuracy.
     *
     * @return array{percent:int, band:string}|null null when there are no chunks
     */
    public function computeSourceMatch(?string $answer = null): ?array
    {
        if (empty($this->lastChunks)) {
            return null;
        }

        $scores = array_map(static fn($c) => (float)($c['score'] ?? 0), $this->lastChunks);
        rsort($scores);
        $top = array_slice($scores, 0, 3);
        $avg = array_sum($top) / count($top);
        $semanticPercent = (int) round(max(0.0, min(1.0, $avg)) * 100);

        $evidencePercent = $answer !== null ? $this->computeEvidenceCoverage($answer) : null;
        $percent = $evidencePercent !== null ? max($semanticPercent, $evidencePercent) : $semanticPercent;

        $band = $percent >= 80 ? 'high' : ($percent >= 50 ? 'medium' : 'low');

        return [
            'percent' => $percent,
            'band' => $band,
        ];
    }

    private function computeEvidenceCoverage(string $answer): ?int
    {
        $answerTokens = $this->meaningfulTokens($answer);
        if (count($answerTokens) < 4) {
            return null;
        }

        $sourceText = implode("\n", array_map(
            static fn($chunk) => (string)($chunk['text'] ?? ''),
            $this->lastChunks
        ));
        $sourceTokens = array_flip($this->meaningfulTokens($sourceText));
        if (empty($sourceTokens)) {
            return null;
        }

        $matched = 0;
        foreach ($answerTokens as $token) {
            if (isset($sourceTokens[$token])) {
                $matched++;
            }
        }

        return (int) round(($matched / count($answerTokens)) * 100);
    }

    /**
     * Tokeniza texto para comparar evidencia literal sin que artículos y preposiciones dominen el resultado.
     *
     * @return array<int, string>
     */
    private function meaningfulTokens(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii)) {
            $text = $ascii;
        }

        preg_match_all('/[a-z0-9][a-z0-9\.]{1,}/i', $text, $matches);
        $stopwords = array_flip([
            'ademas', 'ante', 'bajo', 'cada', 'como', 'con', 'contra', 'cual', 'cuando',
            'desde', 'donde', 'entre', 'esta', 'este', 'estos', 'estas', 'para', 'pero',
            'por', 'que', 'segun', 'sin', 'sobre', 'sus', 'una', 'uno', 'unos', 'unas',
            'del', 'las', 'los', 'les', 'the', 'and', 'for', 'from', 'that', 'this', 'with',
        ]);

        $tokens = [];
        foreach ($matches[0] ?? [] as $token) {
            $token = trim($token, '.');
            if (strlen($token) < 3 && !ctype_digit($token)) {
                continue;
            }
            if (isset($stopwords[$token])) {
                continue;
            }
            $tokens[$token] = true;
        }

        return array_keys($tokens);
    }

    /**
     * Checks whether the semantic retriever is ready.
     */
    public function isRagReady(): bool
    {
        return $this->retriever && $this->retriever->isReady();
    }

    /**
     * Gets semantic index statistics.
     */
    public function getRagStats(): array
    {
        if (!$this->retriever) {
            return ['enabled' => false];
        }

        $stats = $this->retriever->getStats();
        $stats['enabled'] = true;
        return $stats;
    }

    /**
     * Detects whether the user mentions a specific source document.
     * @return string|null document_id when detected, null otherwise
     */
    private function detectMentionedDocument(string $query, array $documents): ?string
    {
        $queryLower = mb_strtolower($query);
        
        // Sector keywords for matching legacy document labels if a compatible corpus is added later.
        $sectorKeywords = [
            'agencia de viajes' => 'CC29',
            'agencias de viajes' => 'CC29',
            'viajes' => 'CC29',
            'instalaciones deportivas' => 'CC1',
            'gimnasios' => 'CC1',
            'gimnasio' => 'CC1',
            'ocio educativo' => 'CC10',
            'animación sociocultural' => 'CC10',
            'limpieza' => 'CC20',
            'dependientes' => 'CC22',
            'personas dependientes' => 'CC22',
            'atención a personas' => 'CC22',
            'discapacidad' => 'CC25',
            'acción social' => 'CC26',
            'intervención social' => 'CC26',
            'socorrismo' => 'CC34',
            'salvamento' => 'CC34',
            'residencias' => 'CC4',
            'centros de día' => 'CC4',
            'deportes' => 'CC5',
            'enseñanza' => 'CC12',
            'formación' => 'CC12',
        ];
        
        // Buscar coincidencia de sector en la query
        foreach ($sectorKeywords as $keyword => $docPrefix) {
            if (mb_strpos($queryLower, $keyword) !== false) {
                // Buscar el documento que coincida con este prefijo
                foreach ($documents as $doc) {
                    if (isset($doc['name']) && strpos($doc['name'], $docPrefix) === 0) {
                        // Devolver el nombre del archivo sin extensión como document_id
                        return pathinfo($doc['name'], PATHINFO_FILENAME);
                    }
                }
            }
        }
        
        // Buscar mención directa de código CC
        if (preg_match('/\bCC\s*(\d+)\b/i', $query, $matches)) {
            $ccNumber = $matches[1];
            foreach ($documents as $doc) {
                if (isset($doc['name']) && preg_match("/^CC{$ccNumber}\b/", $doc['name'])) {
                    return pathinfo($doc['name'], PATHINFO_FILENAME);
                }
            }
        }
        
        return null;
    }
}
