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
    // El usuario menciona que tarda mucho, eliminamos el refinamiento por IA si no es estrictamente necesario
    // o lo hacemos más selectivo. Por ahora, para ganar velocidad, vamos a limpiar manualmente
    // intros y outros comunes si es posible, o simplemente quitar la llamada a la IA.
    
    /* 
    // Comentado temporalmente para mejorar velocidad por petición del usuario
    if (strlen($content) > 200) {
        try {
            $client = new OpenRouterClient(null, 'google/gemini-3-flash-preview', null);
            // ... (resto de la lógica de refinamiento)
        } catch (\Exception $e) {}
    }
    */

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
