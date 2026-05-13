<?php
/**
 * Nana quick answers endpoint
 * 
 * Quick answers chatbot using Qwen.
 * It does not persist to the database, but receives history for conversation continuity.
 * 
 * Uses ContextBuilder to load the quick-answer knowledge base.
 * The model is instructed not to invent information.
 */
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Chat\OpenRouterClient;
use Chat\ContextBuilder;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

// Requires session and CSRF.
$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string)($input['message'] ?? ''));
$history = $input['history'] ?? [];

if ($message === '') {
    Response::error('validation_error', 'Message is required', 400);
}

// Validate history (array of {role, content}).
if (!is_array($history)) {
    $history = [];
}

// Limit history to the last 20 messages to avoid exceeding context.
if (count($history) > 20) {
    $history = array_slice($history, -20);
}

// Load Nana-specific context from docs/context_faq/.
// Separate from the main chat to allow specific instructions.
$faqContextDir = dirname(dirname(__DIR__)) . '/docs/context_faq';
$contextBuilder = new ContextBuilder($faqContextDir);
$systemPrompt = $contextBuilder->buildSystemPrompt();

// Create an OpenRouter client with a fast model for quick answers.
$llmClient = new OpenRouterClient(
    null,                   // API key desde .env
    'qwen/qwen-plus',       // Modelo rápido vía OpenRouter
    $systemPrompt,
    0.1,
    600
);

// Build messages: history + current message.
$messages = [];
foreach ($history as $h) {
    if (isset($h['role']) && isset($h['content'])) {
        $messages[] = [
            'role' => $h['role'] === 'assistant' ? 'assistant' : 'user',
            'content' => (string)$h['content']
        ];
    }
}
// Add current message.
$messages[] = ['role' => 'user', 'content' => $message];

try {
    $reply = $llmClient->generateWithMessages($messages);
    
    Response::json([
        'reply' => $reply,
        'model' => $llmClient->getModel()
    ]);
} catch (\Exception $e) {
    Response::error('faq_error', 'Error processing question: ' . $e->getMessage(), 500);
}
