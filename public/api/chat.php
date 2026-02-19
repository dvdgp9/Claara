<?php
require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Chat/LlmProvider.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterProvider.php';
require_once __DIR__ . '/../../src/Chat/LlmProviderFactory.php';
require_once __DIR__ . '/../../src/Chat/ChatService.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../src/Repos/MessagesRepo.php';
require_once __DIR__ . '/../../src/Repos/ChatFilesRepo.php';
require_once __DIR__ . '/../../src/Repos/UsageLogRepo.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../src/Utils/SpreadsheetReader.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Chat\ChatService;
use Chat\LlmProviderFactory;
use Repos\ConversationsRepo;
use Repos\MessagesRepo;
use Repos\ChatFilesRepo;
use Repos\UsageLogRepo;
use Repos\UserFeatureAccessRepo;
use Utils\SpreadsheetReader;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

// Requiere sesión y CSRF
$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string)($input['message'] ?? ''));
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$file = $input['file'] ?? null;
$fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
$imageMode = !empty($input['image_mode']); // Modo generación de imágenes (nanobanana)
$webSearch = !empty($input['web_search']); // Modo búsqueda web

// Verificar permiso de generación de imágenes si está activado el modo imagen
if ($imageMode) {
    $accessRepo = new UserFeatureAccessRepo();
    if (!$accessRepo->hasImageGenerationAccess((int)$user['id'])) {
        Response::error('forbidden', 'No tienes acceso a la generación de imágenes', 403);
    }
}

// Opcional: permitir elegir modelo desde el cliente (formato: provider/model)
$modelName = isset($input['model']) && $input['model'] !== ''
    ? (string)$input['model']
    : 'google/gemini-3-flash-preview';

// Si es modo imagen, forzar modelo de generación de imágenes
if ($imageMode) {
    $modelName = 'google/gemini-3-pro-image-preview';
}

// Validar que haya mensaje o archivo
if ($message === '' && !$file && !$fileId) {
    Response::error('validation_error', 'Se requiere un mensaje o archivo', 400);
}

$filesRepo = new ChatFilesRepo();

// Si hay file_id, cargar archivo desde la base de datos
if ($fileId && !$file) {
    $storedFile = $filesRepo->findByIdAndUser($fileId, (int)$user['id']);
    if ($storedFile) {
        $storagePath = ChatFilesRepo::getStoragePath();
        $filePath = $storagePath . '/' . $storedFile['stored_name'];
        if (file_exists($filePath)) {
            $fileData = base64_encode(file_get_contents($filePath));
            $file = [
                'mime_type' => $storedFile['mime_type'],
                'data' => $fileData,
                'name' => $storedFile['original_name']
            ];
        }
    }
}

// Validar archivo si existe (inline o cargado)
if ($file) {
    if (!isset($file['mime_type']) || !isset($file['data'])) {
        Response::error('validation_error', 'Datos de archivo inválidos', 400);
    }
    
    // Validar tipo MIME
    $allowedTypes = ['application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp', 'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    if (!in_array($file['mime_type'], $allowedTypes)) {
        Response::error('validation_error', 'Tipo de archivo no soportado', 400);
    }
    // Si es imagen y el cliente no ha especificado modelo, forzar uno multimodal (Gemini 3 Flash Preview)
    if (
        (!isset($input['model']) || $input['model'] === '')
        && str_starts_with((string)$file['mime_type'], 'image/')
    ) {
        $modelName = 'google/gemini-3-flash-preview';
    }
}

$convos = new ConversationsRepo();
$msgs = new MessagesRepo();
$usageLog = new UsageLogRepo();

// Limpieza de imágenes antiguas (5 días)
$msgs->purgeImagesOlderThan(5);

// Si no hay conversación, crear una nueva
$isNewConversation = $conversationId <= 0;
if ($isNewConversation) {
    $conversationId = $convos->create((int)$user['id'], null);
    // Registrar creación de conversación
    $usageLog->log((int)$user['id'], 'conversation');
}

// Guardar mensaje de usuario (con file_id si existe)
$userMsgId = $msgs->create($conversationId, (int)$user['id'], 'user', $message, null, null, null, $fileId);

// Registrar mensaje de usuario con modelo para estadísticas
$usageLog->log((int)$user['id'], 'message', 1, ['model' => $modelName]);

// Actualizar archivo con conversation_id y message_id si es nuevo
if ($fileId) {
    $filesRepo->updateConversationId($fileId, $conversationId);
    $filesRepo->updateMessageId($fileId, $userMsgId);
}

// Auto-titular si el título sigue siendo el genérico
$convos->autoTitle($conversationId, $message);

// Para generación de imágenes (nanobanana), no enviar contexto corporativo
$withContext = !$imageMode;
$provider = LlmProviderFactory::create($modelName, $withContext);
$svc = new ChatService($provider);
// Construir historial: incluir todos los mensajes de la conversación (ya incluye el del usuario)
$allMessages = $msgs->listByConversation($conversationId);
$history = [];
foreach ($allMessages as $m) {
    $historyItem = [ 'role' => $m['role'], 'content' => $m['content'] ];
    $history[] = $historyItem;
}

// Si hay archivo, procesarlo según tipo
if ($file && count($history) > 0) {
    $lastIdx = count($history) - 1;
    if ($history[$lastIdx]['role'] === 'user') {
        $fileMime = $file['mime_type'] ?? '';
        
        // Para CSV/Excel: convertir a texto y añadir al mensaje
        if (SpreadsheetReader::isSpreadsheet($fileMime)) {
            $binaryData = base64_decode($file['data']);
            $fileName = $file['name'] ?? 'archivo';
            $spreadsheetText = SpreadsheetReader::readToText($binaryData, $fileMime, $fileName);
            
            // Añadir el contenido tabular al mensaje del usuario con instrucciones de precisión
            $originalContent = $history[$lastIdx]['content'];
            $promptPrefix = "IMPORTANTE: Los siguientes datos provienen de un archivo Excel/CSV. \n" .
                            "1. Úsalos como ÚNICA fuente de verdad para preguntas sobre el archivo.\n" .
                            "2. Si un dato no está en la tabla, di claramente que no dispones de él.\n" .
                            "3. Mantén la precisión numérica absoluta.\n\n";
            
            if ($originalContent !== '') {
                $history[$lastIdx]['content'] = $promptPrefix . $originalContent . "\n\n" . $spreadsheetText;
            } else {
                $history[$lastIdx]['content'] = $promptPrefix . "Analiza el contenido de este archivo:\n\n" . $spreadsheetText;
            }
        } else {
            // Para PDF e imágenes: enviar como archivo adjunto
            $history[$lastIdx]['file'] = $file;
        }
    }
}

// En modo imagen, condensar contexto para reducir respuestas textuales,
// pero conservando intención cuando el último mensaje es genérico.
if ($imageMode && !empty($history)) {
    $history = buildImageModeHistory($history);
}

// Limitar contexto para no exceder límites de Gemini (250k tokens → ~1M chars)
// Dejamos margen: ~150k chars de historial (~37.5k tokens estimados)
$contextTruncated = false;
if (count($history) > 20) {
    $totalChars = array_sum(array_map(fn($m) => mb_strlen($m['content']), $history));
    $maxContextChars = 50000;
    
    if ($totalChars > $maxContextChars) {
        $contextTruncated = true;
        // Mantener mensajes recientes hasta alcanzar límite
        $truncated = [];
        $chars = 0;
        for ($i = count($history) - 1; $i >= 0; $i--) {
            $len = mb_strlen($history[$i]['content']);
            if ($chars + $len > $maxContextChars && count($truncated) >= 20) {
                break;
            }
            array_unshift($truncated, $history[$i]);
            $chars += $len;
        }
        $history = $truncated;
    }
}

// Determinar modalities para la generación
$modalities = $imageMode ? ['image', 'text'] : null;

// Web search no es compatible con modo imagen
$useWebSearch = $webSearch && !$imageMode;

if ($imageMode) {
    $assistantMsg = null;
    $generatedImages = null;
    $attemptHistory = $history;

    for ($attempt = 1; $attempt <= 2; $attempt++) {
        $assistantMsg = $svc->replyWithHistory($attemptHistory, $modalities, false);
        $generatedImages = $svc->getLastImages();

        if ($generatedImages && !empty($generatedImages)) {
            break;
        }

        if ($attempt === 1) {
            $attemptHistory = enforceImageOutputHistory($history);
        }
    }

    if (!$generatedImages || empty($generatedImages)) {
        Response::error(
            'image_generation_failed',
            'No se pudo generar una imagen con ese prompt. Prueba acortándolo o dividiéndolo en pasos.',
            422
        );
    }
} else {
    $assistantMsg = $svc->replyWithHistory($history, $modalities, $useWebSearch);
}

// Determinar el modelo usado
$usedModel = $provider->getModel();

// Obtener imágenes generadas si las hay
$generatedImages = $generatedImages ?? $svc->getLastImages();

// Obtener anotaciones/citas web si las hay
$webAnnotations = $svc->getLastAnnotations();

// Guardar respuesta de asistente (con imágenes persistidas si las hay)
$imagesToSave = null;
$savedFileIds = [];
if ($generatedImages && !empty($generatedImages)) {
    // Deduplicar por URL (normalizada)
    $seen = [];
    $unique = [];
    foreach ($generatedImages as $img) {
        $url = $img['image_url']['url'] ?? ($img['imageUrl']['url'] ?? null);
        if (!$url) continue;
        
        // Normalizar URL para evitar duplicados por query strings o variaciones menores
        $normalizedUrl = $url;
        if (strpos($url, 'data:') !== 0) {
            $parsed = parse_url($url);
            $normalizedUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');
        }
        
        if (isset($seen[$normalizedUrl])) continue;
        $seen[$normalizedUrl] = true;
        $unique[] = $url;
    }

    // Normalizar y persistir en chat_files con expiración (5 días) y devolver nuestros URLs
    $imagesNormalized = [];
    $storagePath = ChatFilesRepo::getStoragePath();
    if (!is_dir($storagePath)) { @mkdir($storagePath, 0755, true); }
    $seenHashes = []; // Deduplicar por contenido binario real
    foreach ($unique as $idx => $url) {
        $binary = null; $mime = null; $ext = null; $origName = 'nanobanana-'.date('Ymd-His')."-$idx";
        if (strpos($url, 'data:') === 0) {
            // data URL: data:mime;base64,XXXX
            if (preg_match('#^data:(.*?);base64,(.*)$#', $url, $m)) {
                $mime = strtolower(trim($m[1]));
                $binary = base64_decode($m[2]);
            }
        } else if (preg_match('#^https?://#i', $url)) {
            // Descargar recurso
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (compatible; EbonIA/1.0)\r\n",
                    'timeout' => 30
                ],
                'ssl' => [ 'verify_peer' => false, 'verify_peer_name' => false ]
            ]);
            $binary = @file_get_contents($url, false, $ctx);
            if ($binary !== false) {
                $imgInfo = @getimagesizefromstring($binary);
                if ($imgInfo && isset($imgInfo['mime'])) { $mime = strtolower($imgInfo['mime']); }
            }
        }

        if (!$binary) { continue; }
        
        // Deduplicar por hash del contenido real (imprescindible si OpenRouter devuelve URLs distintas para misma imagen)
        $hash = hash('sha256', $binary);
        if (isset($seenHashes[$hash])) { continue; }
        $seenHashes[$hash] = true;
        
        // Validar mime y extensión
        $map = [ 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp' ];
        $ext = $map[$mime] ?? null;
        if (!$ext) { continue; }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $filePath = $storagePath . '/' . $storedName;
        if (@file_put_contents($filePath, $binary) === false) { continue; }

        // Registrar en DB (sin message_id todavía)
        try {
            $size = strlen($binary);
            $fileId = $filesRepo->create([
                'user_id' => (int)$user['id'],
                'conversation_id' => $conversationId,
                'original_name' => $origName . '.' . $ext,
                'stored_name' => $storedName,
                'mime_type' => $mime,
                'size_bytes' => $size
            ]);
            $savedFileIds[] = $fileId;
            $imagesNormalized[] = [ 'image_url' => [ 'url' => '/api/files/serve.php?id=' . $fileId ] ];
        } catch (\Exception $e) {
            @unlink($filePath);
        }
    }

    if (!empty($imagesNormalized)) {
        $imagesToSave = $imagesNormalized;
    }
}

$assistantMsgId = $msgs->create($conversationId, null, 'assistant', $assistantMsg['content'], $usedModel ?: null, null, null, null, $imagesToSave);

// Registrar imágenes generadas (si las hay)
if ($imagesToSave && count($imagesToSave) > 0) {
    $usageLog->log((int)$user['id'], 'image', count($imagesToSave));
}

// Vincular archivos persistidos al mensaje del asistente
if (!empty($savedFileIds)) {
    foreach ($savedFileIds as $fid) {
        $filesRepo->updateMessageId($fid, $assistantMsgId);
    }
}

// Actualizar updated_at de la conversación
$convos->touch($conversationId);

$response = [
    'conversation' => [ 'id' => $conversationId ],
    'message' => [
        'id' => $assistantMsgId,
        'role' => $assistantMsg['role'],
        'content' => $assistantMsg['content'],
        'model' => $usedModel ?: null
    ],
    'context_truncated' => $contextTruncated
];

// Incluir imágenes generadas si las hay (ya deduplicadas)
if ($imagesToSave) {
    $response['message']['images'] = $imagesToSave;
}

// Incluir anotaciones/citas web si las hay
if ($webAnnotations && !empty($webAnnotations)) {
    $response['message']['annotations'] = $webAnnotations;
}

Response::json($response);

function enforceImageOutputHistory(array $history): array
{
    $enforced = $history;
    $instruction = "\n\nIMPORTANT: Return at least one generated image in this response. Do not return text-only output. If the prompt is long, prioritize the key visual requirements and still generate the image.";

    for ($i = count($enforced) - 1; $i >= 0; $i--) {
        if (($enforced[$i]['role'] ?? '') !== 'user') {
            continue;
        }

        $content = $enforced[$i]['content'] ?? '';
        if (is_array($content)) {
            $content[] = ['type' => 'text', 'text' => trim($instruction)];
            $enforced[$i]['content'] = $content;
        } else {
            $enforced[$i]['content'] = (string)$content . $instruction;
        }
        break;
    }

    return $enforced;
}

function buildImageModeHistory(array $history): array
{
    $lastUserIdx = -1;
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') === 'user') {
            $lastUserIdx = $i;
            break;
        }
    }

    if ($lastUserIdx === -1) {
        return [end($history)];
    }

    $lastUser = $history[$lastUserIdx];
    $lastText = extractTextFromContent($lastUser['content'] ?? '');

    if (!isGenericImageFollowup($lastText)) {
        return [$lastUser];
    }

    $prevUserText = '';
    for ($i = $lastUserIdx - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }
        $candidate = trim(extractTextFromContent($history[$i]['content'] ?? ''));
        if (mb_strlen($candidate) >= 20) {
            $prevUserText = $candidate;
            break;
        }
    }

    if ($prevUserText === '') {
        return [$lastUser];
    }

    $combined = "Contexto previo de la imagen:\n{$prevUserText}\n\nInstrucción actual:\n{$lastText}";
    $result = $lastUser;

    if (is_array($result['content'] ?? null)) {
        $result['content'][] = ['type' => 'text', 'text' => $combined];
    } else {
        $result['content'] = $combined;
    }

    return [$result];
}

function extractTextFromContent($content): string
{
    if (is_string($content)) {
        return trim($content);
    }
    if (!is_array($content)) {
        return '';
    }

    $parts = [];
    foreach ($content as $item) {
        if (is_array($item) && ($item['type'] ?? '') === 'text' && isset($item['text'])) {
            $parts[] = (string)$item['text'];
        }
    }
    return trim(implode("\n", $parts));
}

function isGenericImageFollowup(string $text): bool
{
    $normalized = mb_strtolower(trim($text));
    if ($normalized === '') return true;

    if (mb_strlen($normalized) <= 45 && preg_match('/^(genera( la)? imagen|hazlo|dale|adelante|ok|vale|perfecto|sí|si)$/u', $normalized)) {
        return true;
    }

    return (bool)preg_match('/^(genera( la)? imagen|hazlo|dale|adelante)\b/u', $normalized);
}
