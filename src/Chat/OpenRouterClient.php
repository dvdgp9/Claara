<?php
namespace Chat;

use App\Env;
use App\Response;

/**
 * Cliente para OpenRouter (https://openrouter.ai)
 * 
 * OpenRouter es un gateway que provee acceso unificado a múltiples LLMs
 * (Gemini, GPT, Claude, Qwen, etc.) con una API compatible con OpenAI.
 * 
 * Modelos se especifican como "provider/model":
 * - openrouter/auto (selección automática inteligente)
 * - google/gemini-2.5-flash
 * - qwen/qwen-plus
 * - openai/gpt-4o
 * - anthropic/claude-3.5-sonnet
 */
class OpenRouterClient {
    private string $apiKey;
    private string $model;
    private ?string $usedModel = null; // Modelo real usado (para openrouter/auto)
    private ?string $systemInstruction;
    private ?float $temperature;
    private ?int $maxTokens;
    private ?array $lastImages = null; // Imágenes generadas en última respuesta
    private ?array $lastAnnotations = null; // Anotaciones/citas web de última respuesta
    private ?string $pdfEngine = null; // null = dejar que OpenRouter auto-seleccione (native en Gemini, mistral-ocr en el resto)
    private string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';

    public function __construct(
        ?string $apiKey = null, 
        ?string $model = null, 
        ?string $systemInstruction = null,
        ?float $temperature = null,
        ?int $maxTokens = null
    ) {
        $this->apiKey = $apiKey ?? (Env::get('OPENROUTER_API_KEY') ?? '');
        $this->model = $model ?? (Env::get('OPENROUTER_MODEL') ?? 'openrouter/auto');
        $this->systemInstruction = $systemInstruction;
        $this->temperature = $temperature;
        $this->maxTokens = $maxTokens;
    }

    public function generateText(string $prompt): string
    {
        return $this->generateWithMessages([
            [ 'role' => 'user', 'content' => $prompt ]
        ]);
    }

    /**
     * @param array<int, array{role:string, content:string, file?:array}> $messages
     * @param array|null $modalities Modalidades de salida (ej: ['image', 'text'] para generación de imágenes)
     * @param bool $webSearch Activar búsqueda web para enriquecer respuestas
     */
    public function generateWithMessages(array $messages, ?array $modalities = null, bool $webSearch = false): string
    {
        if (!$this->apiKey) {
            Response::error('openrouter_api_key_missing', 'Falta OPENROUTER_API_KEY en .env', 500);
        }

        // Construir mensajes en formato OpenAI
        $messagesPayload = [];
        
        // Agregar system instruction si existe
        if ($this->systemInstruction !== null && $this->systemInstruction !== '') {
            $messagesPayload[] = [
                'role' => 'system',
                'content' => $this->systemInstruction
            ];
        }
        
        // Agregar mensajes del historial
        $hasPdf = false;
        foreach ($messages as $m) {
            $content = [];
            
            // Agregar archivo si existe (para mensajes de usuario)
            if (isset($m['file']) && $m['role'] === 'user') {
                $file = $m['file'];
                // OpenRouter/OpenAI soporta imágenes en formato base64
                if (str_starts_with($file['mime_type'], 'image/')) {
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $file['mime_type'] . ';base64,' . $file['data']
                        ]
                    ];
                } elseif ($file['mime_type'] === 'application/pdf') {
                    // Para PDFs, usar bloque type:file + file-parser plugin
                    $hasPdf = true;
                    $filename = $file['name'] ?? 'document.pdf';
                    $content[] = [
                        'type' => 'file',
                        'file' => [
                            'filename' => $filename,
                            'file_data' => 'data:application/pdf;base64,' . $file['data']
                        ]
                    ];
                }
            }
            
            // Agregar texto
            if (!empty($m['content'])) {
                if (is_array($m['content'])) {
                    // Si ya es un array (multimodal), lo añadimos tal cual
                    foreach ($m['content'] as $item) {
                        $content[] = $item;
                    }
                } else if (!empty($content)) {
                    // Si hay archivo, usar formato array de contenido
                    $content[] = [
                        'type' => 'text',
                        'text' => (string)$m['content']
                    ];
                } else {
                    // Si no hay archivo, usar string directo
                    $content = (string)$m['content'];
                }
            }
            
            // Safeguard: si content quedó como array vacío, usar string vacío
            if (is_array($content) && empty($content)) {
                $content = '';
            }
            
            // Omitir mensajes sin contenido real (evita 400 en OpenRouter)
            if ($content === '' || $content === null) {
                continue;
            }
            
            $messagesPayload[] = [
                'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $content
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messagesPayload
        ];
        // Construir array de plugins
        $plugins = [];
        
        // Si hay PDF y se ha forzado un engine concreto, añadir plugin file-parser.
        // Si no, omitimos el plugin para que OpenRouter use el mejor disponible
        // (native en modelos que lo soporten como Gemini; mistral-ocr en otros).
        if ($hasPdf && $this->pdfEngine !== null) {
            $plugins[] = [
                'id' => 'file-parser',
                'pdf' => [ 'engine' => $this->pdfEngine ]
            ];
        }
        
        // Si webSearch está activo, añadir plugin web
        if ($webSearch) {
            $plugins[] = [ 'id' => 'web' ];
        }
        
        // Añadir plugins al payload si hay alguno
        if (!empty($plugins)) {
            $payload['plugins'] = $plugins;
        }
        // Si hay modalities (ej: generación de imágenes), añadirlas
        if ($modalities !== null && !empty($modalities)) {
            $payload['modalities'] = $modalities;
        }
        
        // Añadir parámetros opcionales
        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }
        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . (Env::get('APP_URL') ?? 'https://ebonia.es'),
                'X-Title: Ebonia'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 180, // 3 minutos máximo
            CURLOPT_CONNECTTIMEOUT => 10, // 10s para conectar
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $err) {
            Response::error('openrouter_request_failed', 'Fallo al contactar con OpenRouter: ' . $err, 502);
        }

        $data = json_decode($raw, true);
        if ($status < 200 || $status >= 300) {
            $msg = $data['error']['message'] ?? $data['message'] ?? ('HTTP '.$status);
            Response::error('openrouter_bad_response', 'Error de OpenRouter: ' . $msg, 502);
        }

        $message = $data['choices'][0]['message'] ?? [];
        $text = $message['content'] ?? '';
        
        // Capturar imágenes generadas si existen
        $this->lastImages = null;
        if (isset($message['images']) && is_array($message['images'])) {
            $this->lastImages = $message['images'];
        }
        
        // Capturar anotaciones/citas web si existen
        $this->lastAnnotations = null;
        if (isset($message['annotations']) && is_array($message['annotations'])) {
            $this->lastAnnotations = $message['annotations'];
        }
        
        // Para generación de imágenes, el texto puede estar vacío pero tener imágenes
        if ($text === '' && empty($this->lastImages)) {
            Response::error('openrouter_empty', 'Respuesta vacía de OpenRouter', 502);
        }
        
        // Capturar el modelo real usado (importante para openrouter/auto)
        $this->usedModel = $data['model'] ?? $this->model;
        
        return $text;
    }

    /**
     * Obtiene el modelo usado en la última generación.
     * Si se usó openrouter/auto, devuelve el modelo real seleccionado.
     */
    public function getModel(): string
    {
        return $this->usedModel ?? $this->model;
    }

    /**
     * Obtiene el modelo configurado (antes de auto-selección)
     */
    public function getConfiguredModel(): string
    {
        return $this->model;
    }

    /**
     * Forzar un engine específico para el plugin file-parser (PDFs).
     * Engines válidos (2026): 'native', 'mistral-ocr', 'cloudflare-ai'.
     * null = no enviar plugin explícito; OpenRouter elegirá según el modelo.
     */
    public function setPdfEngine(?string $engine): void
    {
        $this->pdfEngine = $engine;
    }

    /**
     * Obtiene las imágenes generadas en la última respuesta (si las hay)
     * @return array|null Array de imágenes con formato [{type: 'image_url', image_url: {url: 'data:...'}}]
     */
    public function getLastImages(): ?array
    {
        return $this->lastImages;
    }

    /**
     * Obtiene las anotaciones/citas web de la última respuesta (si las hay)
     * @return array|null Array de anotaciones con formato [{type: 'url_citation', url_citation: {url, title, content?, start_index, end_index}}]
     */
    public function getLastAnnotations(): ?array
    {
        return $this->lastAnnotations;
    }

    /**
     * Genera respuesta en modo streaming (Server-Sent Events)
     * Cada chunk se pasa al callback inmediatamente.
     * 
     * @param array $messages Array de mensajes [{role, content, file?}]
     * @param callable $onChunk Callback que recibe cada chunk de texto: fn(string $chunk): void
     * @param callable|null $onComplete Callback al completar: fn(string $fullText, string $model): void
     * @param bool $webSearch Activar búsqueda web
     * @return string El texto completo generado
     */
    public function generateWithMessagesStreaming(array $messages, callable $onChunk, ?callable $onComplete = null, bool $webSearch = false): string
    {
        if (!$this->apiKey) {
            throw new \Exception('Falta OPENROUTER_API_KEY en .env');
        }

        // Construir mensajes en formato OpenAI
        $messagesPayload = [];
        
        if ($this->systemInstruction !== null && $this->systemInstruction !== '') {
            $messagesPayload[] = [
                'role' => 'system',
                'content' => $this->systemInstruction
            ];
        }
        
        $hasPdf = false;
        foreach ($messages as $m) {
            $content = [];
            
            if (isset($m['file']) && $m['role'] === 'user') {
                $file = $m['file'];
                if (str_starts_with($file['mime_type'], 'image/')) {
                    $content[] = [
                        'type' => 'image_url',
                        'image_url' => [
                            'url' => 'data:' . $file['mime_type'] . ';base64,' . $file['data']
                        ]
                    ];
                } elseif ($file['mime_type'] === 'application/pdf') {
                    $hasPdf = true;
                    $filename = $file['name'] ?? 'document.pdf';
                    $content[] = [
                        'type' => 'file',
                        'file' => [
                            'filename' => $filename,
                            'file_data' => 'data:application/pdf;base64,' . $file['data']
                        ]
                    ];
                }
            }
            
            if (!empty($m['content'])) {
                if (!empty($content)) {
                    $content[] = [
                        'type' => 'text',
                        'text' => (string)$m['content']
                    ];
                } else {
                    $content = (string)$m['content'];
                }
            }
            
            // Safeguard: si content quedó como array vacío, usar string vacío
            if (is_array($content) && empty($content)) {
                $content = '';
            }
            
            // Omitir mensajes sin contenido real (evita 400 en OpenRouter)
            if ($content === '' || $content === null) {
                continue;
            }
            
            $messagesPayload[] = [
                'role' => $m['role'] === 'assistant' ? 'assistant' : 'user',
                'content' => $content
            ];
        }

        $payload = [
            'model' => $this->model,
            'messages' => $messagesPayload,
            'stream' => true
        ];
        
        // Construir array de plugins
        $plugins = [];
        if ($hasPdf && $this->pdfEngine !== null) {
            $plugins[] = [
                'id' => 'file-parser',
                'pdf' => [ 'engine' => $this->pdfEngine ]
            ];
        }
        
        if ($webSearch) {
            $plugins[] = [ 'id' => 'web' ];
        }
        
        if (!empty($plugins)) {
            $payload['plugins'] = $plugins;
        }
        
        if ($this->temperature !== null) {
            $payload['temperature'] = $this->temperature;
        }
        if ($this->maxTokens !== null) {
            $payload['max_tokens'] = $this->maxTokens;
        }

        $fullText = '';
        $buffer = '';
        $rawErrorBody = ''; // Capturar body en caso de error HTTP
        
        // Referencia a $this para usar dentro del closure
        $self = $this;
        
        $ch = curl_init($this->baseUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
                'HTTP-Referer: ' . (Env::get('APP_URL') ?? 'https://ebonia.es'),
                'X-Title: Ebonia',
                'Accept: text/event-stream'
            ],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_BUFFERSIZE => 128, // Buffer pequeño para recibir chunks rápido
            CURLOPT_WRITEFUNCTION => function($ch, $data) use (&$fullText, &$buffer, &$rawErrorBody, $onChunk, $self) {
                // Acumular body bruto para diagnóstico de errores HTTP
                $rawErrorBody .= $data;
                $buffer .= $data;
                
                // Procesar líneas completas del buffer
                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, $pos + 1);
                    
                    $line = trim($line);
                    if ($line === '' || $line === 'data: [DONE]') {
                        continue;
                    }
                    
                    if (strpos($line, 'data: ') === 0) {
                        $jsonStr = substr($line, 6);
                        $json = json_decode($jsonStr, true);
                        
                        if ($json && isset($json['choices'][0]['delta']['content'])) {
                            $chunk = $json['choices'][0]['delta']['content'];
                            $fullText .= $chunk;
                            $onChunk($chunk);
                        }
                        
                        // Capturar modelo usado
                        if ($json && isset($json['model'])) {
                            $self->usedModel = $json['model'];
                        }
                    }
                }
                
                return strlen($data);
            }
        ]);
        
        $result = curl_exec($ch);
        $err = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($err) {
            throw new \Exception('Error de conexión con OpenRouter: ' . $err);
        }
        
        if ($status >= 400) {
            // Intentar extraer mensaje de error del body
            $errorMsg = 'Error HTTP ' . $status . ' de OpenRouter';
            if ($rawErrorBody !== '') {
                $errorJson = json_decode($rawErrorBody, true);
                if ($errorJson) {
                    $detail = $errorJson['error']['message'] ?? $errorJson['message'] ?? null;
                    if ($detail) {
                        $errorMsg .= ': ' . $detail;
                    }
                }
            }
            throw new \Exception($errorMsg);
        }
        
        if ($onComplete) {
            $onComplete($fullText, $this->usedModel ?? $this->model);
        }
        
        return $fullText;
    }
}
