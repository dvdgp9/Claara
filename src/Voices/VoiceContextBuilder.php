<?php
namespace Voices;

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
    }

    /**
     * Checks whether the Voice exists.
     */
    public function voiceExists(): bool
    {
        return isset(self::$voices[$this->voiceId]);
    }

    /**
     * Gets Voice information.
     */
    public function getVoiceInfo(): ?array
    {
        return self::$voices[$this->voiceId] ?? null;
    }

    /**
     * Builds the full system prompt for the Voice.
     */
    public function buildSystemPrompt(): ?string
    {
        if (!$this->voiceExists()) {
            return null;
        }

        $voice = self::$voices[$this->voiceId];
        
        // Base system prompt.
        $prompt = "# Identity\n";
        $prompt .= "You are **{$voice['name']}**, {$voice['role']}.\n\n";
        $prompt .= "## Description\n{$voice['description']}\n\n";
        $prompt .= "## Personality\n{$voice['personality']}\n\n";
        
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
     */
    public function listDocuments(): array
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

        return $docs;
    }

    /**
     * Gets all available Voices.
     */
    public static function getAllVoices(): array
    {
        return self::$voices;
    }

    /**
     * Checks whether the Voice has semantic index retrieval enabled.
     */
    public function hasRagEnabled(): bool
    {
        $voice = self::$voices[$this->voiceId] ?? null;
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

        $voice = self::$voices[$this->voiceId];
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
    public function buildSystemPromptWithRag(string $userQuery, int $topK = 15): ?string
    {
        if (!$this->voiceExists()) {
            return null;
        }

        $voice = self::$voices[$this->voiceId];
        
        // Get the list of all documents so the assistant knows what is available.
        $allDocs = $this->listDocuments();
        $docListText = "";
        foreach ($allDocs as $doc) {
            $docListText .= "- " . $doc['name'] . "\n";
        }

        // Base system prompt.
        $prompt = "# Identity\n";
        $prompt .= "You are **{$voice['name']}**, {$voice['role']}.\n\n";
        $prompt .= "## Description\n{$voice['description']}\n\n";
        $prompt .= "## Personality\n{$voice['personality']}\n\n";
        
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

        // Get relevant context through semantic index.
        if ($this->retriever && $this->retriever->isReady()) {
            // Detect whether the user mentioned a specific agreement to filter by.
            $documentFilter = $this->detectMentionedDocument($userQuery, $allDocs);
            
            $chunks = $this->retriever->retrieve($userQuery, $topK, $documentFilter);
            $ragContext = $this->retriever->formatForPrompt($chunks);
            $prompt .= $ragContext;
            
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
