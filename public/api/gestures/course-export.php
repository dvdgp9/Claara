<?php
/**
 * API: Exportar contenido de curso a Word/PDF
 * POST /api/gestures/course-export.php
 * 
 * Tipos de exportación:
 * - module: Un módulo individual
 * - course: Todo el contenido del curso (todos los módulos)
 * - material: Material adicional (flashcards, tests, examen, podcast)
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Utils\DocumentGenerator;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'No autenticado', 401);
}

Session::requireCsrf();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];

$exportType = $body['export_type'] ?? null; // module, course, material
$content = $body['content'] ?? null;
$title = $body['title'] ?? 'Documento';
$format = strtolower($body['format'] ?? 'docx'); // docx o pdf

// Validaciones
if (!in_array($exportType, ['module', 'course', 'material'])) {
    Response::error('invalid_type', 'Tipo de exportación no válido', 400);
}

if (empty($content)) {
    Response::error('missing_content', 'Se requiere contenido para exportar', 400);
}

if (!in_array($format, ['docx', 'pdf'])) {
    Response::error('invalid_format', 'Formato debe ser docx o pdf', 400);
}

try {
    $generator = new DocumentGenerator();
    
    // Preparar el contenido según el tipo
    $finalContent = $content;
    $finalTitle = $title;
    
    // Añadir encabezado según tipo
    switch ($exportType) {
        case 'module':
            // El contenido ya viene formateado, solo aseguramos el título
            if (strpos($finalContent, '# ') !== 0 && strpos($finalContent, '## ') !== 0) {
                $finalContent = "# {$finalTitle}\n\n{$finalContent}";
            }
            break;
            
        case 'course':
            // Añadir título del curso si no está
            if (strpos($finalContent, '# ') !== 0) {
                $finalContent = "# {$finalTitle}\n\n{$finalContent}";
            }
            break;
            
        case 'material':
            // Añadir título del material
            if (strpos($finalContent, '# ') !== 0 && strpos($finalContent, '## ') !== 0) {
                $finalContent = "# {$finalTitle}\n\n{$finalContent}";
            }
            break;
    }
    
    // Generar documento
    if ($format === 'pdf') {
        $result = $generator->generatePdf($finalContent, $finalTitle);
    } else {
        $result = $generator->generateDocx($finalContent, $finalTitle);
    }
    
    if (!$result['success']) {
        throw new \Exception($result['error'] ?? 'Error generando documento');
    }
    
    $filePath = $result['path'];
    
    if (!file_exists($filePath)) {
        throw new \Exception('El archivo generado no existe');
    }
    
    $fileContent = file_get_contents($filePath);
    $fileName = basename($filePath);
    
    // Limpiar archivo temporal
    @unlink($filePath);
    
    // Enviar como base64
    Response::json([
        'success' => true,
        'filename' => $fileName,
        'content' => base64_encode($fileContent),
        'mime_type' => $format === 'pdf' 
            ? 'application/pdf' 
            : 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ]);
    
} catch (\Exception $e) {
    Response::error('export_error', 'Error: ' . $e->getMessage(), 500);
}
