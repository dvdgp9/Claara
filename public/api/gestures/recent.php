<?php
/**
 * API: Obtener últimas publicaciones de una línea de negocio
 * GET /api/gestures/recent.php?type=social-media&business_line=ebone&limit=5
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Gestures\GestureExecutionsRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'Not authenticated', 401);
}

$gestureType = $_GET['type'] ?? '';
$businessLine = $_GET['business_line'] ?? '';
$limit = min((int)($_GET['limit'] ?? 5), 10);

if (!$gestureType || !$businessLine) {
    Response::error('missing_params', 'Faltan parámetros: type, business_line', 400);
}

$repo = new GestureExecutionsRepo();

try {
    $rows = $repo->getRecentByBusinessLine($user['id'], $gestureType, $businessLine, $limit);
    
    // Extraer solo el texto de las publicaciones (sin hashtags ni marcadores)
    $posts = [];
    foreach ($rows as $row) {
        $content = $row['output_content'];
        
        // Extraer solo la publicación
        $postMatch = preg_match('/---PUBLICACION---\s*([\s\S]*?)\s*---HASHTAGS---/', $content, $matches);
        if ($postMatch && !empty($matches[1])) {
            $posts[] = trim($matches[1]);
        } else {
            // Fallback: limpiar marcadores
            $clean = preg_replace('/---PUBLICACION---|---HASHTAGS---|---FIN---/', '', $content);
            $clean = preg_replace('/(#\w+\s*)+$/', '', $clean);
            $posts[] = trim($clean);
        }
    }
    
    Response::json([
        'success' => true,
        'posts' => $posts,
        'count' => count($posts)
    ]);
    
} catch (Exception $e) {
    Response::error('db_error', 'Error al obtener publicaciones: ' . $e->getMessage(), 500);
}
