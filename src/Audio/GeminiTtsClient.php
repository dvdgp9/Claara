<?php
namespace Audio;

use App\Env;

/**
 * Cliente para Gemini TTS (Text-to-Speech)
 * 
 * Usa la API directa de Google AI Studio para generar audio
 * con el modelo gemini-2.5-flash-preview-tts
 * 
 * Soporta multi-speaker (hasta 2 voces) para diálogos/podcasts
 */
class GeminiTtsClient
{
    private string $apiKey;
    private string $model = 'gemini-2.5-flash-preview-tts';
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    // Voces disponibles (30 en total)
    // Ver todas en: https://aistudio.google.com/generate-speech
    public const VOICES = [
        'Puck', 'Charon', 'Kore', 'Fenrir', 'Aoede', 'Leda', 'Orus', 'Zephyr',
        'Kari', 'Perseus', 'Mira', 'Stella', 'Aurora', 'Luna', 'Sol', 'Nova',
        'Orion', 'Phoenix', 'Sirius', 'Vega', 'Callisto', 'Titan', 'Io', 'Europa',
        'Ganymede', 'Rhea', 'Dione', 'Tethys', 'Enceladus', 'Mimas'
    ];

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? Env::get('GEMINI_API_KEY', '');
    }

    /**
     * Genera audio de un diálogo con dos voces
     * 
     * @param string $script El guion con formato "Speaker1: texto\nSpeaker2: texto..."
     * @param string $speaker1Name Nombre del primer speaker en el guion
     * @param string $speaker2Name Nombre del segundo speaker en el guion
     * @param string $voice1 Voz para speaker1 (ej: 'Kore')
     * @param string $voice2 Voz para speaker2 (ej: 'Puck')
     * @return array ['success' => bool, 'audio_data' => base64, 'error' => string|null]
     */
    public function generateMultiSpeaker(
        string $script,
        string $speaker1Name = 'Ana',
        string $speaker2Name = 'Carlos',
        string $voice1 = 'Aoede',
        string $voice2 = 'Orus'
    ): array {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Falta GEMINI_API_KEY en .env'];
        }

        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $script]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'multiSpeakerVoiceConfig' => [
                        'speakerVoiceConfigs' => [
                            [
                                'speaker' => $speaker1Name,
                                'voiceConfig' => [
                                    'prebuiltVoiceConfig' => [
                                        'voiceName' => $voice1
                                    ]
                                ]
                            ],
                            [
                                'speaker' => $speaker2Name,
                                'voiceConfig' => [
                                    'prebuiltVoiceConfig' => [
                                        'voiceName' => $voice2
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 300, // 5 minutos para audios largos
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err) {
            return ['success' => false, 'error' => 'Error de conexión: ' . $err];
        }

        $data = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $status);
            return ['success' => false, 'error' => 'Error de Gemini TTS: ' . $msg];
        }

        $audioBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

        if (!$audioBase64) {
            return ['success' => false, 'error' => 'No se recibió audio en la respuesta'];
        }

        return [
            'success' => true,
            'audio_data' => $audioBase64,
            'mime_type' => 'audio/wav',
            'sample_rate' => 24000,
            'channels' => 1,
            'bit_depth' => 16
        ];
    }

    /**
     * Genera audio con una sola voz
     */
    public function generateSingleSpeaker(string $text, string $voice = 'Aoede'): array
    {
        if (!$this->apiKey) {
            return ['success' => false, 'error' => 'Falta GEMINI_API_KEY en .env'];
        }

        $url = $this->baseUrl . $this->model . ':generateContent?key=' . $this->apiKey;

        $payload = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $text]
                    ]
                ]
            ],
            'generationConfig' => [
                'responseModalities' => ['AUDIO'],
                'speechConfig' => [
                    'voiceConfig' => [
                        'prebuiltVoiceConfig' => [
                            'voiceName' => $voice
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT => 180,
        ]);

        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err) {
            return ['success' => false, 'error' => 'Error de conexión: ' . $err];
        }

        $data = json_decode($raw, true);

        if ($status < 200 || $status >= 300) {
            $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP ' . $status);
            return ['success' => false, 'error' => 'Error de Gemini TTS: ' . $msg];
        }

        $audioBase64 = $data['candidates'][0]['content']['parts'][0]['inlineData']['data'] ?? null;

        if (!$audioBase64) {
            return ['success' => false, 'error' => 'No se recibió audio en la respuesta'];
        }

        return [
            'success' => true,
            'audio_data' => $audioBase64,
            'mime_type' => 'audio/wav',
            'sample_rate' => 24000,
            'channels' => 1,
            'bit_depth' => 16
        ];
    }

    /**
     * Convierte audio PCM raw a formato WAV con headers correctos
     */
    public static function pcmToWav(string $pcmData, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $dataSize = strlen($pcmData);
        $byteRate = $sampleRate * $channels * ($bitsPerSample / 8);
        $blockAlign = $channels * ($bitsPerSample / 8);

        $header = pack('A4', 'RIFF');
        $header .= pack('V', 36 + $dataSize);
        $header .= pack('A4', 'WAVE');
        $header .= pack('A4', 'fmt ');
        $header .= pack('V', 16);
        $header .= pack('v', 1); // PCM
        $header .= pack('v', $channels);
        $header .= pack('V', $sampleRate);
        $header .= pack('V', $byteRate);
        $header .= pack('v', $blockAlign);
        $header .= pack('v', $bitsPerSample);
        $header .= pack('A4', 'data');
        $header .= pack('V', $dataSize);

        return $header . $pcmData;
    }
}
