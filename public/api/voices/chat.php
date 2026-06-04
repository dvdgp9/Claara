<?php
/**
 * API: Chat con una voz especializada
 * POST /api/voices/chat.php
 * Body JSON: { voice_id, message, history?, execution_id? }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../../src/Chat/LlmProvider.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterProvider.php';
require_once __DIR__ . '/../../../src/Chat/LlmProviderFactory.php';
require_once __DIR__ . '/../../../src/Voices/VoiceExecutionsRepo.php';
require_once __DIR__ . '/../../../src/Voices/VoiceContextBuilder.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';
require_once __DIR__ . '/../../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../../src/Rag/QdrantClient.php';
require_once __DIR__ . '/../../../src/Rag/EmbeddingService.php';
require_once __DIR__ . '/../../../src/Rag/LexRetriever.php';

use App\Session;
use App\Response;
use App\Env;
use Chat\OpenRouterClient;
use Voices\VoiceExecutionsRepo;
use Voices\VoiceContextBuilder;
use Repos\UsageLogRepo;
use Repos\UserFeatureAccessRepo;

/**
 * Parses the LLM reply into answer + metadata.
 * Expects a JSON object { answer_markdown, sources[], conflicts[] }.
 * Falls back to treating the whole reply as plain text when parsing fails,
 * so a malformed response never breaks the chat.
 *
 * @return array{answer:string, sources:array, conflicts:array, conflict_summary:?array}
 */
function parseVoiceReply(string $raw): array
{
    $text = trim($raw);

    // Strip ```json ... ``` fences if the model added them despite instructions.
    if (str_starts_with($text, '```')) {
        $text = preg_replace('/^```(?:json)?\s*/i', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);
    }

    $data = json_decode($text, true);
    if (is_array($data) && isset($data['answer_markdown'])) {
        $sources = [];
        foreach ((array)($data['sources'] ?? []) as $s) {
            $s = trim((string)$s);
            if ($s !== '') {
                $sources[] = $s;
            }
        }

        $conflicts = [];
        foreach ((array)($data['conflicts'] ?? []) as $c) {
            if (is_array($c) && (isset($c['topic']) || isset($c['note']))) {
                $conflicts[] = [
                    'topic' => trim((string)($c['topic'] ?? '')),
                    'sources' => array_values(array_filter(array_map(
                        static fn($x) => trim((string)$x),
                        (array)($c['sources'] ?? [])
                    ), static fn($x) => $x !== '')),
                    'note' => trim((string)($c['note'] ?? '')),
                ];
            }
        }

        $conflictSummary = normalizeConflictSummary($data['conflict_summary'] ?? null);

        return [
            'answer' => (string)$data['answer_markdown'],
            'sources' => array_values(array_unique($sources)),
            'conflicts' => $conflicts,
            'conflict_summary' => $conflictSummary,
        ];
    }

    // Fallback: render the raw text, no metadata.
    return ['answer' => $raw, 'sources' => [], 'conflicts' => [], 'conflict_summary' => null];
}

function normalizeConflictSummary(mixed $raw): ?array
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

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Invalid CSRF token', 403);
}

// Solo POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$voiceId = $body['voice_id'] ?? '';
$message = trim($body['message'] ?? '');
$history = $body['history'] ?? [];
$executionId = $body['execution_id'] ?? null;

if (!$voiceId) {
    Response::error('missing_voice', 'voice_id is required', 400);
}
if (!$message) {
    Response::error('missing_message', 'message is required', 400);
}

$accessRepo = new UserFeatureAccessRepo();
if (!$accessRepo->hasVoiceAccess((int)$user['id'], $voiceId)) {
    Response::error('forbidden', 'No tienes acceso a esta voz', 403);
}

// Obtener contexto especializado de la voz
$voiceContext = new VoiceContextBuilder($voiceId);

if (!$voiceContext->voiceExists()) {
    Response::error('invalid_voice', 'Voice not found', 404);
}

// Try semantic index retrieval if enabled for this voice
$useRag = false;
if ($voiceContext->hasRagEnabled()) {
    $openrouterKey = Env::get('OPENROUTER_API_KEY');
    $qdrantHost = Env::get('QDRANT_HOST', 'localhost');
    $qdrantPort = (int) Env::get('QDRANT_PORT', 6333);

    if ($openrouterKey) {
        $voiceContext->initRetriever($openrouterKey, $qdrantHost, $qdrantPort);
        $useRag = $voiceContext->isRagReady();
    }
}

// Build system prompt (with indexed context when available)
if ($useRag) {
    $systemPrompt = $voiceContext->buildSystemPromptWithRag($message, 15);
} else {
    $systemPrompt = $voiceContext->buildSystemPrompt();
}

// Construir mensajes para el LLM
$messages = [];

// Añadir historial si existe
foreach ($history as $msg) {
    if (isset($msg['role']) && isset($msg['content'])) {
        $messages[] = [
            'role' => $msg['role'],
            'content' => $msg['content']
        ];
    }
}

// Añadir mensaje actual
$messages[] = [
    'role' => 'user',
    'content' => $message
];

// Crear cliente OpenRouter con el system prompt de la voz
try {
    $client = new OpenRouterClient(
        Env::get('OPENROUTER_API_KEY'),
        'google/gemini-3-flash-preview', // Forzado por código
        $systemPrompt
    );

    $reply = $client->generateWithMessages($messages);
} catch (\Exception $e) {
    Response::error('llm_error', 'Error generating response: ' . $e->getMessage(), 500);
}

// Parse structured reply (answer + sources + conflicts) with plain-text fallback.
$parsed = parseVoiceReply($reply);
$answer = $parsed['answer'];

// Build the trust metadata bundle shown in the UI.
$meta = [
    'source_match' => $useRag ? $voiceContext->computeSourceMatch($answer) : null,
    'sources' => $parsed['sources'],
    'conflicts' => $parsed['conflicts'],
    'conflict_summary' => $parsed['conflict_summary'],
];

// Guardar o actualizar ejecución
$repo = new VoiceExecutionsRepo();

// Generar título si es nueva conversación
$title = $message;
if (strlen($title) > 60) {
    $title = substr($title, 0, 57) . '...';
}

// Preparar historial completo para guardar
$fullHistory = $history;
$fullHistory[] = ['role' => 'user', 'content' => $message];
$fullHistory[] = ['role' => 'assistant', 'content' => $answer, 'meta' => $meta];

$inputData = [
    'history' => $fullHistory
];

// Registrar uso de voz (siempre, independientemente de si es nueva o existente)
$usageLog = new UsageLogRepo();
$usageLog->log((int)$user['id'], 'voice', 1, ['voice_id' => $voiceId]);

if ($executionId) {
    // Actualizar ejecución existente
    $repo->update($executionId, (int)$user['id'], [
        'input_data' => $inputData,
        'output_content' => $answer
    ]);
} else {
    // Crear nueva ejecución
    $executionId = $repo->create([
        'user_id' => (int)$user['id'],
        'voice_id' => $voiceId,
        'title' => $title,
        'input_data' => $inputData,
        'output_content' => $answer,
        'model' => $client->getModel()
    ]);
}

Response::json([
    'success' => true,
    'reply' => $answer,
    'meta' => $meta,
    'execution_id' => $executionId
]);
