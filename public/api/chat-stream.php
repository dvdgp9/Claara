<?php
/**
 * Chat Streaming Endpoint (SSE)
 * Transmite respuestas de IA en tiempo real para mejor UX
 */

require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Chat/LlmProvider.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterProvider.php';
require_once __DIR__ . '/../../src/Chat/LlmProviderFactory.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Audio/ContentExtractor.php';
require_once __DIR__ . '/../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../src/Repos/ConversationAccessRepo.php';
require_once __DIR__ . '/../../src/Repos/MessagesRepo.php';
require_once __DIR__ . '/../../src/Repos/ChatFilesRepo.php';
require_once __DIR__ . '/../../src/Repos/UsageLogRepo.php';
require_once __DIR__ . '/../../src/Repos/UserFeatureAccessRepo.php';
require_once __DIR__ . '/../../src/Utils/SpreadsheetReader.php';

use App\Env;
use App\Session;
use Audio\ContentExtractor;
use Auth\AuthService;
use Chat\OpenRouterClient;
use Chat\ContextBuilder;
use Repos\ConversationsRepo;
use Repos\ConversationAccessRepo;
use Repos\MessagesRepo;
use Repos\ChatFilesRepo;
use Repos\UsageLogRepo;
use Repos\UserFeatureAccessRepo;
use Utils\SpreadsheetReader;

// Desactivar output buffering para streaming
while (ob_get_level()) ob_end_clean();

// Cabeceras para SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no'); // Desactivar buffering de nginx

/**
 * Enviar evento SSE
 */
function sendEvent(string $type, array $data): void {
    $data['type'] = $type;
    echo "data: " . json_encode($data, JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

/**
 * Enviar error y terminar
 */
function sendError(string $message, int $code = 500): void {
    sendEvent('error', ['message' => $message, 'code' => $code]);
    echo "data: [DONE]\n\n";
    flush();
    exit;
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('POST only', 405);
}

// Requiere auth y CSRF
try {
    $user = AuthService::requireAuth();
    Session::requireCsrf();
} catch (\Exception $e) {
    sendError($e->getMessage(), 401);
}

// Parsear input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$message = trim((string)($input['message'] ?? ''));
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$file = $input['file'] ?? null;
$fileId = isset($input['file_id']) ? (int)$input['file_id'] : null;
$imageMode = !empty($input['image_mode']);
$webSearch = !empty($input['web_search']);

// Verificar permiso de generación de imágenes
if ($imageMode) {
    $accessRepo = new UserFeatureAccessRepo();
    if (!$accessRepo->hasImageGenerationAccess((int)$user['id'])) {
        sendError('No tienes acceso a la generación de imágenes', 403);
    }
}

// Selección de modelo
$modelName = isset($input['model']) && $input['model'] !== ''
    ? (string)$input['model']
    : 'google/gemini-3-flash-preview';

if ($imageMode) {
    $modelName = 'google/gemini-3.1-flash-image-preview';
}

// Validar input
if ($message === '' && !$file && !$fileId) {
    sendError('Se requiere un mensaje o archivo', 400);
}

$filesRepo = new ChatFilesRepo();

// Cargar archivo desde BD si se proporciona file_id
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

// Validar archivo
if ($file) {
    if (!isset($file['mime_type']) || !isset($file['data'])) {
        sendError('Datos de archivo inválidos', 400);
    }
    
    $allowedTypes = [
        'application/pdf', 'image/png', 'image/jpeg', 'image/gif', 'image/webp',
        'text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    ];
    if (!in_array($file['mime_type'], $allowedTypes)) {
        sendError('Tipo de archivo no soportado', 400);
    }
    
    // Convertir hojas de cálculo a texto
    if (SpreadsheetReader::isSpreadsheet($file['mime_type'])) {
        $binaryData = base64_decode($file['data']);
        $fileName = $file['name'] ?? 'archivo';
        $spreadsheetText = SpreadsheetReader::readToText($binaryData, $file['mime_type'], $fileName);
        
        $promptPrefix = "IMPORTANTE: Los siguientes datos provienen de un archivo Excel/CSV. \n" .
                        "1. Úsalos como ÚNICA fuente de verdad para preguntas sobre el archivo.\n" .
                        "2. Si un dato no está en la tabla, di claramente que no dispones de él.\n" .
                        "3. Mantén la precisión numérica absoluta.\n\n";
        
        if ($message !== '') {
            $message = $promptPrefix . $message . "\n\n" . $spreadsheetText;
        } else {
            $message = $promptPrefix . "Analiza el contenido de este archivo:\n\n" . $spreadsheetText;
        }
        $file = null;
    }
    
    // Forzar modelo multimodal para imágenes
    if ($file && (!isset($input['model']) || $input['model'] === '') && str_starts_with((string)$file['mime_type'], 'image/')) {
        $modelName = 'google/gemini-3-flash-preview';
    }
}

$convos = new ConversationsRepo();
$conversationAccess = new ConversationAccessRepo();
$msgs = new MessagesRepo();
$usageLog = new UsageLogRepo();

// Limpiar imágenes antiguas
$msgs->purgeImagesOlderThan(5);

// Crear conversación si es necesario
$isNewConversation = $conversationId <= 0;
if ($isNewConversation) {
    $conversationId = $convos->create((int)$user['id'], null);
    $usageLog->log((int)$user['id'], 'conversation');
    // Notificar al cliente de la nueva conversación
    sendEvent('conversation', ['id' => $conversationId]);
} elseif (!$conversationAccess->canChat($conversationId, $user)) {
    sendError('You do not have permission to chat in this conversation', 403);
}

// Guardar mensaje de usuario
$userMsgId = $msgs->create($conversationId, (int)$user['id'], 'user', $message, null, null, null, $fileId);
$usageLog->log((int)$user['id'], 'message', 1, ['model' => $modelName]);

// Actualizar referencias de archivo
if ($fileId) {
    $filesRepo->updateConversationId($fileId, $conversationId);
    $filesRepo->updateMessageId($fileId, $userMsgId);
}

// Auto-título
$convos->autoTitle($conversationId, $message);

// Construir contexto
$withContext = !$imageMode;
$contextBuilder = $withContext ? new ContextBuilder(null, $user, true) : null;
$systemPrompt = $contextBuilder ? $contextBuilder->buildSystemPrompt() : null;
if ($imageMode) {
    $systemPrompt = 'You are an image generation assistant. Always return at least one generated image for image_mode requests. Text-only responses are not allowed unless image generation is impossible.';
}

// Construir historial
$allMessages = $msgs->listByConversation($conversationId);
$history = [];
foreach ($allMessages as $m) {
    $historyItem = ['role' => $m['role'], 'content' => $m['content']];
    
    // Si el mensaje tiene un archivo asociado, cargarlo
    if (!empty($m['file_id'])) {
        $storedFile = $filesRepo->findByIdAndUser((int)$m['file_id'], (int)$user['id']);
        if ($storedFile) {
            $storagePath = ChatFilesRepo::getStoragePath();
            $filePath = $storagePath . '/' . $storedFile['stored_name'];
            if (file_exists($filePath)) {
                $historyItem['file'] = [
                    'mime_type' => $storedFile['mime_type'],
                    'data' => base64_encode(file_get_contents($filePath)),
                    'name' => $storedFile['original_name']
                ];
            }
        }
    }
    
    $history[] = $historyItem;
}

// Añadir el archivo actual si existe y no está ya en el historial (para mensajes nuevos)
if ($file && count($history) > 0) {
    $lastIdx = count($history) - 1;
    if ($history[$lastIdx]['role'] === 'user' && !isset($history[$lastIdx]['file'])) {
        $history[$lastIdx]['file'] = $file;
    }
}

// Evitar parser remoto de OpenRouter para PDFs problemáticos: convertir PDFs a texto localmente
$history = preprocessPdfHistoryForStreaming($history);

// En modo imagen, condensar contexto para evitar respuestas textuales,
// pero conservando intención si el último mensaje es genérico.
if ($imageMode && !empty($history)) {
    $history = buildImageModeHistory($history);
}

// Limitar contexto
$contextTruncated = false;
if (count($history) > 20) {
    $totalChars = array_sum(array_map(fn($m) => mb_strlen($m['content']), $history));
    $maxContextChars = 50000;
    
    if ($totalChars > $maxContextChars) {
        $contextTruncated = true;
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

// Para generación de imágenes, usar modo no-streaming (las imágenes no se pueden streamear)
if ($imageMode) {
    try {
        $client = new OpenRouterClient(null, $modelName, $systemPrompt);
        $attemptHistory = $history;
        $response = '';
        $usedModel = $modelName;
        $imagesToSave = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $response = $client->generateWithMessages($attemptHistory, ['image', 'text'], false);
            $usedModel = $client->getModel();
            $generatedImages = $client->getLastImages();

            if ($generatedImages && !empty($generatedImages)) {
                $imagesToSave = processGeneratedImages($generatedImages, $conversationId, $user, $filesRepo);
                if ($imagesToSave && !empty($imagesToSave)) {
                    break;
                }
            }

            if ($attempt === 1) {
                $attemptHistory = enforceImageOutputHistory($history);
            }
        }

        if (!$imagesToSave || empty($imagesToSave)) {
            sendError('No se pudo generar una imagen con ese prompt. Prueba acortándolo o dividiéndolo en pasos.', 422);
        }
        
        $assistantMsgId = $msgs->create($conversationId, null, 'assistant', $response, $usedModel, null, null, null, $imagesToSave);
        $convos->touch($conversationId);
        
        // Enviar respuesta completa
        sendEvent('chunk', ['content' => $response]);
        
        // Incluir imágenes en el evento meta para que el frontend las reciba al finalizar
        $metaData = ['message_id' => $assistantMsgId, 'model' => $usedModel];
        if ($imagesToSave) {
            $metaData['images'] = $imagesToSave;
            $usageLog->log((int)$user['id'], 'image', count($imagesToSave));
        }
        sendEvent('meta', $metaData);
        
        echo "data: [DONE]\n\n";
        flush();
        exit;
    } catch (\Exception $e) {
        sendError($e->getMessage());
    }
}

// Respuesta en streaming
try {
    // Añadir plugin web si está activo
    $plugins = [];
    if ($webSearch) {
        $plugins[] = ['id' => 'web'];
    }
    
    $client = new OpenRouterClient(null, $modelName, $systemPrompt);
    $fullText = '';
    $usedModel = $modelName;
    
    // Pasar parámetro webSearch a la función de streaming
    $client->generateWithMessagesStreaming(
        $history,
        function($chunk) use (&$fullText) {
            $fullText .= $chunk;
            sendEvent('chunk', ['content' => $chunk]);
        },
        function($text, $model) use (&$usedModel) {
            $usedModel = $model;
        },
        $webSearch
    );
    
    // Obtener anotaciones si las hay (búsqueda web)
    $annotations = $client->getLastAnnotations();
    
    // Guardar mensaje del asistente
    $assistantMsgId = $msgs->create($conversationId, null, 'assistant', $fullText, $usedModel, null, null, null, null);
    $convos->touch($conversationId);
    
    // Enviar metadatos
    sendEvent('meta', [
        'message_id' => $assistantMsgId, 
        'model' => $usedModel,
        'context_truncated' => $contextTruncated
    ]);
    
    if ($annotations && !empty($annotations)) {
        sendEvent('annotations', ['annotations' => $annotations]);
    }
    
    echo "data: [DONE]\n\n";
    flush();
    } catch (\Exception $e) {
        // Cascada de fallbacks cuando OpenRouter falla parseando un PDF.
        $historyHasPdf = pdfHistoryContainsAttachment($history);
        if ($historyHasPdf && shouldFallbackPdfToLocalText($e)) {

            // 1) Intentar extracción local con pdftotext (si está disponible
            //    en el servidor; en producción suele NO estarlo).
            $localPdfHistory = replacePdfAttachmentsWithLocalText($history);
            if ($localPdfHistory !== null) {
                try {
                    $client = new OpenRouterClient(null, $modelName, $systemPrompt);
                    $fullText = '';
                    $usedModel = $modelName;

                    $client->generateWithMessagesStreaming(
                        $localPdfHistory,
                        function($chunk) use (&$fullText) {
                            $fullText .= $chunk;
                            sendEvent('chunk', ['content' => $chunk]);
                        },
                        function($text, $model) use (&$usedModel) {
                            $usedModel = $model;
                        },
                        $webSearch
                    );

                    $annotations = $client->getLastAnnotations();
                    $assistantMsgId = $msgs->create($conversationId, null, 'assistant', $fullText, $usedModel, null, null, null, null);
                    $convos->touch($conversationId);

                    sendEvent('meta', [
                        'message_id' => $assistantMsgId,
                        'model' => $usedModel,
                        'context_truncated' => $contextTruncated
                    ]);

                    if ($annotations && !empty($annotations)) {
                        sendEvent('annotations', ['annotations' => $annotations]);
                    }

                    echo "data: [DONE]\n\n";
                    flush();
                    exit;
                } catch (\Exception $ignored) {
                    // Cae al siguiente fallback.
                }
            }

            // 2) Reintentar en OpenRouter forzando el engine mistral-ocr,
            //    que es el más robusto para PDFs con contenido mixto
            //    (texto + imágenes, escaneados, encoding no estándar).
            try {
                $client = new OpenRouterClient(null, $modelName, $systemPrompt);
                $client->setPdfEngine('mistral-ocr');
                $fullText = '';
                $usedModel = $modelName;

                $client->generateWithMessagesStreaming(
                    $history,
                    function($chunk) use (&$fullText) {
                        $fullText .= $chunk;
                        sendEvent('chunk', ['content' => $chunk]);
                    },
                    function($text, $model) use (&$usedModel) {
                        $usedModel = $model;
                    },
                    $webSearch
                );

                $annotations = $client->getLastAnnotations();
                $assistantMsgId = $msgs->create($conversationId, null, 'assistant', $fullText, $usedModel, null, null, null, null);
                $convos->touch($conversationId);

                sendEvent('meta', [
                    'message_id' => $assistantMsgId,
                    'model' => $usedModel,
                    'context_truncated' => $contextTruncated
                ]);

                if ($annotations && !empty($annotations)) {
                    sendEvent('annotations', ['annotations' => $annotations]);
                }

                echo "data: [DONE]\n\n";
                flush();
                exit;
            } catch (\Exception $fallbackException) {
                sendError('No se ha podido procesar el PDF. ' . $fallbackException->getMessage());
            }
        }

        sendError($e->getMessage());
    }

/**
 * Procesar imágenes generadas (extraído de chat.php)
 */
function processGeneratedImages(array $generatedImages, int $conversationId, array $user, ChatFilesRepo $filesRepo): ?array {
    $seen = [];
    $unique = [];
    foreach ($generatedImages as $img) {
        $url = $img['image_url']['url'] ?? ($img['imageUrl']['url'] ?? null);
        if (!$url) continue;
        
        $normalizedUrl = $url;
        if (strpos($url, 'data:') !== 0) {
            $parsed = parse_url($url);
            $normalizedUrl = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . ($parsed['path'] ?? '');
        }
        
        if (isset($seen[$normalizedUrl])) continue;
        $seen[$normalizedUrl] = true;
        $unique[] = $url;
    }

    $imagesNormalized = [];
    $storagePath = ChatFilesRepo::getStoragePath();
    if (!is_dir($storagePath)) { @mkdir($storagePath, 0755, true); }
    $seenHashes = [];
    
    foreach ($unique as $idx => $url) {
        $binary = null; $mime = null; $ext = null; $origName = 'nanobanana-'.date('Ymd-His')."-$idx";
        if (strpos($url, 'data:') === 0) {
            if (preg_match('#^data:(.*?);base64,(.*)$#', $url, $m)) {
                $mime = strtolower(trim($m[1]));
                $binary = base64_decode($m[2]);
            }
        } else if (preg_match('#^https?://#i', $url)) {
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
        
        $hash = hash('sha256', $binary);
        if (isset($seenHashes[$hash])) { continue; }
        $seenHashes[$hash] = true;
        
        $map = [ 'image/png' => 'png', 'image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp' ];
        $ext = $map[$mime] ?? null;
        if (!$ext) { continue; }

        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;
        $filePath = $storagePath . '/' . $storedName;
        if (@file_put_contents($filePath, $binary) === false) { continue; }

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
            $imagesNormalized[] = [ 'image_url' => [ 'url' => '/api/files/serve.php?id=' . $fileId ] ];
        } catch (\Exception $e) {
            @unlink($filePath);
        }
    }

    return !empty($imagesNormalized) ? $imagesNormalized : null;
}

function enforceImageOutputHistory(array $history): array {
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

function buildImageModeHistory(array $history): array {
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

function extractTextFromContent($content): string {
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

function isGenericImageFollowup(string $text): bool {
    $normalized = mb_strtolower(trim($text));
    if ($normalized === '') return true;

    if (mb_strlen($normalized) <= 45 && preg_match('/^(genera( la)? imagen|hazlo|dale|adelante|ok|vale|perfecto|sí|si)$/u', $normalized)) {
        return true;
    }

    return (bool)preg_match('/^(genera( la)? imagen|hazlo|dale|adelante)\b/u', $normalized);
}

function shouldFallbackPdfToLocalText(\Exception $e): bool {
    $message = mb_strtolower($e->getMessage());
    return str_contains($message, 'failed to parse') && str_contains($message, 'pdf');
}

function buildPdfFallbackPrompt(string $message, string $pdfText, string $fileName): string {
    $prefix = "IMPORTANTE: El siguiente contenido proviene del PDF '" . $fileName . "'.\n" .
              "Úsalo como fuente principal para responder sobre el documento.\n" .
              "Si el dato no aparece en el texto extraído, indícalo claramente.\n\n";

    if (trim($message) !== '') {
        return $prefix . $message . "\n\n" . $pdfText;
    }

    return $prefix . "Analiza el contenido de este PDF:\n\n" . $pdfText;
}

/**
 * Indica si el historial contiene algún mensaje de usuario con un PDF adjunto.
 */
function pdfHistoryContainsAttachment(array $history): bool {
    foreach ($history as $item) {
        if (($item['role'] ?? '') !== 'user') continue;
        if (($item['file']['mime_type'] ?? '') === 'application/pdf') {
            return true;
        }
    }
    return false;
}

/**
 * Intenta reemplazar cada PDF adjunto del historial por su texto extraído
 * localmente (pdftotext). Devuelve el nuevo historial si al menos un PDF
 * se pudo convertir, o null si no se pudo extraer ninguno (p. ej. porque
 * pdftotext no está instalado en el servidor).
 */
function replacePdfAttachmentsWithLocalText(array $history): ?array {
    $extractor = new ContentExtractor();
    $replaced = false;

    foreach ($history as $idx => $item) {
        if (($item['role'] ?? '') !== 'user') continue;
        if (($item['file']['mime_type'] ?? '') !== 'application/pdf') continue;

        $pdfData = (string)($item['file']['data'] ?? '');
        $fileName = (string)($item['file']['name'] ?? 'documento.pdf');
        if ($pdfData === '') continue;

        $extraction = $extractor->extractFromPdfLocally($pdfData);
        if (empty($extraction['success']) || empty($extraction['content'])) {
            continue;
        }

        $pdfText = (string)$extraction['content'];
        $maxChars = 15000;
        if (mb_strlen($pdfText) > $maxChars) {
            $pdfText = mb_substr($pdfText, 0, $maxChars) . "\n\n[Contenido truncado para mantener contexto manejable]";
        }

        $currentMessage = is_string($item['content'] ?? null) ? (string)$item['content'] : '';
        $history[$idx]['content'] = buildPdfFallbackPrompt($currentMessage, $pdfText, $fileName);
        unset($history[$idx]['file']);
        $replaced = true;
    }

    return $replaced ? $history : null;
}

function replaceLastUserPdfWithText(array $history, string $fallbackMessage): array {
    for ($i = count($history) - 1; $i >= 0; $i--) {
        if (($history[$i]['role'] ?? '') !== 'user') {
            continue;
        }

        if (($history[$i]['file']['mime_type'] ?? '') !== 'application/pdf') {
            continue;
        }

        $history[$i]['content'] = $fallbackMessage;
        unset($history[$i]['file']);
        break;
    }

    return $history;
}

function preprocessPdfHistoryForStreaming(array $history): array {
    if (empty($history)) {
        return $history;
    }

    $extractor = new ContentExtractor();

    foreach ($history as $idx => $item) {
        if (($item['role'] ?? '') !== 'user') {
            continue;
        }

        $mimeType = (string)($item['file']['mime_type'] ?? '');
        if ($mimeType !== 'application/pdf') {
            continue;
        }

        $pdfData = (string)($item['file']['data'] ?? '');
        $fileName = (string)($item['file']['name'] ?? 'documento.pdf');
        if ($pdfData === '') {
            unset($history[$idx]['file']);
            continue;
        }

        $extraction = $extractor->extractFromPdfLocally($pdfData);

        if (!empty($extraction['success']) && !empty($extraction['content'])) {
            $pdfText = (string)$extraction['content'];
            $maxChars = 15000;
            if (mb_strlen($pdfText) > $maxChars) {
                $pdfText = mb_substr($pdfText, 0, $maxChars) . "\n\n[Contenido truncado para mantener contexto manejable]";
            }

            $currentMessage = is_string($item['content'] ?? null) ? (string)$item['content'] : '';
            $history[$idx]['content'] = buildPdfFallbackPrompt($currentMessage, $pdfText, $fileName);
            // Si pudimos extraer texto localmente, quitamos el adjunto PDF
            // para evitar errores intermitentes del parser remoto.
            unset($history[$idx]['file']);
        }
        // Si la extracción local falla, conservamos el PDF adjunto para que
        // OpenRouter pueda intentar procesarlo con su parser nativo.
    }

    return $history;
}
