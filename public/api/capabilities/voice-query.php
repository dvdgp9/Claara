<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../../src/Repos/MessagesRepo.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Repos\ConversationsRepo;
use Repos\MessagesRepo;
use Repos\UsageLogRepo;
use Voices\VoiceContextBuilder;
use Voices\VoiceQueryService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$voiceSlug = trim((string)($input['voice_slug'] ?? ''));
$message = trim((string)($input['message'] ?? ''));
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;

if ($voiceSlug === '') {
    Response::error('validation_error', 'voice_slug requerido', 400);
}
if ($message === '') {
    Response::error('validation_error', 'message requerido', 400);
}
if ($conversationId <= 0) {
    Response::error('validation_error', 'conversation_id requerido', 400);
}

$convos = new ConversationsRepo();
$conversation = $convos->findByIdForUser($conversationId, (int)$user['id']);
if (!$conversation) {
    Response::error('not_found', 'Conversación no encontrada', 404);
}

$msgs = new MessagesRepo();
$history = [];
foreach ($msgs->listByConversation($conversationId) as $row) {
    $content = trim((string)($row['content'] ?? ''));
    if ($content === '') {
        continue;
    }
    $history[] = [
        'role' => (string)$row['role'],
        'content' => $content,
    ];
}

try {
    $result = (new VoiceQueryService())->query($user, $voiceSlug, $message, array_slice($history, -8));
} catch (\InvalidArgumentException $e) {
    Response::error('validation_error', $e->getMessage(), 400);
} catch (\RuntimeException $e) {
    $status = in_array($e->getCode(), [403, 404], true) ? $e->getCode() : 500;
    Response::error($status === 403 ? 'forbidden' : 'voice_query_error', $e->getMessage(), $status);
} catch (\Throwable $e) {
    Response::error('voice_query_error', 'Error al consultar la voz: ' . $e->getMessage(), 500);
}

$voice = $result['voice'] ?? [];
$voiceName = (string)($voice['name'] ?? $voiceSlug);
$answer = trim((string)$result['answer']);
$content = "### Answer from {$voiceName}\n\n";

$sourceMatch = $result['meta']['source_match'] ?? null;
if (is_array($sourceMatch) && isset($sourceMatch['percent'])) {
    $percent = max(0, min(100, (int)$sourceMatch['percent']));
    $band = trim((string)($sourceMatch['band'] ?? ''));
    $content .= "**Source match:** {$percent}%";
    if ($band !== '') {
        $content .= " ({$band})";
    }
    $content .= "\n\n";
}

$content .= $answer;

$sources = array_values(array_filter((array)($result['meta']['sources'] ?? [])));
if ($sources) {
    $content .= "\n\n**Sources consulted:**\n";
    foreach (array_slice($sources, 0, 8) as $source) {
        $source = trim((string)$source);
        $url = resolve_voice_source_url($voiceSlug, $source);
        if ($url) {
            $content .= "\n- [" . escape_markdown_link_text($source) . "]({$url})";
        } else {
            $content .= "\n- {$source}";
        }
    }
}

$messageId = $msgs->create(
    $conversationId,
    (int)$user['id'],
    'assistant',
    $content,
    (string)($result['model'] ?? 'google/gemini-3-flash-preview')
);
$convos->touch($conversationId);
(new UsageLogRepo())->log((int)$user['id'], 'voice', 1, ['voice_id' => $voiceSlug, 'source' => 'claara_chat']);

Response::json([
    'success' => true,
    'message' => [
        'id' => $messageId,
        'role' => 'assistant',
        'content' => $content,
    ],
    'voice' => [
        'slug' => $voiceSlug,
        'name' => $voiceName,
    ],
    'meta' => $result['meta'],
]);

function resolve_voice_source_url(string $voiceSlug, string $source): ?string
{
    try {
        $builder = new VoiceContextBuilder($voiceSlug);
        $sourceKey = normalize_source_name($source);
        foreach ($builder->listDocuments() as $doc) {
            $name = (string)($doc['name'] ?? '');
            $path = (string)($doc['path'] ?? '');
            $candidates = [
                $name,
                basename($path),
                pathinfo($name, PATHINFO_FILENAME),
                pathinfo($path, PATHINFO_FILENAME),
            ];

            foreach ($candidates as $candidate) {
                $candidateKey = normalize_source_name($candidate);
                if ($candidateKey !== '' && source_names_match($sourceKey, $candidateKey)) {
                    return '/api/voices/doc.php?voice_id=' . rawurlencode($voiceSlug)
                        . '&doc_id=' . rawurlencode((string)$doc['id'])
                        . '&download=1';
                }
            }
        }
    } catch (\Throwable $e) {
        return null;
    }

    return null;
}

function normalize_source_name(string $value): string
{
    $value = mb_strtolower(trim($value));
    $value = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $value) ?? $value;
    $value = preg_replace('/[^a-z0-9áéíóúüñ]+/iu', ' ', $value) ?? $value;
    return trim(preg_replace('/\s+/', ' ', $value) ?? $value);
}

function source_names_match(string $sourceKey, string $candidateKey): bool
{
    if ($sourceKey === $candidateKey) {
        return true;
    }

    if (mb_strlen($sourceKey) < 6 || mb_strlen($candidateKey) < 6) {
        return false;
    }

    return str_contains($sourceKey, $candidateKey) || str_contains($candidateKey, $sourceKey);
}

function escape_markdown_link_text(string $value): string
{
    return str_replace([']', '['], ['\]', '\['], $value);
}
