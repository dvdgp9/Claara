<?php
/**
 * API: Procesar jobs pendientes (llamado por cron o trigger)
 * POST /api/jobs/process.php
 * 
 * Este endpoint procesa UN job pendiente cada vez que se llama.
 * Diseñado para ser llamado por cron cada minuto o por trigger del frontend.
 * 
 * Response: { success: true, processed: bool, job_id: int|null }
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Env;
use Jobs\BackgroundJobsRepo;
use Audio\ContentExtractor;
use Audio\PodcastScriptGenerator;
use Audio\GeminiTtsClient;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

// Permitir llamadas desde cron (sin sesión) o desde frontend (con sesión)
// Para cron, verificar token secreto; para frontend, verificar sesión
$isCliOrCron = php_sapi_name() === 'cli' || !isset($_SERVER['HTTP_HOST']);
$cronToken = $_GET['token'] ?? '';
$expectedToken = Env::get('CRON_SECRET_TOKEN', '');

if (!$isCliOrCron) {
    // Llamada HTTP - verificar token o sesión
    \App\Session::start();
    $user = \App\Session::user();
    
    $hasValidToken = !empty($expectedToken) && hash_equals($expectedToken, $cronToken);
    $hasValidSession = !empty($user);
    
    if (!$hasValidToken && !$hasValidSession) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'No autorizado']);
        exit;
    }
    
    // IMPORTANTE: Liberar sesión para no bloquear otras peticiones del usuario
    // El procesamiento de podcast puede tardar minutos
    session_write_close();
}

// Configurar tiempo máximo de ejecución (15 minutos para podcasts largos por segmentos)
set_time_limit(900);

// Enviar respuesta inmediata al frontend para no bloquear
if (!$isCliOrCron && isset($_SERVER['HTTP_HOST'])) {
    // Desconectar del cliente pero seguir procesando
    ignore_user_abort(true);
    
    header('Content-Type: application/json');
    header('Connection: close');
    
    $response = json_encode(['success' => true, 'processing' => true, 'message' => 'Procesando en background']);
    header('Content-Length: ' . strlen($response));
    
    echo $response;
    
    // Flush y cerrar conexión
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        ob_end_flush();
        flush();
    }
}

$repo = new BackgroundJobsRepo();

// Primero, resetear jobs "colgados" (más de 15 minutos en processing)
$stuckReset = $repo->resetStuckJobs(15);

// Obtener siguiente job pendiente
$job = $repo->getNextPending();

if (!$job) {
    // No hay jobs pendientes
    if ($isCliOrCron) {
        echo "No hay jobs pendientes\n";
        exit(0);
    }
    echo json_encode(['success' => true, 'processed' => false, 'message' => 'No hay jobs pendientes']);
    exit;
}

$jobId = (int)$job['id'];
$jobType = $job['job_type'];
$inputData = $job['input_data'];
$userId = (int)$job['user_id'];

// Log inicio
if ($isCliOrCron) {
    echo "Procesando job #{$jobId} (tipo: {$jobType})\n";
}

try {
    // Marcar como processing
    $repo->markProcessing($jobId, 'Iniciando procesamiento...');
    
    $outputData = [];
    
    switch ($jobType) {
        case 'podcast':
            $outputData = processPodcastJob($jobId, $inputData, $userId, $repo);
            break;
            
        default:
            throw new \Exception("Tipo de job no soportado: {$jobType}");
    }
    
    // Marcar como completed
    $repo->markCompleted($jobId, $outputData);
    
    if ($isCliOrCron) {
        echo "Job #{$jobId} completado exitosamente\n";
    } else {
        echo json_encode([
            'success' => true, 
            'processed' => true, 
            'job_id' => $jobId,
            'status' => 'completed'
        ]);
    }
    
} catch (\Exception $e) {
    // Marcar como failed
    $repo->markFailed($jobId, $e->getMessage());
    
    if ($isCliOrCron) {
        echo "Job #{$jobId} falló: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        echo json_encode([
            'success' => true,
            'processed' => true,
            'job_id' => $jobId,
            'status' => 'failed',
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Procesar job de tipo podcast
 */
function processPodcastJob(int $jobId, array $inputData, int $userId, BackgroundJobsRepo $repo): array
{
    $sourceType = $inputData['source_type'] ?? 'url';
    $sourceUrl = $inputData['url'] ?? '';
    $sourceText = $inputData['text'] ?? '';
    $sourcePdf = $inputData['pdf_base64'] ?? '';
    
    // === PASO 1: Extraer contenido ===
    $repo->updateProgress($jobId, 'Extrayendo contenido del artículo...');
    
    $extractor = new ContentExtractor();
    $content = null;
    $title = '';
    $source = '';
    
    switch ($sourceType) {
        case 'url':
            if (empty($sourceUrl)) {
                throw new \Exception('URL no proporcionada');
            }
            $result = $extractor->extractFromUrl($sourceUrl);
            if (!$result['success']) {
                throw new \Exception('Error extrayendo URL: ' . $result['error']);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = $result['source'];
            break;
            
        case 'pdf':
            if (empty($sourcePdf)) {
                throw new \Exception('PDF no proporcionado');
            }
            $result = $extractor->extractFromPdf($sourcePdf);
            if (!$result['success']) {
                throw new \Exception('Error extrayendo PDF: ' . $result['error']);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'PDF';
            break;
            
        case 'text':
            if (empty($sourceText)) {
                throw new \Exception('Texto no proporcionado');
            }
            $result = $extractor->extractFromText($sourceText);
            if (!$result['success']) {
                throw new \Exception('Error procesando texto: ' . $result['error']);
            }
            $content = $result['content'];
            $title = $result['title'];
            $source = 'Texto';
            break;
            
        default:
            throw new \Exception("Tipo de fuente no soportado: {$sourceType}");
    }
    
    // === PASO 2: Generar guion ===
    $repo->updateProgress($jobId, 'Generando guion del podcast...');
    
    $scriptGenerator = new PodcastScriptGenerator();
    $scriptResult = $scriptGenerator->generate($content, $title, 15);
    
    if (!$scriptResult['success']) {
        throw new \Exception('Error generando guion: ' . $scriptResult['error']);
    }
    
    $script = $scriptResult['script'];
    $summary = $scriptResult['summary'];
    $speaker1 = $scriptResult['speaker1'];
    $speaker2 = $scriptResult['speaker2'];
    $estimatedDuration = $scriptResult['estimated_duration'];
    $scriptDisplay = PodcastScriptGenerator::cleanAudioTags($script);
    
    // === PASO 3: Generar audio ===
    $repo->updateProgress($jobId, 'Sintetizando audio con IA por bloques...');
    
    $geminiKey = Env::get('GEMINI_API_KEY');
    if (empty($geminiKey)) {
        throw new \Exception('Falta GEMINI_API_KEY para generar audio');
    }
    
    $ttsClient = new GeminiTtsClient();
    $scriptSegments = $scriptGenerator->splitScriptForTts($script);
    $totalSegments = count($scriptSegments);
    $ttsPrompts = [];
    foreach ($scriptSegments as $index => $segment) {
        $ttsPrompts[] = $scriptGenerator->buildTtsPromptForSegment($segment, $index + 1, $totalSegments);
    }
    
    $audioResult = $ttsClient->generateMultiSpeakerSegments(
        $ttsPrompts,
        $speaker1,
        $speaker2,
        'Aoede',
        'Orus',
        350,
        function (int $current, int $total) use ($repo, $jobId) {
            $repo->updateProgress(
                $jobId,
                "Sintetizando audio con IA ({$current}/{$total})...",
                'Generando el podcast por bloques para mantener la calidad de voz.'
            );
        }
    );
    
    if (!$audioResult['success']) {
        throw new \Exception('Error generando audio: ' . $audioResult['error']);
    }
    
    // Convertir PCM a WAV y guardar en storage (fuera de public)
    $pcmData = base64_decode($audioResult['audio_data']);
    $wavData = GeminiTtsClient::pcmToWav($pcmData);
    
    $storageDir = dirname(__DIR__, 3) . '/storage/podcasts';
    if (!is_dir($storageDir)) {
        @mkdir($storageDir, 0775, true);
    }
    $fileName = 'podcast_' . bin2hex(random_bytes(16)) . '.wav';
    $filePath = $storageDir . '/' . $fileName;
    file_put_contents($filePath, $wavData);
    // URL segura que requiere autenticación
    $wavUrl = '/api/files/podcast.php?file=' . urlencode($fileName);
    
    // === PASO 4: Guardar en historial de gestos ===
    $repo->updateProgress($jobId, 'Guardando resultado...');
    
    $gesturesRepo = new GestureExecutionsRepo();
    $executionId = $gesturesRepo->create([
        'user_id' => $userId,
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
            'script_display' => $scriptDisplay,
            'audio_url' => $wavUrl,
            'duration_estimate' => $estimatedDuration,
            'speaker1' => $speaker1,
            'speaker2' => $speaker2,
            'tts_model' => $ttsClient->getModel(),
            'tts_segments' => $audioResult['segments'] ?? $totalSegments
        ],
        'content_type' => 'original',
        'business_line' => null,
        'model' => $ttsClient->getModel()
    ]);
    
    // Registrar en estadísticas (usage_log)
    $usageLog = new UsageLogRepo();
    $usageLog->log($userId, 'gesture', 1, ['gesture_type' => 'podcast-from-article']);
    
    // Devolver datos para output_data del job
    return [
        'execution_id' => $executionId,
        'title' => $title,
        'summary' => $summary,
        'script' => $script,
        'script_display' => $scriptDisplay,
        'speaker1' => $speaker1,
        'speaker2' => $speaker2,
        'audio_url' => $wavUrl,
        'duration_estimate' => $estimatedDuration,
        'tts_model' => $ttsClient->getModel(),
        'tts_segments' => $audioResult['segments'] ?? $totalSegments,
        'source' => $source
    ];
}
