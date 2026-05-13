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
use Sop\AudioTranscriber;

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

// Configure max execution time. Long audio transcription can legitimately run
// for a long time, so the stuck-job window must be longer than this runtime.
$maxRuntime = (int)(Env::get('BACKGROUND_JOB_MAX_SECONDS') ?? 4500);
if ($maxRuntime < 300) {
    $maxRuntime = 300;
}
set_time_limit($maxRuntime);

// Enviar respuesta inmediata al frontend para no bloquear
if (!$isCliOrCron && isset($_SERVER['HTTP_HOST'])) {
    // Desconectar del cliente pero seguir procesando
    ignore_user_abort(true);
    
    header('Content-Type: application/json');
    header('Connection: close');
    
    $response = json_encode(['success' => true, 'processing' => true, 'message' => 'Processing in background']);
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

// Reset only jobs that have clearly exceeded the configured worker runtime.
// A fixed 15-minute reset can restart valid long audio jobs while they are
// still processing.
$stuckWindowMinutes = max(30, (int)ceil($maxRuntime / 60) + 10);
$stuckReset = $repo->resetStuckJobs($stuckWindowMinutes);

// Obtener siguiente job pendiente
$job = $repo->getNextPending();

if (!$job) {
    // No hay jobs pendientes
    if ($isCliOrCron) {
        echo "No pending jobs\n";
        exit(0);
    }
    echo json_encode(['success' => true, 'processed' => false, 'message' => 'No pending jobs']);
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
    $repo->markProcessing($jobId, 'Starting processing...');
    
    $outputData = [];
    
    switch ($jobType) {
        case 'podcast':
            $outputData = processPodcastJob($jobId, $inputData, $userId, $repo);
            break;
        case 'audio-transcribe':
            $outputData = processAudioTranscribeJob($jobId, $inputData, $userId, $repo);
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

/**
 * Process audio transcription job.
 */
function processAudioTranscribeJob(int $jobId, array $inputData, int $userId, BackgroundJobsRepo $repo): array
{
    $filePath = (string)($inputData['file_path'] ?? '');
    $audioMime = (string)($inputData['audio_mime'] ?? '');
    $audioFilename = (string)($inputData['audio_filename'] ?? 'audio');
    $audioSizeMB = (float)($inputData['size_mb'] ?? 0);

    if ($filePath === '' || $audioMime === '') {
        throw new \Exception('Missing audio file path or mime type');
    }

    if (!is_file($filePath)) {
        throw new \Exception('Temporary audio file not found');
    }

    $repo->updateProgress($jobId, 'Transcribing audio...');

    $transcriber = new AudioTranscriber();
    $result = $transcriber->transcribeFile(
        $filePath,
        $audioMime,
        $audioFilename,
        function (array $progress) use ($repo, $jobId) {
            $done = (int)($progress['segments_done'] ?? 0);
            $total = (int)($progress['segments_total'] ?? 0);
            $phase = (string)($progress['phase'] ?? 'transcribing');
            $current = (int)($progress['current_segment'] ?? 0);
            $partialText = (string)($progress['partial_text'] ?? '');
            if ($phase === 'probing') {
                $progressText = 'Analyzing audio duration...';
            } elseif ($phase === 'segmenting') {
                $progressText = 'Preparing audio segments...';
            } elseif ($total > 1) {
                $segmentLabel = max(1, $current ?: min($done + 1, $total));
                $progressText = "Transcribing segment {$segmentLabel}/{$total}...";
            } else {
                $progressText = 'Transcribing audio...';
            }

            $repo->updateProcessingSnapshot($jobId, $progressText, [
                'is_partial' => true,
                'phase' => $phase,
                'segments_done' => $done,
                'segments_total' => $total,
                'current_segment' => $current,
                'segment_seconds' => (int)($progress['segment_seconds'] ?? 0),
                'segmented' => (bool)($progress['segmented'] ?? false),
                'partial_transcription' => $partialText,
            ]);
        }
    );

    if (!$result['success']) {
        throw new \Exception((string)($result['error'] ?? 'Transcription failed'));
    }

    $transcription = trim((string)($result['text'] ?? ''));
    if ($transcription === '') {
        throw new \Exception('Transcription is empty');
    }

    $durationEstimate = $result['duration_estimate'] ?? null;

    $title = mb_substr(preg_replace('/\s+/', ' ', $transcription), 0, 60);
    if (mb_strlen($transcription) > 60) {
        $title .= '...';
    }
    if (trim($title) === '') {
        $title = 'Transcription - ' . date('Y-m-d H:i');
    }

    $repo->updateProcessingSnapshot($jobId, 'Saving transcription...', [
        'is_partial' => true,
        'phase' => 'saving',
        'partial_transcription' => mb_substr($transcription, 0, 4000),
    ]);

    $gesturesRepo = new GestureExecutionsRepo();
    $executionId = $gesturesRepo->create([
        'user_id' => $userId,
        'gesture_type' => 'audio-transcriber',
        'title' => $title,
        'input_data' => [
            'filename' => $audioFilename,
            'mime_type' => $audioMime,
            'size_mb' => round($audioSizeMB, 2),
            'duration_estimate' => $durationEstimate,
        ],
        'output_content' => $transcription,
        'output_data' => [
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription),
            'duration_estimate' => $durationEstimate,
        ],
        'content_type' => 'transcription',
        'business_line' => null,
        'model' => 'gemini-2.5-flash',
    ]);

    $usageLog = new UsageLogRepo();
    $usageLog->log($userId, 'gesture', 1, ['gesture_type' => 'audio-transcriber']);

    @unlink($filePath);

    return [
        'execution_id' => $executionId,
        'title' => $title,
        'transcription' => $transcription,
        'metadata' => [
            'filename' => $audioFilename,
            'duration_estimate' => $durationEstimate,
            'word_count' => str_word_count($transcription),
            'char_count' => mb_strlen($transcription),
            'segmented' => (bool)($result['metadata']['segmented'] ?? false),
            'segment_count' => (int)($result['metadata']['segment_count'] ?? 1),
            'segment_seconds' => (int)($result['metadata']['segment_seconds'] ?? 0),
        ],
    ];
}
