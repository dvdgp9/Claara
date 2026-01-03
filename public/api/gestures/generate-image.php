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

// Modo de operación: 'generate' o 'edit'
$mode = $inputData['mode'] ?? 'generate';
$sourceImage = $inputData['source_image'] ?? null; // Base64 de imagen fuente (para edición)
$targetImage = $inputData['target_image'] ?? null; // Base64 de imagen objetivo (opcional, para edición)

// Seleccionar modelo según el modo
$model = ($mode === 'edit') ? 'qwen-image-edit-plus-2025-12-15' : 'qwen-image-max';

// Generar/Editar imagen
try {
    $qwenApiKey = Env::get('QWEN_API_KEY');
    if (!$qwenApiKey) {
        Response::error('qwen_api_key_missing', 'Falta QWEN_API_KEY en .env', 500);
    }
    
    // Endpoint para Qwen Image (multimodal-generation)
    $url = 'https://dashscope.aliyuncs.com/api/v1/services/aigc/multimodal-generation/generation';
    
    // Construir contenido del mensaje según el modo
    $content = [];
    
    if ($mode === 'edit') {
        // Modo edición: requiere al menos imagen fuente
        if (!$sourceImage) {
            Response::error('missing_source_image', 'Se requiere una imagen fuente para el modo edición', 400);
        }
        
        // Añadir imagen fuente
        $content[] = ['image' => 'data:image/png;base64,' . $sourceImage];
        
        // Añadir imagen objetivo si existe
        if ($targetImage) {
            $content[] = ['image' => 'data:image/png;base64,' . $targetImage];
        }
    }
    
    // Añadir el texto/prompt
    $content[] = ['text' => $prompt];
    
    // Formato de payload para Qwen Image
    $payload = [
        'model' => $model,
        'input' => [
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $content
                ]
            ]
        ],
        'parameters' => [
            'n' => 1,
            'negative_prompt' => 'low quality, blurry, distorted, deformed',
            'prompt_extend' => true,
            'watermark' => false
        ]
    ];
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $qwenApiKey,
            'Content-Type: application/json',
            'X-DashScope-Async: disable'
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        CURLOPT_TIMEOUT => 180,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);
    
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($raw === false || $err) {
        Response::error('qwen_request_failed', 'Fallo al contactar con DashScope: ' . $err, 502);
    }
    
    $data = json_decode($raw, true);
    if ($status < 200 || $status >= 300) {
        $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP '.$status);
        Response::error('qwen_bad_response', 'Error de DashScope: ' . $msg, 502);
    }
    
    // Extraer resultado de Qwen Image
    $output = $data['output'] ?? [];
    $results = $output['choices'][0]['message']['content'] ?? [];
    $text = '';
    $images = [];
    
    foreach ($results as $item) {
        if (isset($item['text'])) {
            $text = $item['text'];
        }
        if (isset($item['image'])) {
            // La imagen viene como URL, necesitamos descargarla y convertir a base64
            $imageUrl = $item['image'];
            $imageData = @file_get_contents($imageUrl);
            if ($imageData !== false) {
                $images[] = base64_encode($imageData);
            }
        }
    }
    
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
