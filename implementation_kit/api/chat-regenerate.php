<?php
/**
 * Chat Regenerate Selection Endpoint
 * Regenerates a specific part of an AI response based on user instructions
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
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$messageId = isset($input['message_id']) ? (int)$input['message_id'] : 0;
$conversationId = isset($input['conversation_id']) ? (int)$input['conversation_id'] : 0;
$originalContent = trim((string)($input['original_content'] ?? ''));
$selectedText = trim((string)($input['selected_text'] ?? ''));
$instructions = trim((string)($input['instructions'] ?? ''));

// Validate input
if (!$messageId || !$conversationId) {
    Response::error('validation_error', 'Message ID and conversation ID are required', 400);
}

if ($selectedText === '' || $instructions === '') {
    Response::error('validation_error', 'Selected text and instructions are required', 400);
}

$convos = new ConversationsRepo();
$msgs = new MessagesRepo();

// Verify user owns the conversation
$conversation = $convos->findByIdForUser($conversationId, (int)$user['id']);
if (!$conversation) {
    Response::error('not_found', 'Conversation not found', 404);
}

// Get the message to edit
$messages = $msgs->listByConversation($conversationId);
$targetMessage = null;
foreach ($messages as $m) {
    if ((int)$m['id'] === $messageId && $m['role'] === 'assistant') {
        $targetMessage = $m;
        break;
    }
}

if (!$targetMessage) {
    Response::error('not_found', 'Message not found or not editable', 404);
}

// Build the regeneration prompt
$contextBuilder = new ContextBuilder();
$systemPrompt = $contextBuilder->buildSystemPrompt();

$editPrompt = <<<PROMPT
You are helping to edit a specific part of a previous AI response.

ORIGINAL FULL RESPONSE:
{$targetMessage['content']}

SELECTED TEXT TO EDIT:
{$selectedText}

USER'S INSTRUCTIONS:
{$instructions}

Please provide ONLY the replacement text for the selected portion. Do not include the rest of the response, just the edited part that should replace the selected text. Maintain the same style and tone as the original.
PROMPT;

try {
    $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', $systemPrompt);
    $editedPart = $client->generateText($editPrompt);
    
    $originalContent = $targetMessage['content'];
    $newContent = null;
    $replacementMethod = 'none';
    
    // Strategy 1: Direct replacement (exact match)
    if (strpos($originalContent, $selectedText) !== false) {
        $newContent = str_replace($selectedText, $editedPart, $originalContent);
        $replacementMethod = 'direct';
    }
    
    // Strategy 2: Normalize whitespace and strip markdown formatting for FINDING the text, but replace in ORIGINAL
    if ($newContent === null || $newContent === $originalContent) {
        $normalizedSelected = preg_replace('/\s+/', ' ', trim($selectedText));
        
        // Use a more robust stripping for matching purposes
        $strippedOriginal = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
        $strippedOriginal = preg_replace('/\s+/', ' ', $strippedOriginal);
        
        $pos = strpos($strippedOriginal, $normalizedSelected);
        if ($pos !== false) {
            // We found where it is in the stripped version. 
            // Now we need to find the approximate start and end in the real content.
            // Since this is complex, we'll use a simpler "fuzzy" approach:
            // Find the first and last few words of the selection in the original.
            $words = explode(' ', $normalizedSelected);
            if (count($words) > 4) {
                $startAnchor = implode(' ', array_slice($words, 0, 3));
                $endAnchor = implode(' ', array_slice($words, -3));
                
                // Strip markdown for anchor searching too
                $strippedOriginalForAnchors = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
                $startPos = strpos($strippedOriginalForAnchors, $startAnchor);
                $endPos = strpos($strippedOriginalForAnchors, $endAnchor, $startPos);
                
                if ($startPos !== false && $endPos !== false) {
                    // Map these back to original by finding nearest matches
                    // For simplicity and to avoid broken markdown, if it's a large selection,
                    // we might be better off with the anchor strategy below which is more precise.
                }
            }
            
            // If Strategy 2 logic gets too complex, fallback to anchor which is safer for markdown
        }
    }
    
    // Strategy 3: Anchor-based matching (preserves newlines better)
    if ($newContent === null || $newContent === $originalContent) {
        // Find start anchor (first 40 chars, ignoring markdown)
        $startText = preg_replace('/(\*\*|\*|#|`)/', '', substr($selectedText, 0, 40));
        $startText = preg_replace('/\s+/', ' ', trim($startText));
        
        // Find end anchor (last 40 chars, ignoring markdown)
        $endText = preg_replace('/(\*\*|\*|#|`)/', '', substr($selectedText, -40));
        $endText = preg_replace('/\s+/', ' ', trim($endText));

        $strippedOriginal = preg_replace('/(\*\*|\*|#|`)/', '', $originalContent);
        
        $startMatch = strpos($strippedOriginal, $startText);
        $endMatch = strpos($strippedOriginal, $endText, $startMatch);

        if ($startMatch !== false && $endMatch !== false) {
            // We have a range in the stripped text. 
            // Instead of trying to map perfectly, let's look for these anchors in the REAL text
            // but allowing for markdown characters in between.
            
            // Regex to find start text with optional markdown characters between words
            $startRegex = '/' . preg_quote(explode(' ', $startText)[0]) . '.*?' . preg_quote(end(explode(' ', $startText))) . '/s';
            $endRegex = '/' . preg_quote(explode(' ', $endText)[0]) . '.*?' . preg_quote(end(explode(' ', $endText))) . '/s';
            
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
    
    // Strategy 4: If selection is a significant portion, replace the whole thing
    if ($newContent === null || $newContent === $originalContent) {
        // Strip markdown from both for fair length comparison
        $strippedSelected = preg_replace('/\s+/', '', $selectedText);
        $strippedOriginal = preg_replace('/\*+/', '', $originalContent);
        $strippedOriginal = preg_replace('/\s+/', '', $strippedOriginal);
        
        $selectedRatio = strlen($strippedSelected) / max(strlen($strippedOriginal), 1);
        
        // If user selected more than 20% of content, just replace with edited version
        if ($selectedRatio > 0.2 || strlen($selectedText) > 100) {
            $newContent = $editedPart;
            $replacementMethod = 'full_replace';
        } else {
            // True fallback - this shouldn't happen often
            $newContent = $editedPart;
            $replacementMethod = 'fallback_replace';
        }
    }
    
    // Update the message in database
    $msgs->updateContent($messageId, $newContent);
    
    // Touch conversation
    $convos->touch($conversationId);
    
    // Log for debugging
    error_log("Regeneration - Method: $replacementMethod, Selected: " . strlen($selectedText) . " chars, Original: " . strlen($originalContent) . " chars");
    
    Response::json([
        'success' => true,
        'message' => [
            'id' => $messageId,
            'content' => $newContent,
            'edited_part' => $editedPart
        ]
    ]);
    
} catch (\Exception $e) {
    Response::error('generation_error', 'Failed to regenerate: ' . $e->getMessage(), 500);
}
