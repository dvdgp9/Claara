<?php

namespace Sop;

use App\Env;

class AudioTranscriber
{
    private string $apiKey;
    private string $model = 'gemini-2.5-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private string $uploadUrl = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
    private string $ffmpegPath;
    private string $ffprobePath;
    private int $segmentThresholdSeconds = 600; // 10 minutes
    private int $segmentSeconds = 180; // 3 minutes
    private int $minSegmentSeconds = 45;
    private int $probeTimeoutSeconds = 20;
    private int $segmentTimeoutSeconds = 600;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (Env::get('GEMINI_API_KEY') ?? '');
        $this->ffmpegPath = (string)(Env::get('FFMPEG_PATH') ?? '/usr/bin/ffmpeg');
        $this->ffprobePath = (string)(Env::get('FFPROBE_PATH') ?? '/usr/bin/ffprobe');
    }

    public function transcribe(string $base64Data, string $mimeType, string $filename = 'audio'): array
    {
        if ($base64Data === '') {
            return ['success' => false, 'error' => 'Missing audio payload'];
        }

        $tmpDir = dirname(__DIR__, 2) . '/storage/transcribe-tmp';
        if (!is_dir($tmpDir) && !@mkdir($tmpDir, 0775, true)) {
            return ['success' => false, 'error' => 'Could not create temporary directory'];
        }

        $ext = $this->extensionFromMime($mimeType);
        $tmpFile = $tmpDir . '/inline_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $bytes = base64_decode($base64Data, true);
        if ($bytes === false) {
            return ['success' => false, 'error' => 'Invalid base64 audio payload'];
        }

        if (file_put_contents($tmpFile, $bytes) === false) {
            return ['success' => false, 'error' => 'Could not write temporary audio file'];
        }

        try {
            return $this->transcribeFile($tmpFile, $mimeType, $filename);
        } finally {
            @unlink($tmpFile);
        }
    }

    public function transcribeFile(string $filePath, string $mimeType, string $filename = 'audio', ?callable $onProgress = null): array
    {
        if ($this->apiKey === '') {
            return ['success' => false, 'error' => 'Missing GEMINI_API_KEY'];
        }
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['success' => false, 'error' => 'Audio file not found or not readable'];
        }
        if (!in_array($mimeType, $this->validAudioMimeTypes(), true)) {
            return ['success' => false, 'error' => 'Unsupported audio type: ' . $mimeType];
        }

        $fileSize = filesize($filePath) ?: 0;
        $fileSizeMB = $fileSize / (1024 * 1024);
        if ($fileSizeMB > 50) {
            return ['success' => false, 'error' => 'Audio file exceeds 50MB limit'];
        }

        $this->notifyProgress($onProgress, [
            'phase' => 'probing',
            'segments_done' => 0,
            'segments_total' => 0,
            'segmented' => false,
            'segment_seconds' => $this->segmentSeconds,
            'partial_text' => '',
        ]);

        $durationSeconds = $this->probeDurationSeconds($filePath);
        $segments = [];
        $segmentTmpDir = null;

        $shouldSegment = $durationSeconds !== null && $durationSeconds >= $this->segmentThresholdSeconds;
        if ($durationSeconds === null && $fileSizeMB >= 8) {
            $shouldSegment = true;
        }

        if ($shouldSegment) {
            $segmentTmpDir = dirname(__DIR__, 2) . '/storage/transcribe-segments/' . bin2hex(random_bytes(8));
            $this->notifyProgress($onProgress, [
                'phase' => 'segmenting',
                'segments_done' => 0,
                'segments_total' => $durationSeconds !== null
                    ? max(1, (int)ceil($durationSeconds / $this->segmentSeconds))
                    : 0,
                'segmented' => true,
                'segment_seconds' => $this->segmentSeconds,
                'partial_text' => '',
            ]);
            $segments = $this->buildSegments($filePath, $segmentTmpDir);
            if (!$segments['success']) {
                return $segments;
            }
            $segments = $segments['segments'];
        } else {
            $segments = [[
                'path' => $filePath,
                'mime' => $mimeType,
                'filename' => $filename,
                'is_temp' => false,
            ]];
        }

        $outputParts = [];
        $segmentsTotal = count($segments);

        foreach ($segments as $idx => $segment) {
            $this->notifyProgress($onProgress, [
                'segments_done' => $idx,
                'segments_total' => $segmentsTotal,
                'segmented' => $segmentsTotal > 1,
                'segment_seconds' => $this->segmentSeconds,
                'current_segment' => $idx + 1,
                'phase' => 'transcribing',
                'partial_text' => implode("\n\n", $outputParts),
            ]);

            $textResult = $this->transcribeBytes(file_get_contents($segment['path']) ?: '', $segment['mime'], $segment['filename']);

            if (!$textResult['success']) {
                if ($segmentTmpDir) {
                    $this->cleanupDirectory($segmentTmpDir);
                }
                return $textResult;
            }

            $segmentText = trim((string)($textResult['text'] ?? ''));
            if ($segmentText !== '' && $segmentText !== '[no speech]') {
                $outputParts[] = $segmentText;
            }

            $this->notifyProgress($onProgress, [
                'segments_done' => $idx + 1,
                'segments_total' => $segmentsTotal,
                'segmented' => $segmentsTotal > 1,
                'segment_seconds' => $this->segmentSeconds,
                'current_segment' => $idx + 1,
                'phase' => 'transcribing',
                'partial_text' => implode("\n\n", $outputParts),
            ]);
        }

        if ($segmentTmpDir) {
            $this->cleanupDirectory($segmentTmpDir);
        }

        $finalText = trim(implode("\n\n", $outputParts));
        if ($finalText === '') {
            return ['success' => false, 'error' => 'Transcription is empty'];
        }

        return [
            'success' => true,
            'text' => $finalText,
            'duration_estimate' => $durationSeconds !== null ? $this->formatDurationSeconds($durationSeconds) : null,
            'metadata' => [
                'provider' => 'gemini',
                'model' => $this->model,
                'audio_size_mb' => round($fileSizeMB, 2),
                'segmented' => $segmentsTotal > 1,
                'segment_count' => $segmentsTotal,
                'segment_seconds' => $this->segmentSeconds,
            ],
        ];
    }

    private function notifyProgress(?callable $onProgress, array $progress): void
    {
        if (!$onProgress) {
            return;
        }
        $onProgress($progress);
    }

    private function transcribeBytes(string $audioBytes, string $mimeType, string $filename): array
    {
        if ($audioBytes === '') {
            return ['success' => false, 'error' => 'Empty audio bytes'];
        }

        $uploadResult = $this->uploadFile($audioBytes, $mimeType, $filename);
        if (!$uploadResult['success']) {
            return $uploadResult;
        }

        $prompt = implode("\n", [
            'Transcribe this audio faithfully and chronologically.',
            'Do not summarize and do not add commentary.',
            'Output must always be in speaker turns with labels:',
            '- If names are clear, use names.',
            '- If roles are clear, use role labels.',
            '- Otherwise use Speaker 1:, Speaker 2:, Speaker 3:, etc.',
            '- Even with one speaker, keep the Speaker 1: label.',
            'If no intelligible human speech exists in the whole segment, return exactly: [no speech]',
            'Keep the original language of the audio content.',
        ]);

        $payload = [
            'contents' => [[
                'parts' => [
                    [
                        'fileData' => [
                            'mimeType' => $mimeType,
                            'fileUri' => $uploadResult['uri'],
                        ],
                    ],
                    ['text' => $prompt],
                ],
            ]],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 65536,
            ],
        ];

        $url = "{$this->baseUrl}/models/{$this->model}:generateContent?key={$this->apiKey}";
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            return ['success' => false, 'error' => 'Gemini connection error: ' . $curlError];
        }
        if (!$response) {
            return ['success' => false, 'error' => 'No response from Gemini'];
        }

        $data = json_decode($response, true);
        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Unknown API error';
            return ['success' => false, 'error' => 'Gemini API error: ' . $errorMsg];
        }
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Gemini HTTP error {$httpCode}"];
        }

        $text = trim((string)($data['candidates'][0]['content']['parts'][0]['text'] ?? ''));
        if ($text === '') {
            return ['success' => false, 'error' => 'Empty transcription response'];
        }

        return ['success' => true, 'text' => $text];
    }

    private function uploadFile(string $fileBytes, string $mimeType, string $filename): array
    {
        $metadata = json_encode(['file' => ['displayName' => $filename]]);
        $start = curl_init("{$this->uploadUrl}?key={$this->apiKey}");
        curl_setopt_array($start, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'X-Goog-Upload-Protocol: resumable',
                'X-Goog-Upload-Command: start',
                'X-Goog-Upload-Header-Content-Length: ' . strlen($fileBytes),
                'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $metadata,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
        ]);

        $startResponse = curl_exec($start);
        $headerSize = curl_getinfo($start, CURLINFO_HEADER_SIZE);
        $headers = substr((string)$startResponse, 0, $headerSize);
        $startHttpCode = curl_getinfo($start, CURLINFO_HTTP_CODE);
        curl_close($start);

        if ($startHttpCode !== 200) {
            return ['success' => false, 'error' => "Upload init failed with HTTP {$startHttpCode}"];
        }

        preg_match('/x-goog-upload-url:\s*(.+)/i', $headers, $matches);
        $uploadUrl = trim((string)($matches[1] ?? ''));
        if ($uploadUrl === '') {
            return ['success' => false, 'error' => 'Could not obtain upload URL'];
        }

        $upload = curl_init($uploadUrl);
        curl_setopt_array($upload, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Length: ' . strlen($fileBytes),
                'X-Goog-Upload-Offset: 0',
                'X-Goog-Upload-Command: upload, finalize',
            ],
            CURLOPT_POSTFIELDS => $fileBytes,
            CURLOPT_TIMEOUT => 300,
        ]);

        $uploadResponse = curl_exec($upload);
        $uploadError = curl_error($upload);
        $uploadHttpCode = curl_getinfo($upload, CURLINFO_HTTP_CODE);
        curl_close($upload);

        if ($uploadError) {
            return ['success' => false, 'error' => 'Upload connection error: ' . $uploadError];
        }
        if ($uploadHttpCode !== 200) {
            return ['success' => false, 'error' => "Upload failed with HTTP {$uploadHttpCode}"];
        }

        $data = json_decode((string)$uploadResponse, true);
        $fileUri = $data['file']['uri'] ?? null;
        if (!$fileUri) {
            return ['success' => false, 'error' => 'Upload did not return a file URI'];
        }

        return ['success' => true, 'uri' => $fileUri];
    }

    private function validAudioMimeTypes(): array
    {
        return [
            'audio/mpeg',
            'audio/mp3',
            'audio/wav',
            'audio/wave',
            'audio/x-wav',
            'audio/mp4',
            'audio/m4a',
            'audio/x-m4a',
            'audio/webm',
            'audio/ogg',
        ];
    }

    private function probeDurationSeconds(string $filePath): ?int
    {
        if (!is_file($this->ffprobePath)) {
            return null;
        }

        $cmd = sprintf(
            '%s -v error -show_entries format=duration -of default=nokey=1:noprint_wrappers=1 %s',
            escapeshellarg($this->ffprobePath),
            escapeshellarg($filePath)
        );
        $result = $this->runCommand($cmd, $this->probeTimeoutSeconds);
        if ($result['exit_code'] !== 0 || trim($result['stdout']) === '') {
            return null;
        }

        $duration = (float)trim(strtok($result['stdout'], "\n"));
        if ($duration <= 0) {
            return null;
        }
        return (int)round($duration);
    }

    private function buildSegments(string $sourcePath, string $segmentDir): array
    {
        if (!is_file($this->ffmpegPath)) {
            return ['success' => false, 'error' => 'ffmpeg not found'];
        }
        if (!is_dir($segmentDir) && !@mkdir($segmentDir, 0775, true)) {
            return ['success' => false, 'error' => 'Could not create segment directory'];
        }

        $pattern = $segmentDir . '/segment_%03d.m4a';
        $cmd = sprintf(
            '%s -hide_banner -loglevel error -y -i %s -vn -ac 1 -ar 16000 -c:a aac -b:a 48k -f segment -segment_time %d -reset_timestamps 1 %s',
            escapeshellarg($this->ffmpegPath),
            escapeshellarg($sourcePath),
            $this->segmentSeconds,
            escapeshellarg($pattern)
        );

        $result = $this->runCommand($cmd, $this->segmentTimeoutSeconds);
        if ($result['exit_code'] !== 0) {
            $this->cleanupDirectory($segmentDir);
            $message = trim($result['stderr'] . ' ' . $result['stdout']);
            if ($result['timed_out']) {
                $message = 'ffmpeg segmentation timed out';
            }
            return ['success' => false, 'error' => 'ffmpeg segmentation failed: ' . $message];
        }

        $segmentFiles = glob($segmentDir . '/segment_*.m4a');
        if (!$segmentFiles) {
            $this->cleanupDirectory($segmentDir);
            return ['success' => false, 'error' => 'No segments created'];
        }
        sort($segmentFiles);

        $segments = [];
        foreach ($segmentFiles as $index => $segmentPath) {
            $segments[] = [
                'path' => $segmentPath,
                'mime' => 'audio/mp4',
                'filename' => sprintf('segment_%03d.m4a', $index + 1),
                'is_temp' => true,
            ];
        }

        return ['success' => true, 'segments' => $segments];
    }

    private function runCommand(string $cmd, int $timeoutSeconds): array
    {
        if (!function_exists('proc_open')) {
            return [
                'exit_code' => 127,
                'stdout' => '',
                'stderr' => 'proc_open is disabled',
                'timed_out' => false,
            ];
        }

        $pipes = [];
        $process = proc_open($cmd, [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);

        if (!is_resource($process)) {
            return [
                'exit_code' => 127,
                'stdout' => '',
                'stderr' => 'Could not start command',
                'timed_out' => false,
            ];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $deadline = time() + max(1, $timeoutSeconds);
        $timedOut = false;

        while (true) {
            $status = proc_get_status($process);
            $stdout .= stream_get_contents($pipes[1]) ?: '';
            $stderr .= stream_get_contents($pipes[2]) ?: '';

            if (!$status['running']) {
                break;
            }
            if (time() >= $deadline) {
                $timedOut = true;
                proc_terminate($process, 9);
                break;
            }
            usleep(100000);
        }

        $stdout .= stream_get_contents($pipes[1]) ?: '';
        $stderr .= stream_get_contents($pipes[2]) ?: '';
        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($timedOut) {
            $exitCode = 124;
        }

        return [
            'exit_code' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
            'timed_out' => $timedOut,
        ];
    }

    private function cleanupDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = glob($dir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($dir);
    }

    private function extensionFromMime(string $mimeType): string
    {
        return match ($mimeType) {
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/wav', 'audio/wave', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/webm' => 'webm',
            'audio/ogg' => 'ogg',
            default => 'audio',
        };
    }

    private function formatDurationSeconds(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' sec';
        }
        if ($seconds < 3600) {
            $mins = (int)floor($seconds / 60);
            $secs = $seconds % 60;
            return sprintf('%d:%02d min', $mins, $secs);
        }
        $hours = (int)floor($seconds / 3600);
        $mins = (int)floor(($seconds % 3600) / 60);
        return sprintf('%dh %02dm', $hours, $mins);
    }
}
