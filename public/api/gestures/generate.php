<?php
/**
 * API: Ejecutar gesto y guardar resultado
 * POST /api/gestures/generate.php
 */

// Error handling seguro para producción
ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("PHP Error [$errno]: $errstr in $errfile:$errline");
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'php_error', 'message' => 'Error interno del servidor']]);
    exit;
});
set_exception_handler(function($e) {
    error_log("Exception: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'exception', 'message' => 'Error interno del servidor']]);
    exit;
});

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../../src/Chat/LlmProvider.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterProvider.php';
require_once __DIR__ . '/../../../src/Chat/LlmProviderFactory.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';

use App\Session;
use App\Response;
use Chat\LlmProviderFactory;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

// Session ya se inicia en bootstrap
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

// Parsear body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    Response::error('invalid_body', 'Body JSON inválido', 400);
}

$gestureType = $body['gesture_type'] ?? '';
$prompt = $body['prompt'] ?? '';
$inputData = $body['input_data'] ?? [];
$contentType = $body['content_type'] ?? null;
$businessLine = $body['business_line'] ?? null;

if (!$gestureType || !$prompt) {
    Response::error('missing_params', 'Faltan parámetros requeridos', 400);
}

// Modelo específico por tipo de gesto
$modelByGesture = [
    'social-media' => 'google/gemini-3-flash-preview',
];
$model = $modelByGesture[$gestureType] ?? null;

// Crear cliente LLM (usa ContextBuilder y system prompt internamente)
$provider = LlmProviderFactory::create($model);

// Generar contenido
try {
    $response = $provider->generate([
        ['role' => 'user', 'content' => $prompt]
    ]);
} catch (\Exception $e) {
    Response::error('llm_error', 'Error al generar contenido: ' . $e->getMessage(), 500);
}

// Generar título automático (primeras palabras del tema/qué)
$title = generateTitle($inputData, $contentType);

// Guardar en historial
$repo = new GestureExecutionsRepo();
$executionId = $repo->create([
    'user_id' => $user['id'],
    'gesture_type' => $gestureType,
    'title' => $title,
    'input_data' => $inputData,
    'output_content' => $response,
    'content_type' => $contentType,
    'business_line' => $businessLine,
    'model' => $provider->getModel(),
]);

// Registrar uso de gesto
$usageLog = new UsageLogRepo();
$usageLog->log((int)$user['id'], 'gesture', 1, ['gesture_type' => $gestureType]);

Response::json([
    'success' => true,
    'execution_id' => $executionId,
    'content' => $response,
    'title' => $title,
]);

/**
 * Genera un título descriptivo basado en los datos de entrada
 */
function generateTitle(array $inputData, ?string $contentType): string
{
    // Intentar extraer el tema/asunto principal
    $topic = $inputData['topic'] ?? $inputData['what'] ?? $inputData['context'] ?? $inputData['theme'] ?? '';
    
    if ($topic) {
        // Truncar a 80 caracteres
        $title = mb_strlen($topic) > 80 ? mb_substr($topic, 0, 77) . '...' : $topic;
    } else {
        // Fallback con tipo de contenido
        $typeLabels = [
            'informativo' => 'Artículo informativo',
            'blog' => 'Post de blog',
            'nota-prensa' => 'Nota de prensa',
            'original' => 'Publicación',
            'variant' => 'Variante',
        ];
        $title = $typeLabels[$contentType] ?? 'Contenido generado';
        $title .= ' - ' . date('d/m H:i');
    }
    
    return $title;
}
