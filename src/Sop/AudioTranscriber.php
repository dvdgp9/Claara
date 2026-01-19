<?php

namespace Sop;

use App\Env;

/**
 * Transcriptor de audio usando Gemini multimodal via OpenRouter
 * Soporta mp3, wav, m4a, webm, ogg
 */
class AudioTranscriber
{
    private string $apiKey;
    private string $model = 'google/gemini-3-flash-preview';
    private string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
    
    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? (Env::get('OPENROUTER_API_KEY') ?? '');
    }
    
    private function debugLog(string $message): void
    {
        $logFile = __DIR__ . '/../../storage/logs/transcribe_debug.log';
        $dir = dirname($logFile);
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        @file_put_contents($logFile, date('[Y-m-d H:i:s] [AudioTranscriber] ') . $message . "\n", FILE_APPEND);
    }
    
    /**
     * Transcribe audio desde base64
     * 
     * @param string $base64Data Audio en base64
     * @param string $mimeType Tipo MIME del audio
     * @param string $filename Nombre del archivo (opcional)
     * @return array ['success' => bool, 'text' => string, 'error' => string|null]
     */
    public function transcribe(string $base64Data, string $mimeType, string $filename = 'audio'): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Falta OPENROUTER_API_KEY'];
        }
        
        // Validar tipo de audio
        $validTypes = [
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
        
        if (!in_array($mimeType, $validTypes)) {
            return ['success' => false, 'error' => 'Tipo de audio no soportado: ' . $mimeType];
        }
        
        // Verificar tamaño (límite ~50MB)
        $audioSizeBytes = strlen(base64_decode($base64Data));
        $audioSizeMB = $audioSizeBytes / (1024 * 1024);
        
        if ($audioSizeMB > 50) {
            return ['success' => false, 'error' => "El audio es demasiado grande (" . round($audioSizeMB, 1) . "MB). Máximo 50MB."];
        }

        // Determinar formato para el payload nativo (ej: 'mp3', 'wav')
        $format = str_replace(['audio/', 'x-'], '', $mimeType);
        if ($format === 'mpeg') $format = 'mp3';

        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_audio',
                            'input_audio' => [
                                'data' => $base64Data,
                                'format' => $format
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Analiza este audio y realiza una transcripción fiel en español. 
                                       ES MUY IMPORTANTE que identifiques a los diferentes hablantes si hay más de uno.
                                       Usa el formato:
                                       Hablante 1: [Texto]
                                       Hablante 2: [Texto]
                                       
                                       Si puedes deducir el rol de cada uno por el contexto (ej. "Entrevistador", "Cliente", "Soporte"), usa ese nombre descriptivo en lugar de "Hablante X".
                                       Si solo hay un hablante claro, puedes omitir la etiqueta del nombre.
                                       Devuelve SOLO la transcripción textual, sin introducción ni explicaciones adicionales. Mantén los párrafos y pausas naturales.'
                        ]
                    ]
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 16384
        ];
        
        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $payloadSizeMB = round(strlen($jsonPayload) / 1024 / 1024, 2);
        unset($payload); // Liberar memoria
        
        $this->debugLog("Payload JSON preparado: {$payloadSizeMB} MB");
        $this->debugLog("Memoria después de JSON: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB");
        
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . (Env::get('APP_URL') ?? 'https://ebonia.es'),
                'X-Title: Ebonia SOP Generator'
            ],
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 600, // 10 minutos para audio largo
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);
        
        $this->debugLog("Enviando a OpenRouter...");
        $response = curl_exec($ch);
        unset($jsonPayload); // Liberar memoria
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->debugLog("Respuesta OpenRouter: HTTP {$httpCode}");
        
        if ($curlError) {
            $this->debugLog("ERROR cURL: " . $curlError);
            return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
        }
        
        if (!$response) {
            $this->debugLog("ERROR: Respuesta vacía");
            return ['success' => false, 'error' => 'No se recibió respuesta de OpenRouter'];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Error desconocido';
            $this->debugLog("ERROR API: " . $errorMsg);
            return ['success' => false, 'error' => 'Error de API: ' . $errorMsg];
        }
        
        if ($httpCode !== 200) {
            $this->debugLog("ERROR HTTP: " . $httpCode . " - " . substr($response, 0, 500));
            return ['success' => false, 'error' => "Error HTTP {$httpCode}"];
        }
        
        $text = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($text)) {
            return ['success' => false, 'error' => 'Transcripción vacía'];
        }
        
        return [
            'success' => true,
            'text' => trim($text),
            'duration_estimate' => $this->estimateDuration($audioSizeBytes, $mimeType)
        ];
    }
    
    /**
     * Estima la duración del audio basándose en el tamaño
     */
    private function estimateDuration(int $bytes, string $mimeType): ?string
    {
        // Estimaciones aproximadas de bitrate por formato
        $bitratesKbps = [
            'audio/mpeg' => 128,
            'audio/mp3' => 128,
            'audio/wav' => 1411, // CD quality
            'audio/wave' => 1411,
            'audio/x-wav' => 1411,
            'audio/mp4' => 128,
            'audio/m4a' => 128,
            'audio/x-m4a' => 128,
            'audio/webm' => 96,
            'audio/ogg' => 96,
        ];
        
        $bitrate = $bitratesKbps[$mimeType] ?? 128;
        $seconds = ($bytes * 8) / ($bitrate * 1000);
        
        if ($seconds < 60) {
            return round($seconds) . ' segundos';
        } elseif ($seconds < 3600) {
            $mins = floor($seconds / 60);
            $secs = round($seconds % 60);
            return "{$mins}:{$secs} minutos";
        } else {
            $hours = floor($seconds / 3600);
            $mins = floor(($seconds % 3600) / 60);
            return "{$hours}h {$mins}min";
        }
    }
}
