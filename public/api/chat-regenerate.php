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
    $newContent = null;
    $replacementMethod = 'none';
    
    // Estrategia 1: Reemplazo directo (coincidencia exacta)
    if (strpos($originalContent, $selectedText) !== false) {
        $newContent = str_replace($selectedText, $editedPart, $originalContent);
        $replacementMethod = 'direct';
    }
    
    // Estrategia 2: Normalizar espacios y quitar formato markdown para BUSCAR, pero reemplazar en ORIGINAL
    if ($newContent === null || $newContent === $originalContent) {
        $normalizedSelected = preg_replace('/\s+/', ' ', trim($selectedText));
        
        // Quitar markdown más robusto para búsqueda
        $strippedOriginal = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
        $strippedOriginal = preg_replace('/\s+/', ' ', $strippedOriginal);
        
        $pos = strpos($strippedOriginal, $normalizedSelected);
        if ($pos !== false) {
            // Encontramos dónde está en la versión sin formato
            // Usar enfoque "fuzzy" con anclas de palabras
            $words = explode(' ', $normalizedSelected);
            if (count($words) > 4) {
                $startAnchor = implode(' ', array_slice($words, 0, 3));
                $endAnchor = implode(' ', array_slice($words, -3));
                
                $strippedOriginalForAnchors = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
                $startPos = strpos($strippedOriginalForAnchors, $startAnchor);
                $endPos = strpos($strippedOriginalForAnchors, $endAnchor, $startPos);
                
                if ($startPos !== false && $endPos !== false) {
                    // Mapear de vuelta al original buscando coincidencias cercanas
                }
            }
        }
    }
    
    // Estrategia 3: Coincidencia basada en anclas (preserva saltos de línea mejor)
    if ($newContent === null || $newContent === $originalContent) {
        // Encontrar ancla de inicio (primeros 40 chars, ignorando markdown)
        $startText = preg_replace('/(\*\*|\*|#|`)/', '', substr($selectedText, 0, 40));
        $startText = preg_replace('/\s+/', ' ', trim($startText));
        
        // Encontrar ancla de fin (últimos 40 chars, ignorando markdown)
        $endText = preg_replace('/(\*\*|\*|#|`)/', '', substr($selectedText, -40));
        $endText = preg_replace('/\s+/', ' ', trim($endText));

        $strippedOriginal = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
        
        $startMatch = strpos($strippedOriginal, $startText);
        $endMatch = strpos($strippedOriginal, $endText, $startMatch ?: 0);

        if ($startMatch !== false && $endMatch !== false) {
            // Regex para encontrar texto de inicio con caracteres markdown opcionales entre palabras
            $startWords = explode(' ', $startText);
            $endWords = explode(' ', $endText);
            
            if (count($startWords) > 0 && count($endWords) > 0) {
                $startRegex = '/' . preg_quote($startWords[0], '/') . '.*?' . preg_quote(end($startWords), '/') . '/s';
                $endRegex = '/' . preg_quote($endWords[0], '/') . '.*?' . preg_quote(end($endWords), '/') . '/s';
                
                if (preg_match($startRegex, $originalContent, $m1, PREG_OFFSET_CAPTURE) && 
                    preg_match($endRegex, $originalContent, $m2, PREG_OFFSET_CAPTURE, $m1[0][1])) {
                    
                    $actualStart = $m1[0][1];
                    $actualEnd = $m2[0][1] + strlen($m2[0][0]);
                    
                    $newContent = substr($originalContent, 0, $actualStart) . 
                                 $editedPart . 
                                 substr($originalContent, $actualEnd);
                    $replacementMethod = 'fuzzy_anchor';
                }
            }
        }
    }
    
    // Estrategia 4: Si la selección es una porción significativa, reemplazar todo
    if ($newContent === null || $newContent === $originalContent) {
        // Quitar markdown de ambos para comparación justa de longitud
        $strippedSelected = preg_replace('/\s+/', '', $selectedText);
        $strippedOriginal = preg_replace('/\*+/', '', $originalContent);
        $strippedOriginal = preg_replace('/\s+/', '', $strippedOriginal);
        
        $selectedRatio = strlen($strippedSelected) / max(strlen($strippedOriginal), 1);
        
        // Si el usuario seleccionó más del 20% del contenido, simplemente reemplazar con versión editada
        if ($selectedRatio > 0.2 || strlen($selectedText) > 100) {
            $newContent = $editedPart;
            $replacementMethod = 'full_replace';
        } else {
            // Fallback verdadero - esto no debería pasar a menudo
            $newContent = $editedPart;
            $replacementMethod = 'fallback_replace';
        }
    }
    
    // Actualizar el mensaje en la base de datos
    $msgs->updateContent($messageId, $newContent);
    
    // Actualizar timestamp de conversación
    $convos->touch($conversationId);
    
    // Log para debug
    error_log("Regeneración - Método: $replacementMethod, Seleccionado: " . strlen($selectedText) . " chars, Original: " . strlen($originalContent) . " chars");
    
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
