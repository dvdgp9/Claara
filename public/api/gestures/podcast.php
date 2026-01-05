<?php
/**
 * API: Generar podcast desde artículo
 * POST /api/gestures/podcast.php
 * 
 * Flujo:
 * 1. Recibe URL o contenido de artículo
 * 2. Extrae contenido del artículo
 * 3. Genera guion de podcast (2 voces)
 * 4. Convierte guion a audio con Gemini TTS
 * 5. Devuelve audio WAV en base64
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use App\Env;
use Audio\ContentExtractor;
use Audio\PodcastScriptGenerator;
use Audio\GeminiTtsClient;
use Gestures\GestureExecutionsRepo;

Session::start();
$user = Session::user();

if (!$user) {
    Response::error('unauthorized', 'No autenticado', 401);
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Solo POST', 405);
}

// Parsear body
$body = json_decode(file_get_contents('php://input'), true) ?? [];

$sourceType = $body['source_type'] ?? 'url'; // 'url', 'pdf', 'text'
$sourceUrl = $body['url'] ?? '';
$sourceText = $body['text'] ?? '';
$sourcePdf = $body['pdf_base64'] ?? '';
$action = $body['action'] ?? 'full'; // 'extract', 'script', 'audio', 'full'

// Validaciones
if ($sourceType === 'url' && empty($sourceUrl)) {
    Response::error('missing_url', 'Se requiere una URL', 400);
}
if ($sourceType === 'text' && empty($sourceText)) {
    Response::error('missing_text', 'Se requiere el texto del artículo', 400);
}
if ($sourceType === 'pdf' && empty($sourcePdf)) {
    Response::error('missing_pdf', 'Se requiere el PDF en base64', 400);
}

// Verificar API Key de Gemini para TTS
if ($action !== 'extract' && $action !== 'script') {
    $geminiKey = Env::get('GEMINI_API_KEY');
    if (empty($geminiKey)) {
        Response::error('missing_gemini_key', 'Falta GEMINI_API_KEY en .env para generar audio', 500);
    }
}

try {
    $extractor = new ContentExtractor();
    $content = null;
    $title = '';
    $source = '';

    // === PASO 1: Extraer contenido ===
    switch ($sourceType) {
        case 'url':
            $result = $extractor->extractFromUrl($sourceUrl);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = $result['source'];
            break;

        case 'pdf':
            $result = $extractor->extractFromPdf($sourcePdf);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'PDF';
            break;

        case 'text':
            $result = $extractor->extractFromText($sourceText);
            if (!$result['success']) {
                Response::error('extraction_failed', $result['error'], 400);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'Texto';
            break;
    }

    // Si solo queremos extracción, devolver aquí
    if ($action === 'extract') {
        Response::json([
            'success' => true,
            'step' => 'extract',
            'title' => $title,
            'content' => $content,
            'word_count' => str_word_count($content),
            'source' => $source
        ]);
    }

    // === PASO 2: Generar guion ===
    // Duración objetivo: 15 minutos para podcasts profundos y naturales
    $scriptGenerator = new PodcastScriptGenerator();
    $scriptResult = $scriptGenerator->generate($content, $title, 15);

    if (!$scriptResult['success']) {
        Response::error('script_failed', $scriptResult['error'], 500);
    }

    $script = $scriptResult['script'];
    $summary = $scriptResult['summary'];
    $speaker1 = $scriptResult['speaker1'];
    $speaker2 = $scriptResult['speaker2'];
    $estimatedDuration = $scriptResult['estimated_duration'];

    // Si solo queremos el guion, devolver aquí
    if ($action === 'script') {
        Response::json([
            'success' => true,
            'step' => 'script',
            'title' => $title,
            'summary' => $summary,
            'script' => $script,
            'speaker1' => $speaker1,
            'speaker2' => $speaker2,
            'estimated_duration' => $estimatedDuration,
            'source' => $source
        ]);
    }

    // === PASO 3: Generar audio ===
    $ttsClient = new GeminiTtsClient();
    
    // Preparar el texto para TTS con formato de diálogo
    $ttsPrompt = "TTS the following podcast conversation between {$speaker1} and {$speaker2} in Spanish:\n\n" . $script;
    
    $audioResult = $ttsClient->generateMultiSpeaker(
        $ttsPrompt,
        $speaker1,
        $speaker2,
        'Aoede',  // Voz femenina
        'Orus'    // Voz masculina
    );

    if (!$audioResult['success']) {
        Response::error('audio_failed', $audioResult['error'], 500);
    }

    // El audio viene como PCM raw, convertir a WAV y guardar en /public/tmp para no agotar memoria en JSON
    $pcmData = base64_decode($audioResult['audio_data']);
    $wavData = GeminiTtsClient::pcmToWav($pcmData);

    $publicTmp = dirname(__DIR__, 2) . '/tmp';
    if (!is_dir($publicTmp)) {
        @mkdir($publicTmp, 0775, true);
    }
    $fileName = 'podcast_' . uniqid() . '.wav';
    $filePath = $publicTmp . '/' . $fileName;
    file_put_contents($filePath, $wavData);
    $wavUrl = '/tmp/' . $fileName;

    // === PASO 4: Guardar en historial ===
    $repo = new GestureExecutionsRepo();
    
    $executionId = $repo->create([
        'user_id' => $user['id'],
        'gesture_type' => 'podcast-from-article',
        'title' => $title ?: 'Podcast: ' . substr($summary, 0, 50),
        'input_data' => [
            'source_type' => $sourceType,
            'source' => $source,
            'url' => $sourceUrl,
            'word_count' => str_word_count($content)
        ],
        'output_content' => $script,
        'output_data' => [
            'summary' => $summary,
            'script' => $script,
            'audio_url' => $wavUrl,
            'duration_estimate' => $estimatedDuration,
            'speaker1' => $speaker1,
            'speaker2' => $speaker2
        ],
        'content_type' => 'original',
        'business_line' => null,
        'model' => 'gemini-2.5-flash-preview-tts'
    ]);

    // === Respuesta final ===
    Response::json([
        'success' => true,
        'step' => 'complete',
        'execution_id' => $executionId,
        'title' => $title,
        'summary' => $summary,
        'script' => $script,
        'speaker1' => $speaker1,
        'speaker2' => $speaker2,
        'audio' => [
            'url' => $wavUrl,
            'mime_type' => 'audio/wav',
            'duration_estimate' => $estimatedDuration
        ],
        'source' => $source
    ]);

} catch (\Exception $e) {
    Response::error('server_error', 'Error: ' . $e->getMessage(), 500);
}
