<?php
/**
 * API: Generar documento (PDF/DOCX) desde contenido del chat
 * POST /api/chat/generate-document.php
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Utils\DocumentGenerator;

/**
 * Limpia el contenido de chat eliminando intros/outros típicas de la IA
 * Prioriza el uso de delimitadores [DOC_START] y [DOC_END]
 */
function cleanChatContent(string $content): string {
    // 1. Intentar extraer por delimitadores explícitos (más preciso)
    if (preg_match('/\[DOC_START\](.*?)\[DOC_END\]/s', $content, $matches)) {
        return trim($matches[1]);
    }

    // 2. Si no hay delimitadores, usar lógica de regex (fallback)
    $lines = explode("\n", $content);
    $cleanLines = [];
    $foundContent = false;
    
    // Patrones de intros típicas de IA (al inicio)
    $introPatterns = [
        '/^¡?[Cc]laro( que sí)?!?/u',
        '/^¡?[Pp]or supuesto!?/u',
        '/^¡?[Ee]xcelente!?/u',
        '/^¡?[Pp]erfecto!?/u',
        '/^[Aa]quí (te |está |tienes)/u',
        '/^[Cc]on (mucho )?gusto/u',
        '/^[Ee]staré encantad[oa]/u',
        '/^[Aa] continuación/u',
        '/^[Tt]e (presento|muestro|comparto)/u',
    ];
    
    // Patrones de outros típicas de IA (al final)
    $outroPatterns = [
        '/^¿[Nn]ecesitas (algo|alguna cosa) más/u',
        '/^¿[Tt]e puedo ayudar (con|en) algo más/u',
        '/^[Ee]spero que (esto |te )/u',
        '/^[Ss]i (tienes|necesitas) (alguna )?/u',
        '/^¿[Qq]uieres que /u',
        '/^[Nn]o dudes en /u',
        '/^[Qq]uedo a tu disposición/u',
    ];
    
    foreach ($lines as $line) {
        $trimmed = trim($line);
        
        // Saltar líneas vacías al inicio
        if (!$foundContent && $trimmed === '') {
            continue;
        }
        
        // Detectar inicio de contenido real (markdown headers o contenido sustancial)
        if (!$foundContent) {
            // Si es un header markdown, empezamos aquí
            if (preg_match('/^#{1,3}\s+/', $trimmed)) {
                $foundContent = true;
                $cleanLines[] = $line;
                continue;
            }
            
            // Si es una línea de intro, la saltamos
            $isIntro = false;
            foreach ($introPatterns as $pattern) {
                if (preg_match($pattern, $trimmed)) {
                    $isIntro = true;
                    break;
                }
            }
            
            if ($isIntro) {
                continue;
            }
            
            // Si llegamos aquí con contenido sustancial, lo incluimos
            if (strlen($trimmed) > 0) {
                $foundContent = true;
                $cleanLines[] = $line;
            }
        } else {
            $cleanLines[] = $line;
        }
    }
    
    // Eliminar outros del final
    while (count($cleanLines) > 0) {
        $lastLine = trim(end($cleanLines));
        
        if ($lastLine === '' || $lastLine === '---') {
            array_pop($cleanLines);
            continue;
        }
        
        $isOutro = false;
        foreach ($outroPatterns as $pattern) {
            if (preg_match($pattern, $lastLine)) {
                $isOutro = true;
                break;
            }
        }
        
        if ($isOutro) {
            array_pop($cleanLines);
        } else {
            break;
        }
    }
    
    return implode("\n", $cleanLines);
}

/**
 * Extrae el título del primer h1 o h2 del markdown
 */
function extractTitleFromMarkdown(string $markdown): ?string {
    // Buscar primer h1 o h2
    if (preg_match('/^#{1,2}\s+(.+)$/m', $markdown, $matches)) {
        return trim($matches[1]);
    }
    return null;
}

$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

// Validar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

// Parsear input
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$content = trim((string)($input['content'] ?? ''));
$format = strtolower(trim((string)($input['format'] ?? '')));
$title = trim((string)($input['title'] ?? 'Documento'));

// Validar
if ($content === '') {
    Response::error('missing_content', 'Se requiere contenido', 400);
}

if (!in_array($format, ['pdf', 'docx'])) {
    Response::error('invalid_format', 'Formato debe ser pdf o docx', 400);
}

try {
    // Limpiar contenido con regex (sin llamar a la IA - mucho más rápido)
    $finalContent = cleanChatContent($content);
    
    // Extraer título del primer h1/h2 del contenido, o usar el pasado
    $finalTitle = extractTitleFromMarkdown($finalContent) ?: $title;

    $generator = new DocumentGenerator();
    
    if ($format === 'pdf') {
        $result = $generator->generatePdf($finalContent, $finalTitle);
    } else {
        $result = $generator->generateDocx($finalContent, $finalTitle);
    }
    
    if (!$result['success']) {
        throw new \Exception($result['error'] ?? 'Error desconocido en el generador');
    }
    
    $filePath = $result['path'];
    
    // Leer archivo y enviarlo
    if (!file_exists($filePath)) {
        throw new \Exception('El archivo generado no existe en la ruta esperada');
    }
    
    $fileContent = file_get_contents($filePath);
    $fileName = basename($filePath);
    
    // Limpiar archivo temporal
    @unlink($filePath);
    
    // Enviar como base64 para el frontend
    Response::json([
        'success' => true,
        'filename' => $fileName,
        'content' => base64_encode($fileContent),
        'mime_type' => $format === 'pdf' ? 'application/pdf' : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ]);
    
} catch (\Exception $e) {
    Response::error('generation_error', $e->getMessage(), 500);
}
