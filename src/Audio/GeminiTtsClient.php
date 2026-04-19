<?php
namespace Audio;

use App\Env;

/**
 * Cliente para Gemini TTS (Text-to-Speech)
 * 
 * Usa la API directa de Google AI Studio para generar audio
 * con el modelo Gemini 3.1 Flash TTS
 * 
 * Soporta multi-speaker (hasta 2 voces) para diálogos/podcasts
 */
class GeminiTtsClient
{
    public const MODEL = 'gemini-3.1-flash-tts-preview';

    private string $apiKey;
    private string $model = self::MODEL;
    private string $baseUrl = 'https://generativelanguage.googleapis.com/v1beta/models/';
    
    // Voces oficiales de Gemini TTS. El acento y estilo se controlan con el prompt.
    public const VOICES = [
        'Zephyr', 'Puck', 'Charon', 'Kore', 'Fenrir', 'Leda', 'Orus', 'Aoede',
        'Callirrhoe', 'Autonoe', 'Enceladus', 'Iapetus', 'Umbriel', 'Algieba',
        'Despina', 'Erinome', 'Algenib', 'Rasalgethi', 'Laomedeia', 'Achernar',
        'Alnilam', 'Schedar', 'Gacrux', 'Pulcherrima', 'Achird', 'Zubenelgenubi',
        'Vindemiatrix', 'Sadachbia', 'Sadaltager', 'Sulafat'
    ];

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? Env::get('GEMINI_API_KEY', '');
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * Genera audio multi-speaker por segmentos y concatena PCM para reducir
     * la deriva de calidad en podcasts largos.
     *
     * @param array<int, string> $prompts Prompts TTS ya preparados para cada segmento
     * @param callable|null $onSegment Callback fn(int $current, int $total): void
     */
    public function generateMultiSpeakerSegments(
        array $prompts,
        string $speaker1Name = 'Ana',
        string $speaker2Name = 'Carlos',
        string $voice1 = 'Aoede',
        string $voice2 = 'Orus',
        int $pauseMs = 350,
        ?callable $onSegment = null
    ): array {
        $prompts = array_values(array_filter(array_map('trim', $prompts)));
        if (empty($prompts)) {
            return ['success' => false, 'error' => 'No hay segmentos para generar audio'];
        }

        if (count($prompts) === 1) {
            return $this->generateMultiSpeaker($prompts[0], $speaker1Name, $speaker2Name, $voice1, $voice2);
        }

        $pcmParts = [];
        $total = count($prompts);
        $pause = self::silentPcm($pauseMs);

        foreach ($prompts as $index => $prompt) {
            if ($onSegment) {
                $onSegment($index + 1, $total);
            }

            $result = $this->generateMultiSpeaker($prompt, $speaker1Name, $speaker2Name, $voice1, $voice2);
            if (!$result['success']) {
                return [
                    'success' => false,
                    'error' => 'Error en segmento ' . ($index + 1) . '/' . $total . ': ' . ($result['error'] ?? 'desconocido')
                ];
            }

            $pcm = base64_decode($result['audio_data'], true);
            if ($pcm === false || $pcm === '') {
                return [
                    'success' => false,
                    'error' => 'No se recibió audio válido en segmento ' . ($index + 1) . '/' . $total
                ];
            }

            $pcmParts[] = $pcm;
            if ($pause !== '' && $index < $total - 1) {
                $pcmParts[] = $pause;
            }
        }

        return [
            'success' => true,
            'audio_data' => base64_encode(implode('', $pcmParts)),
            'mime_type' => 'audio/wav',
            'sample_rate' => 24000,
            'channels' => 1,
            'bit_depth' => 16,
            'segments' => $total
        ];
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

    public static function silentPcm(int $durationMs, int $sampleRate = 24000, int $channels = 1, int $bitsPerSample = 16): string
    {
        $bytesPerSample = (int)($bitsPerSample / 8);
        $sampleCount = (int)round($sampleRate * ($durationMs / 1000) * $channels);
        return str_repeat("\0", $sampleCount * $bytesPerSample);
    }
}
