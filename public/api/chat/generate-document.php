<?php
/**
 * API: Generar documento (PDF/DOCX) desde contenido del chat
 * POST /api/chat/generate-document.php
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Utils\DocumentGenerator;
use Chat\OpenRouterClient;

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
    // Si el contenido es una respuesta de chat, intentar extraer el núcleo y un título mejor
    $finalContent = $content;
    $finalTitle = $title;

    // Solo limpiar si parece una respuesta de chat (tiene intros/outros típicos)
    if (strlen($content) > 200) {
        try {
            $client = new OpenRouterClient();
            $prompt = "Actúa como un editor de documentos profesional. 
            Te voy a pasar una respuesta de un asistente de IA. Tu tarea es:
            1. Extraer ÚNICAMENTE el cuerpo principal del contenido (el informe, el artículo, el análisis, etc.).
            2. ELIMINAR saludos iniciales, comentarios de cortesía ('¡Claro!', 'Espero que esto te ayude', etc.) y despedidas.
            3. Generar un TÍTULO corto y descriptivo para este documento.
            
            Responde ÚNICAMENTE con un JSON con este formato:
            {
              \"title\": \"Título del documento\",
              \"content\": \"Contenido Markdown limpio\"
            }
            
            CONTENIDO A PROCESAR:
            " . $content;

            $refineResponse = $client->generate($prompt, 'google/gemini-3-flash-preview');
            
            // Limpieza más robusta del JSON (Gemini a veces incluye markdown incluso si se le pide que no)
            $cleanJson = $refineResponse;
            if (preg_match('/\{.*\}/s', $refineResponse, $matches)) {
                $cleanJson = $matches[0];
            }
            
            $data = json_decode($cleanJson, true);
            
            if ($data && isset($data['content']) && isset($data['title'])) {
                $finalContent = $data['content'];
                $finalTitle = $data['title'];
            }
        } catch (\Exception $e) {
            // Si falla el refinamiento, seguimos con el contenido original
        }
    }

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
