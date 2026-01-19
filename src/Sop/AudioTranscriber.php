<?php

namespace Sop;

use App\Env;

/**
 * Transcriptor de audio usando Google Gemini API directamente
 * Usa File API para archivos grandes (hasta 2GB)
 * Soporta mp3, wav, m4a, webm, ogg
 */
class AudioTranscriber
{
    private string $apiKey;
    private string $model = 'gemini-2.0-flash';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';
    private string $uploadUrl = 'https://generativelanguage.googleapis.com/upload/v1beta/files';
    
    public function __construct(?string $apiKey = null)
    {
        // Usar GEMINI_API_KEY directamente (no OpenRouter)
        $this->apiKey = $apiKey ?? (Env::get('GEMINI_API_KEY') ?? '');
    }
    
    private function debugLog(string $message): void
    {
        // Debug eliminado
    }
    
    /**
     * Transcribe audio desde base64 usando Gemini API directamente
     * 
     * @param string $base64Data Audio en base64
     * @param string $mimeType Tipo MIME del audio
     * @param string $filename Nombre del archivo (opcional)
     * @return array ['success' => bool, 'text' => string, 'error' => string|null]
     */
    public function transcribe(string $base64Data, string $mimeType, string $filename = 'audio'): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Falta GEMINI_API_KEY'];
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
        
        // Decodificar audio
        $audioBytes = base64_decode($base64Data);
        $audioSizeBytes = strlen($audioBytes);
        $audioSizeMB = $audioSizeBytes / (1024 * 1024);
        
        if ($audioSizeMB > 50) {
            return ['success' => false, 'error' => "El audio es demasiado grande (" . round($audioSizeMB, 1) . "MB). Máximo 50MB."];
        }
        
        $this->debugLog("Tamaño audio: {$audioSizeMB} MB, MIME: {$mimeType}");
        
        // Paso 1: Subir archivo a Gemini File API
        $uploadResult = $this->uploadFile($audioBytes, $mimeType, $filename);
        unset($audioBytes); // Liberar memoria
        
        if (!$uploadResult['success']) {
            return $uploadResult;
        }
        
        $fileUri = $uploadResult['uri'];
        $this->debugLog("Archivo subido: {$fileUri}");
        
        // Paso 2: Generar transcripción con el archivo
        $prompt = 'Analiza este audio y realiza una transcripción fiel en español. 
ES MUY IMPORTANTE que identifiques a los diferentes hablantes si hay más de uno.
Usa el formato:
Hablante 1: [Texto]
Hablante 2: [Texto]

Si puedes deducir el rol de cada uno por el contexto (ej. "Entrevistador", "Cliente", "Soporte"), usa ese nombre descriptivo en lugar de "Hablante X".
Si solo hay un hablante claro, puedes omitir la etiqueta del nombre.
Devuelve SOLO la transcripción textual, sin introducción ni explicaciones adicionales. Mantén los párrafos y pausas naturales.';

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        [
                            'fileData' => [
                                'mimeType' => $mimeType,
                                'fileUri' => $fileUri
                            ]
                        ],
                        [
                            'text' => $prompt
                        ]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.1,
                'maxOutputTokens' => 16384
            ]
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
        
        $this->debugLog("Enviando a Gemini API...");
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->debugLog("Respuesta Gemini: HTTP {$httpCode}");
        
        if ($curlError) {
            $this->debugLog("ERROR cURL: " . $curlError);
            return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
        }
        
        if (!$response) {
            $this->debugLog("ERROR: Respuesta vacía");
            return ['success' => false, 'error' => 'No se recibió respuesta de Gemini'];
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
        
        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
        
        if (empty($text)) {
            $this->debugLog("ERROR: Transcripción vacía");
            return ['success' => false, 'error' => 'Transcripción vacía'];
        }
        
        $this->debugLog("Transcripción exitosa: " . strlen($text) . " caracteres");
        
        return [
            'success' => true,
            'text' => trim($text),
            'duration_estimate' => $this->estimateDuration($audioSizeBytes, $mimeType)
        ];
    }
    
    /**
     * Sube archivo a Gemini File API
     */
    private function uploadFile(string $fileBytes, string $mimeType, string $filename): array
    {
        $this->debugLog("Subiendo archivo a Gemini File API...");
        
        // Usar resumable upload para archivos grandes
        $metadata = json_encode(['file' => ['displayName' => $filename]]);
        
        // Paso 1: Iniciar upload resumable
        $ch = curl_init("{$this->uploadUrl}?key={$this->apiKey}");
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'X-Goog-Upload-Protocol: resumable',
                'X-Goog-Upload-Command: start',
                'X-Goog-Upload-Header-Content-Length: ' . strlen($fileBytes),
                'X-Goog-Upload-Header-Content-Type: ' . $mimeType,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => $metadata,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 60,
        ]);
        
        $response = curl_exec($ch);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headerSize);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            $this->debugLog("ERROR inicio upload: HTTP {$httpCode}");
            return ['success' => false, 'error' => "Error al iniciar upload: HTTP {$httpCode}"];
        }
        
        // Extraer URL de upload
        preg_match('/x-goog-upload-url:\s*(.+)/i', $headers, $matches);
        $uploadUrl = trim($matches[1] ?? '');
        
        if (empty($uploadUrl)) {
            $this->debugLog("ERROR: No se obtuvo URL de upload");
            return ['success' => false, 'error' => 'No se obtuvo URL de upload'];
        }
        
        $this->debugLog("URL de upload obtenida");
        
        // Paso 2: Subir bytes
        $ch = curl_init($uploadUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Length: ' . strlen($fileBytes),
                'X-Goog-Upload-Offset: 0',
                'X-Goog-Upload-Command: upload, finalize'
            ],
            CURLOPT_POSTFIELDS => $fileBytes,
            CURLOPT_TIMEOUT => 300, // 5 min para upload
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) {
            $this->debugLog("ERROR cURL upload: " . $curlError);
            return ['success' => false, 'error' => 'Error de conexión en upload: ' . $curlError];
        }
        
        if ($httpCode !== 200) {
            $this->debugLog("ERROR upload: HTTP {$httpCode} - " . substr($response, 0, 500));
            return ['success' => false, 'error' => "Error en upload: HTTP {$httpCode}"];
        }
        
        $data = json_decode($response, true);
        $fileUri = $data['file']['uri'] ?? null;
        
        if (empty($fileUri)) {
            $this->debugLog("ERROR: No se obtuvo URI del archivo");
            return ['success' => false, 'error' => 'No se obtuvo URI del archivo subido'];
        }
        
        $this->debugLog("Archivo subido exitosamente: {$fileUri}");
        
        return ['success' => true, 'uri' => $fileUri];
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
            return (int)round($seconds) . ' segundos';
        } elseif ($seconds < 3600) {
            $mins = (int)floor($seconds / 60);
            $secs = (int)round(fmod($seconds, 60));
            return "{$mins}:{$secs} minutos";
        } else {
            $hours = (int)floor($seconds / 3600);
            $mins = (int)floor(fmod($seconds, 3600) / 60);
            return "{$hours}h {$mins}min";
        }
    }
}
