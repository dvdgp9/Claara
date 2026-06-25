<?php
namespace Voices;

use App\Env;
use Chat\OpenRouterClient;
use Repos\UserFeatureAccessRepo;

class VoiceQueryService
{
    private UserFeatureAccessRepo $accessRepo;
    private VoiceAccessResolver $accessResolver;

    public function __construct(?UserFeatureAccessRepo $accessRepo = null, ?VoiceAccessResolver $accessResolver = null)
    {
        $this->accessRepo = $accessRepo ?? new UserFeatureAccessRepo();
        $this->accessResolver = $accessResolver ?? new VoiceAccessResolver();
    }

    public function query(array $user, string $voiceId, string $message, array $history = []): array
    {
        $voiceId = trim($voiceId);
        $message = trim($message);

        if ($voiceId === '') {
            throw new \InvalidArgumentException('voice_id is required');
        }
        if ($message === '') {
            throw new \InvalidArgumentException('message is required');
        }
        $voiceContext = new VoiceContextBuilder($voiceId);
        if (!$voiceContext->voiceExists()) {
            throw new \RuntimeException('Voice not found', 404);
        }
        $voice = $voiceContext->getVoiceInfo() ?? ['slug' => $voiceId];
        $userId = (int)$user['id'];

        // Access gate: a user can open the voice if they are a superadmin, the
        // voice's responsible, or have an access profile in it.
        if (!$this->accessResolver->hasVoiceAccess($userId, $voice)) {
            throw new \RuntimeException('No tienes acceso a esta voz', 403);
        }

        // Folders this user may read from. null = full access (no folder filter);
        // [] = has the voice but no folders granted -> fail closed.
        $fullAccess = $this->accessResolver->hasFullAccess($userId, $voice);
        $allowedFolderIds = $fullAccess
            ? null
            : $this->accessResolver->resolveAccessibleFolderIds($userId, $voice);

        if ($allowedFolderIds !== null && count($allowedFolderIds) === 0) {
            return $this->noAccessibleDocumentsResponse($voice);
        }

        $useRag = false;
        if ($voiceContext->hasRagEnabled()) {
            $openrouterKey = Env::get('OPENROUTER_API_KEY');
            $qdrantHost = Env::get('QDRANT_HOST', 'localhost');
            $qdrantPort = (int)Env::get('QDRANT_PORT', 6333);

            if ($openrouterKey) {
                $voiceContext->initRetriever($openrouterKey, $qdrantHost, $qdrantPort);
                $useRag = $voiceContext->isRagReady();
            }
        }

        // A restricted user on a RAG voice must never fall back to the unfiltered
        // static-context prompt. If retrieval is unavailable, fail closed.
        if ($voiceContext->hasRagEnabled() && !$useRag && !$fullAccess) {
            return $this->noAccessibleDocumentsResponse($voice, true);
        }

        $systemPrompt = $useRag
            ? $voiceContext->buildSystemPromptWithRag($message, 15, $allowedFolderIds)
            : $voiceContext->buildSystemPrompt();

        $messages = [];
        foreach ($history as $msg) {
            if (isset($msg['role'], $msg['content'])) {
                $messages[] = [
                    'role' => (string)$msg['role'],
                    'content' => (string)$msg['content'],
                ];
            }
        }
        $messages[] = ['role' => 'user', 'content' => $message];

        $client = new OpenRouterClient(
            Env::get('OPENROUTER_API_KEY'),
            'google/gemini-3-flash-preview',
            $systemPrompt
        );

        $reply = $client->generateWithMessages($messages);
        $parsed = self::parseVoiceReply($reply);
        $answer = $parsed['answer'];

        return [
            'answer' => $answer,
            'meta' => [
                'source_match' => $useRag ? $voiceContext->computeSourceMatch($answer) : null,
                'sources' => $parsed['sources'],
                'conflicts' => $parsed['conflicts'],
                'conflict_summary' => $parsed['conflict_summary'],
            ],
            'model' => $client->getModel(),
            'voice' => $voiceContext->getVoiceInfo(),
        ];
    }

    /**
     * Safe response when the user has no documents they may read in this voice.
     * Never invokes the model or exposes any document content (fail closed).
     */
    private function noAccessibleDocumentsResponse(array $voice, bool $temporary = false): array
    {
        $answer = $temporary
            ? "I can't reach this voice's documents right now. Please try again in a moment."
            : "I couldn't find any documents you have access to in this voice. If you think this is a mistake, please contact an administrator.";

        return [
            'answer' => $answer,
            'meta' => [
                'source_match' => null,
                'sources' => [],
                'conflicts' => [],
                'conflict_summary' => null,
            ],
            'model' => null,
            'voice' => $voice,
        ];
    }

    public static function parseVoiceReply(string $raw): array
    {
        $text = trim($raw);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
            $text = preg_replace('/\s*```$/', '', $text);
            $text = trim($text);
        }

        $data = json_decode($text, true);
        if (is_array($data) && isset($data['answer_markdown'])) {
            $sources = [];
            foreach ((array)($data['sources'] ?? []) as $source) {
                $source = trim((string)$source);
                if ($source !== '') {
                    $sources[] = $source;
                }
            }

            $conflicts = [];
            foreach ((array)($data['conflicts'] ?? []) as $conflict) {
                if (is_array($conflict) && (isset($conflict['topic']) || isset($conflict['note']))) {
                    $conflicts[] = [
                        'topic' => trim((string)($conflict['topic'] ?? '')),
                        'sources' => array_values(array_filter(array_map(
                            static fn($x) => trim((string)$x),
                            (array)($conflict['sources'] ?? [])
                        ), static fn($x) => $x !== '')),
                        'note' => trim((string)($conflict['note'] ?? '')),
                    ];
                }
            }

            return [
                'answer' => (string)$data['answer_markdown'],
                'sources' => array_values(array_unique($sources)),
                'conflicts' => $conflicts,
                'conflict_summary' => self::normalizeConflictSummary($data['conflict_summary'] ?? null),
            ];
        }

        return ['answer' => $raw, 'sources' => [], 'conflicts' => [], 'conflict_summary' => null];
    }

    private static function normalizeConflictSummary(mixed $raw): ?array
    {
        if (!is_array($raw)) {
            return null;
        }

        $hasConflict = filter_var($raw['has_conflict'] ?? false, FILTER_VALIDATE_BOOLEAN);
        if (!$hasConflict) {
            return null;
        }

        $positions = [];
        foreach ((array)($raw['positions'] ?? []) as $position) {
            if (!is_array($position)) {
                continue;
            }
            $claim = trim((string)($position['claim'] ?? ''));
            if ($claim === '') {
                continue;
            }
            $sources = array_values(array_filter(array_map(
                static fn($x) => trim((string)$x),
                (array)($position['sources'] ?? [])
            ), static fn($x) => $x !== ''));
            $positions[] = [
                'claim' => $claim,
                'document_count' => max(0, (int)($position['document_count'] ?? count($sources))),
                'sources' => $sources,
            ];
        }

        if (count($positions) < 2) {
            return null;
        }

        $mostRecent = null;
        if (isset($raw['most_recent']) && is_array($raw['most_recent'])) {
            $mostRecent = [
                'claim' => trim((string)($raw['most_recent']['claim'] ?? '')),
                'source' => trim((string)($raw['most_recent']['source'] ?? '')),
                'date' => trim((string)($raw['most_recent']['date'] ?? 'unknown')) ?: 'unknown',
            ];
        }

        return [
            'has_conflict' => true,
            'topic' => trim((string)($raw['topic'] ?? '')),
            'documents_considered' => max(0, (int)($raw['documents_considered'] ?? 0)),
            'positions' => $positions,
            'most_recent' => $mostRecent,
            'official_source_note' => trim((string)($raw['official_source_note'] ?? '')),
        ];
    }
}
