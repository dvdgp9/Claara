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
require_once __DIR__ . '/../../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../../src/Repos/UsageLogRepo.php';

use App\Session;
use App\Response;
use Chat\OpenRouterClient;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Invalid session', 401);
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

if (!$gestureType || !$prompt) {
    Response::error('missing_params', 'Faltan parámetros requeridos', 400);
}

// Modo de operación: 'generate' o 'edit'
$mode = $inputData['mode'] ?? 'generate';
$sourceImage = $inputData['source_image'] ?? null; // Base64 de imagen fuente (para edición)
$targetImage = $inputData['target_image'] ?? null; // Base64 de imagen objetivo (opcional, para edición)
$referenceImages = is_array($inputData['reference_images'] ?? null) ? $inputData['reference_images'] : [];
$referenceImages = array_values(array_filter(array_map(static function ($img) {
    return is_string($img) && $img !== '' ? $img : null;
}, $referenceImages)));
if (count($referenceImages) > 4) {
    $referenceImages = array_slice($referenceImages, 0, 4);
}
$model = 'google/gemini-3.1-flash-image-preview';

// Guardarraíles de calidad comunes (IMG-4)
$qualityGuardrails = " Always produce a high-resolution, sharp, professional result. Avoid: garbled or misspelled text, distorted or extra limbs/fingers, watermarks, signatures, low-resolution or blurry artifacts, oversaturation, and unwanted borders or frames. Keep composition clean and intentional.";

$systemInstruction = $mode === 'edit'
    ? "You are an expert image editor focused on instruction fidelity. Perform targeted, minimal, non-destructive edits. Keep all existing elements intact unless the user explicitly asks to modify them. If adding a logo to clothing that already has a logo, preserve the existing logo and place the new logo beside it, keeping realistic scale, perspective, fabric deformation, lighting, and readability." . $qualityGuardrails
    : "You are an expert image generator focused on photorealistic, high-quality outputs that strictly follow user instructions. If the user includes reference images, use them as visual guidance while preserving logo readability, proportion and clean layout hierarchy." . $qualityGuardrails;

if ($mode === 'edit' && !$sourceImage) {
    Response::error('missing_source_image', 'Se requiere una imagen fuente para el modo edición', 400);
}

// Aspect ratio + resolución como parámetros reales del modelo (IMG-2)
$allowedRatios = ['1:1','2:3','3:2','4:3','3:4','4:5','5:4','9:16','16:9','21:9','1:4','4:1','1:8','8:1'];
$aspectRatio = (string)($inputData['format'] ?? $inputData['aspect_ratio'] ?? '');
$aspectRatio = in_array($aspectRatio, $allowedRatios, true) ? $aspectRatio : null;

$allowedSizes = ['0.5K','1K','2K','4K'];
$imageSize = (string)($inputData['image_size'] ?? '1K');
if (!in_array($imageSize, $allowedSizes, true)) {
    $imageSize = '1K';
}

$imageConfig = ['image_size' => $imageSize];
if ($aspectRatio !== null) {
    $imageConfig['aspect_ratio'] = $aspectRatio;
}

// Mejora de prompt con LLM (IMG-3) — solo en generación, transparente para el usuario.
$enhancedPrompt = null;
if ($mode === 'generate') {
    $enhancedPrompt = enhanceImagePrompt($inputData, count($referenceImages));
    if (is_string($enhancedPrompt) && trim($enhancedPrompt) !== '') {
        $prompt = $enhancedPrompt;
    }
}

// Generar/Editar imagen
try {
    // Usar OpenRouter para Nanobanana (Gemini)
    $client = new OpenRouterClient(null, $model, $systemInstruction, null, null);
    
    // Si hay imágenes (modo edición), las pasamos
    $messages = [];
    if ($mode === 'edit') {
        $content = [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $sourceImage]]
        ];
        if ($targetImage) {
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $targetImage]];
        }
        $messages[] = ['role' => 'user', 'content' => $content];
    } else {
        if (!empty($referenceImages)) {
            $content = [
                ['type' => 'text', 'text' => $prompt]
            ];
            foreach ($referenceImages as $refImage) {
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,' . $refImage]];
            }
            $messages[] = ['role' => 'user', 'content' => $content];
        } else {
            $messages[] = ['role' => 'user', 'content' => $prompt];
        }
    }

    $text = $client->generateWithMessages($messages, ['image', 'text'], false, $imageConfig);
    $images = $client->getLastImages();
    $usedModel = $client->getModel();
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
$imageBinary = base64_decode($imageBase64, true);
$imageThumbnailBase64 = null;
if ($imageBinary !== false) {
    $imageThumbnailBase64 = createImageThumbnailBase64($imageBinary);
}

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
        'image_thumbnail' => $imageThumbnailBase64,
        'text' => $text,
        'final_prompt' => $prompt,
        'enhanced' => $enhancedPrompt !== null && trim((string)$enhancedPrompt) !== '',
        'image_config' => $imageConfig
    ],
    'content_type' => null,
    'business_line' => null,
    'model' => $usedModel ?? $model,
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
 * Mejora de prompt (IMG-3): reescribe la descripción del usuario + los controles
 * seleccionados en un prompt rico y coherente en inglés para el modelo de imagen.
 * Devuelve null si falla o no hay descripción (se usará el prompt original como fallback).
 */
function enhanceImagePrompt(array $inputData, int $referenceCount = 0): ?string
{
    $description = trim((string)($inputData['description'] ?? ''));
    if ($description === '') {
        return null;
    }

    // Atributos elegidos por el usuario (pueden venir vacíos).
    $attrs = [];
    foreach (['style' => 'Style', 'composition' => 'Composition', 'lighting' => 'Lighting', 'color' => 'Color palette'] as $key => $label) {
        $val = trim((string)($inputData[$key] ?? ''));
        if ($val !== '') {
            $attrs[] = "$label: $val";
        }
    }
    $attrsText = $attrs ? ("\nUser-selected attributes:\n- " . implode("\n- ", $attrs)) : '';
    $refsText = $referenceCount > 0
        ? "\nThe user attached $referenceCount reference image(s); assume they convey brand/style/elements to respect."
        : '';

    $system = "You are a senior prompt engineer for text-to-image models. "
        . "Rewrite the user's idea into ONE single, vivid, self-contained English image prompt. "
        . "Specify: main subject, setting/background, composition and framing, lighting, mood, color, materials/textures, and a clear visual style. "
        . "Be concrete and descriptive but concise (max ~80 words). "
        . "Do NOT include aspect ratio, resolution, camera megapixels, or meta commentary. "
        . "Do NOT add text/letters to the image unless the user explicitly asked for them. "
        . "Output only the final prompt, with no preamble, quotes or labels.";

    $userMsg = "User idea (may be in Spanish; translate the meaning to English): \"$description\"" . $attrsText . $refsText;

    try {
        $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', $system, 0.7, 400);
        $result = $client->generateWithMessages([
            ['role' => 'user', 'content' => $userMsg],
        ]);
        $result = trim((string)$result);
        // Saneado básico: quitar comillas envolventes o etiquetas accidentales.
        $result = trim($result, "\"' \n\r\t");
        return $result !== '' ? $result : null;
    } catch (\Throwable $e) {
        error_log('enhanceImagePrompt failed: ' . $e->getMessage());
        return null;
    }
}

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

function createImageThumbnailBase64(string $imageBinary, int $maxWidth = 240): ?string
{
    if (!function_exists('imagecreatefromstring') || !function_exists('imagescale')) {
        return null;
    }

    $source = @imagecreatefromstring($imageBinary);
    if (!$source) {
        return null;
    }

    $width = imagesx($source);
    $height = imagesy($source);
    if ($width <= 0 || $height <= 0) {
        imagedestroy($source);
        return null;
    }

    if ($width <= $maxWidth) {
        $thumb = $source;
    } else {
        $targetHeight = (int)round(($maxWidth / $width) * $height);
        $thumb = imagescale($source, $maxWidth, max(1, $targetHeight), IMG_BILINEAR_FIXED);
        imagedestroy($source);
        if (!$thumb) {
            return null;
        }
    }

    ob_start();
    imagepng($thumb, null, 6);
    $thumbBinary = ob_get_clean();
    imagedestroy($thumb);

    if (!is_string($thumbBinary) || $thumbBinary === '') {
        return null;
    }
    return base64_encode($thumbBinary);
}
