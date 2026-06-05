<?php
namespace Voices;

use App\Env;
use Chat\OpenRouterClient;
use Repos\UserFeatureAccessRepo;

class VoiceQueryService
{
    private UserFeatureAccessRepo $accessRepo;

    public function __construct(?UserFeatureAccessRepo $accessRepo = null)
    {
        $this->accessRepo = $accessRepo ?? new UserFeatureAccessRepo();
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
        if (!$this->accessRepo->hasVoiceAccess((int)$user['id'], $voiceId)) {
            throw new \RuntimeException('No tienes acceso a esta voz', 403);
        }

        $voiceContext = new VoiceContextBuilder($voiceId);
        if (!$voiceContext->voiceExists()) {
            throw new \RuntimeException('Voice not found', 404);
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

        $systemPrompt = $useRag
            ? $voiceContext->buildSystemPromptWithRag($message, 15)
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
