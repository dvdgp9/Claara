<?php
/**
 * API: Generar imagen con Nanobanana (Gemini Vision)
 * POST /api/gestures/generate-image.php
 * 
 * Usa OpenRouter con modalities=['image', 'text'] para generación de imágenes
 */

ini_set('display_errors', 0);
error_reporting(E_ALL);
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'php_error', 'message' => "$errstr in $errfile:$errline"]]);
    exit;
});
set_exception_handler(function($e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => ['code' => 'exception', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]]);
    exit;
});

require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';

use App\Env;
use App\Session;
use App\Response;
use Chat\OpenRouterClient;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || $csrfHeader !== $csrfSession) {
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

if (!$gestureType || !$prompt) {
    Response::error('missing_params', 'Faltan parámetros requeridos', 400);
}

// Modelo solicitado por el usuario
$model = 'qwen-image-edit-plus-2025-12-15';

// Si es el modelo de Qwen, usamos su API directamente (Alibaba DashScope)
// La API de DashScope es compatible con el formato OpenAI en su mayoría
if (strpos($model, 'qwen-image') !== false) {
    $qwenApiKey = Env::get('QWEN_API_KEY');
    if (!$qwenApiKey) {
        Response::error('qwen_api_key_missing', 'Falta QWEN_API_KEY en .env', 500);
    }
    
    // Crear cliente OpenRouter pero configurado para Qwen (DashScope)
    // DashScope tiene un endpoint específico para compatibilidad con OpenAI
    $client = new OpenRouterClient(
        $qwenApiKey,
        $model,
        null, 
        null, 
        null
    );
    
    // Reflejar el cambio de base URL internamente para este cliente específico
    // DashScope OpenAI-compatible endpoint: https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions
    $reflection = new \ReflectionClass($client);
    $baseUrlProp = $reflection->getProperty('baseUrl');
    $baseUrlProp->setAccessible(true);
    $baseUrlProp->setValue($client, 'https://dashscope.aliyuncs.com/compatible-mode/v1/chat/completions');
} else {
    // Cliente estándar de OpenRouter
    $client = new OpenRouterClient(
        null,  // API key from env
        $model,
        null,
        null,
        null
    );
}

// Generar imagen con modalities
try {
    $text = $client->generateWithMessages(
        [['role' => 'user', 'content' => $prompt]],
        ['text', 'image']  // Modalities para generación de imagen
    );
    
    $images = $client->getLastImages();
    
} catch (\Exception $e) {
    Response::error('llm_error', 'Error al generar imagen: ' . $e->getMessage(), 500);
}

// Extraer imagen base64
$imageBase64 = null;
if ($images && is_array($images) && count($images) > 0) {
    $firstImage = $images[0];
    // El formato puede variar: puede ser directamente base64 o un objeto con url
    if (is_string($firstImage)) {
        // Ya es base64
        $imageBase64 = $firstImage;
    } elseif (is_array($firstImage)) {
        // Puede ser {type: 'image_url', image_url: {url: 'data:...'}}
        if (isset($firstImage['image_url']['url'])) {
            $dataUrl = $firstImage['image_url']['url'];
            // Extraer base64 de data:image/png;base64,...
            if (preg_match('/^data:image\/[^;]+;base64,(.+)$/', $dataUrl, $matches)) {
                $imageBase64 = $matches[1];
            }
        } elseif (isset($firstImage['url'])) {
            $dataUrl = $firstImage['url'];
            if (preg_match('/^data:image\/[^;]+;base64,(.+)$/', $dataUrl, $matches)) {
                $imageBase64 = $matches[1];
            }
        }
    }
}

if (!$imageBase64) {
    Response::error('no_image', 'El modelo no generó ninguna imagen. Intenta con otra descripción.', 400);
}

// Generar título
$title = generateImageTitle($inputData);

// Guardar en historial
$repo = new GestureExecutionsRepo();
$executionId = $repo->create([
    'user_id' => $user['id'],
    'gesture_type' => $gestureType,
    'title' => $title,
    'input_data' => $inputData,
    'output_content' => $text ?: 'Imagen generada',
    'output_data' => [
        'image' => $imageBase64,
        'text' => $text
    ],
    'content_type' => null,
    'business_line' => null,
    'model' => $client->getModel(),
]);

// Registrar uso
$usageLog = new UsageLogRepo();
$usageLog->log((int)$user['id'], 'gesture', 1, ['gesture_type' => $gestureType]);

Response::json([
    'success' => true,
    'execution_id' => $executionId,
    'image' => $imageBase64,
    'text' => $text,
    'title' => $title,
]);

/**
 * Genera un título para la imagen basado en la descripción
 */
function generateImageTitle(array $inputData): string
{
    $description = $inputData['description'] ?? '';
    
    if (!$description) {
        return 'Imagen generada';
    }
    
    // Tomar las primeras palabras (máx 50 caracteres)
    $title = mb_substr($description, 0, 50);
    if (mb_strlen($description) > 50) {
        $title .= '...';
    }
    
    return $title;
}
