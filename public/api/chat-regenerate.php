<?php
/**
 * Chat Regenerate Selection Endpoint
 * Regenera una parte específica de una respuesta de IA según las instrucciones del usuario
 */

require_once __DIR__ . '/../../src/App/bootstrap.php';
require_once __DIR__ . '/../../src/Chat/OpenRouterClient.php';
require_once __DIR__ . '/../../src/Chat/ContextBuilder.php';
require_once __DIR__ . '/../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../src/Repos/ConversationsRepo.php';
require_once __DIR__ . '/../../src/Repos/MessagesRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Chat\OpenRouterClient;
use Chat\ContextBuilder;
use Repos\ConversationsRepo;
use Repos\MessagesRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$originalContent = trim((string)($input['original_content'] ?? ''));
$selectedText = trim((string)($input['selected_text'] ?? ''));
$instructions = trim((string)($input['instructions'] ?? ''));

// Validar input
if (!$messageId || !$conversationId) {
    Response::error('validation_error', 'Se requiere ID de mensaje y conversación', 400);
}

if ($selectedText === '' || $instructions === '') {
    Response::error('validation_error', 'Se requiere texto seleccionado e instrucciones', 400);
}

$convos = new ConversationsRepo();
$msgs = new MessagesRepo();

// Verificar que el usuario es dueño de la conversación
$conversation = $convos->findByIdForUser($conversationId, (int)$user['id']);
if (!$conversation) {
    Response::error('not_found', 'Conversación no encontrada', 404);
}

// Obtener el mensaje a editar
$messages = $msgs->listByConversation($conversationId);
$targetMessage = null;
foreach ($messages as $m) {
    if ((int)$m['id'] === $messageId && $m['role'] === 'assistant') {
        $targetMessage = $m;
        break;
    }
}

if (!$targetMessage) {
    Response::error('not_found', 'Mensaje no encontrado o no editable', 404);
}

// Construir el prompt de regeneración
$contextBuilder = new ContextBuilder();
$systemPrompt = $contextBuilder->buildSystemPrompt();

$editPrompt = <<<PROMPT
Estás ayudando a editar una parte específica de una respuesta anterior de IA.

RESPUESTA ORIGINAL COMPLETA:
{$targetMessage['content']}

TEXTO SELECCIONADO PARA EDITAR:
{$selectedText}

INSTRUCCIONES DEL USUARIO:
{$instructions}

Por favor proporciona SOLO el texto de reemplazo para la parte seleccionada. No incluyas el resto de la respuesta, solo la parte editada que debe reemplazar el texto seleccionado. Mantén el mismo estilo y tono que el original.
PROMPT;

try {
    $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', $systemPrompt);
    $editedPart = $client->generateText($editPrompt);
    
    $originalContent = $targetMessage['content'];
    $newContent = replaceSelectedText($originalContent, $selectedText, $editedPart);
    
    // Actualizar el mensaje en la base de datos
    $msgs->updateContent($messageId, $newContent);
    
    // Actualizar timestamp de conversación
    $convos->touch($conversationId);
    
    Response::json([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'content' => $newContent,
            'edited_part' => $editedPart
        ]
    ]);
    
} catch (\Exception $e) {
    Response::error('generation_error', 'Error al regenerar: ' . $e->getMessage(), 500);
}

/**
 * Reemplaza el texto seleccionado dentro del contenido original.
 * Prueba múltiples estrategias hasta encontrar una que funcione.
 * NUNCA devuelve solo el reemplazo - siempre incluye el contexto original.
 */
function replaceSelectedText(string $original, string $selected, string $replacement): string {
    // Estrategia 1: Coincidencia exacta
    if (strpos($original, $selected) !== false) {
        return str_replace($selected, $replacement, $original);
    }
    
    // Estrategia 2: Normalizar saltos de línea (el navegador puede cambiar \r\n a \n)
    $selectedNormalized = str_replace(["\r\n", "\r"], "\n", $selected);
    $originalNormalized = str_replace(["\r\n", "\r"], "\n", $original);
    
    if (strpos($originalNormalized, $selectedNormalized) !== false) {
        return str_replace($selectedNormalized, $replacement, $originalNormalized);
    }
    
    // Estrategia 3: Colapsar espacios múltiples a uno solo
    $selectedCollapsed = preg_replace('/[ \t]+/', ' ', $selectedNormalized);
    $originalCollapsed = preg_replace('/[ \t]+/', ' ', $originalNormalized);
    
    $pos = strpos($originalCollapsed, $selectedCollapsed);
    if ($pos !== false) {
        // Encontrar las posiciones en el original sin colapsar
        return replaceByPosition($originalNormalized, $originalCollapsed, $selectedCollapsed, $replacement);
    }
    
    // Estrategia 4: Buscar por primeras y últimas palabras (anclas)
    $words = preg_split('/\s+/', trim($selected));
    if (count($words) >= 4) {
        $firstWords = implode(' ', array_slice($words, 0, min(5, count($words))));
        $lastWords = implode(' ', array_slice($words, -min(5, count($words))));
        
        // Buscar patrón: primeras palabras ... últimas palabras
        $pattern = '/' . preg_quote($firstWords, '/') . '.*?' . preg_quote($lastWords, '/') . '/s';
        
        if (preg_match($pattern, $original, $matches, PREG_OFFSET_CAPTURE)) {
            $matchStart = $matches[0][1];
            $matchLength = strlen($matches[0][0]);
            
            return substr($original, 0, $matchStart) . $replacement . substr($original, $matchStart + $matchLength);
        }
    }
    
    // Estrategia 5: Búsqueda difusa con similar_text
    $bestMatch = findBestMatch($original, $selected);
    if ($bestMatch !== null) {
        return substr($original, 0, $bestMatch['start']) . $replacement . substr($original, $bestMatch['end']);
    }
    
    // Fallback: NO reemplazar todo. Devolver original con nota de error al final.
    // Esto es mejor que perder todo el contenido.
    error_log("Regeneración fallida - no se encontró coincidencia para: " . substr($selected, 0, 100));
    return $original . "\n\n[Nota: No se pudo localizar el texto exacto para reemplazar. Texto regenerado: " . $replacement . "]";
}

/**
 * Reemplaza por posición mapeando desde string colapsado al original
 */
function replaceByPosition(string $original, string $collapsed, string $selectedCollapsed, string $replacement): string {
    $pos = strpos($collapsed, $selectedCollapsed);
    if ($pos === false) return $original;
    
    // Mapear posición del string colapsado al original
    $origPos = 0;
    $collPos = 0;
    $startInOrig = 0;
    $endInOrig = 0;
    
    $origLen = strlen($original);
    $targetStart = $pos;
    $targetEnd = $pos + strlen($selectedCollapsed);
    
    while ($origPos < $origLen && $collPos < $targetEnd) {
        if ($collPos === $targetStart) {
            $startInOrig = $origPos;
        }
        
        $origChar = $original[$origPos];
        $collChar = $collapsed[$collPos] ?? '';
        
        if ($origChar === $collChar) {
            $origPos++;
            $collPos++;
        } elseif ($origChar === ' ' || $origChar === "\t") {
            // Espacio extra en original que fue colapsado
            $origPos++;
        } else {
            // Avanzar ambos
            $origPos++;
            $collPos++;
        }
    }
    $endInOrig = $origPos;
    
    return substr($original, 0, $startInOrig) . $replacement . substr($original, $endInOrig);
}

/**
 * Busca la mejor coincidencia aproximada usando ventana deslizante
 */
function findBestMatch(string $haystack, string $needle): ?array {
    $needleLen = strlen($needle);
    $haystackLen = strlen($haystack);
    
    if ($needleLen > $haystackLen) return null;
    
    $bestSimilarity = 0;
    $bestStart = 0;
    $bestEnd = 0;
    $threshold = 0.85; // 85% de similitud mínima
    
    // Buscar con ventanas de tamaño similar al needle (+/- 20%)
    $minWindow = (int)($needleLen * 0.8);
    $maxWindow = (int)($needleLen * 1.2);
    
    for ($windowSize = $minWindow; $windowSize <= $maxWindow; $windowSize += max(1, (int)($needleLen * 0.1))) {
        for ($i = 0; $i <= $haystackLen - $windowSize; $i += max(1, (int)($windowSize * 0.1))) {
            $chunk = substr($haystack, $i, $windowSize);
            
            similar_text($needle, $chunk, $percent);
            $similarity = $percent / 100;
            
            if ($similarity > $bestSimilarity && $similarity >= $threshold) {
                $bestSimilarity = $similarity;
                $bestStart = $i;
                $bestEnd = $i + $windowSize;
            }
        }
    }
    
    if ($bestSimilarity >= $threshold) {
        return ['start' => $bestStart, 'end' => $bestEnd, 'similarity' => $bestSimilarity];
    }
    
    return null;
}
