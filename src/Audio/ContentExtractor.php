<?php
namespace Audio;

/**
 * Extractor de contenido de artículos desde URLs y archivos
 */
class ContentExtractor
{
    /**
     * Extrae el contenido de texto de una URL
     */
    public function extractFromUrl(string $url): array
    {
        error_log("ContentExtractor: Extraer de URL: " . $url);
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            error_log("ContentExtractor: URL no válida: " . $url);
            return ['success' => false, 'error' => 'URL no válida'];
        }

        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: Mozilla/5.0 (compatible; EbonIA/1.0)\r\n",
                'timeout' => 30
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        $html = @file_get_contents($url, false, $ctx);

        if ($html === false) {
            $error = error_get_last();
            error_log("ContentExtractor: Error file_get_contents: " . ($error['message'] ?? 'Error desconocido'));
            return ['success' => false, 'error' => 'No se pudo acceder a la URL'];
        }

        error_log("ContentExtractor: HTML recibido (" . strlen($html) . " bytes)");

        // Extraer título
        $title = '';
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Extraer contenido principal
        $content = $this->extractMainContent($html);

        if (empty($content)) {
            error_log("ContentExtractor: Falló extracción de contenido principal (vacío)");
            return ['success' => false, 'error' => 'No se pudo extraer contenido del artículo'];
        }

        error_log("ContentExtractor: Contenido extraído con éxito (" . str_word_count($content) . " palabras)");

        return [
            'success' => true,
            'title' => $title,
            'content' => $content,
            'source' => parse_url($url, PHP_URL_HOST),
            'url' => $url,
            'word_count' => str_word_count($content)
        ];
    }

    /**
     * Extrae el contenido de un archivo PDF (base64)
     */
    public function extractFromPdf(string $base64Data): array
    {
        $pdfData = base64_decode($base64Data);
        
        if ($pdfData === false) {
            return ['success' => false, 'error' => 'Datos PDF inválidos'];
        }

        // Guardar temporalmente el PDF
        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempFile, $pdfData);

        try {
            // 1. Intentar con pdftotext (si está disponible)
            $text = $this->extractWithPdftotext($tempFile);
            
            // 2. Si falla, usar OpenRouter (mejor opción para PDFs modernos)
            if (empty($text)) {
                $text = $this->extractWithOpenRouter($base64Data);
            }
            
            // 3. Último fallback: extracción básica
            if (empty($text)) {
                $text = $this->extractPdfBasic($pdfData);
            }

            unlink($tempFile);

            if (empty($text)) {
                return ['success' => false, 'error' => 'No se pudo extraer texto del PDF'];
            }

            // Extraer título de las primeras líneas del contenido
            $title = $this->extractTitleFromText($text);

            return [
                'success' => true,
                'title' => $title,
                'content' => $text,
                'source' => 'PDF upload',
                'word_count' => str_word_count($text)
            ];
        } catch (\Exception $e) {
            @unlink($tempFile);
            return ['success' => false, 'error' => 'Error procesando PDF: ' . $e->getMessage()];
        }
    }

    /**
     * Extrae el contenido de un archivo de texto plano
     */
    public function extractFromText(string $text): array
    {
        $text = trim($text);
        error_log("ContentExtractor: Extraer de texto (" . strlen($text) . " caracteres)");
        
        if (empty($text)) {
            error_log("ContentExtractor: El texto está vacío");
            return ['success' => false, 'error' => 'El texto está vacío'];
        }

        $title = $this->extractTitleFromText($text);

        return [
            'success' => true,
            'title' => $title,
            'content' => $text,
            'source' => 'Texto directo',
            'word_count' => str_word_count($text)
        ];
    }

    /**
     * Extrae un título inteligente de las primeras líneas del texto
     */
    private function extractTitleFromText(string $text): string
    {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $text))));
        
        if (empty($lines)) {
            return 'Documento sin título';
        }

        // Buscar la primera línea significativa (no vacía, más de 10 caracteres)
        foreach ($lines as $line) {
            // Ignorar líneas muy cortas o que parezcan metadatos
            if (strlen($line) < 10) continue;
            if (preg_match('/^(author|date|by|página|page):/i', $line)) continue;
            
            // Si es una línea tipo título (corta, sin punto final, mayúsculas)
            if (strlen($line) <= 150 && !preg_match('/\.\s*$/', $line)) {
                return mb_substr($line, 0, 100);
            }
            
            // Si es la primera línea y es razonablemente corta, usarla
            if (strlen($line) <= 200) {
                $title = preg_replace('/\.\s*$/', '', $line); // quitar punto final
                return mb_substr($title, 0, 100);
            }
        }

        // Fallback: usar primera línea truncada
        $firstLine = $lines[0];
        $title = mb_substr($firstLine, 0, 100);
        if (strlen($firstLine) > 100) {
            $title .= '...';
        }
        
        return $title;
    }

    /**
     * Extrae el contenido principal de HTML limpiando boilerplate
     */
    private function extractMainContent(string $html): string
    {
        // Eliminar scripts, styles, nav, header, footer, aside
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<nav[^>]*>.*?<\/nav>/is', '', $html);
        $html = preg_replace('/<header[^>]*>.*?<\/header>/is', '', $html);
        $html = preg_replace('/<footer[^>]*>.*?<\/footer>/is', '', $html);
        $html = preg_replace('/<aside[^>]*>.*?<\/aside>/is', '', $html);
        $html = preg_replace('/<form[^>]*>.*?<\/form>/is', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);

        // Buscar contenido en article, main, o div con clases típicas de contenido
        $contentPatterns = [
            '/<article[^>]*>(.*?)<\/article>/is',
            '/<main[^>]*>(.*?)<\/main>/is',
            '/<div[^>]*class="[^"]*(?:content|article|post|entry|story)[^"]*"[^>]*>(.*?)<\/div>/is',
        ];

        $content = '';
        foreach ($contentPatterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $content = $matches[1];
                break;
            }
        }

        // Si no encontramos contenido específico, usar el body
        if (empty($content)) {
            if (preg_match('/<body[^>]*>(.*?)<\/body>/is', $html, $matches)) {
                $content = $matches[1];
            } else {
                // Fallback final: usar todo el HTML si no hay body
                $content = $html;
            }
        }

        // Convertir párrafos y headers a texto con saltos de línea
        $content = preg_replace('/<\/p>/i', "\n\n", $content);
        $content = preg_replace('/<\/h[1-6]>/i', "\n\n", $content);
        $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
        $content = preg_replace('/<li[^>]*>/i', "\n• ", $content);

        // Eliminar todas las etiquetas HTML restantes
        $content = strip_tags($content);

        // Decodificar entidades HTML
        $content = html_entity_decode($content, ENT_QUOTES, 'UTF-8');

        // Limpiar espacios múltiples y líneas vacías
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n\s*\n\s*\n/', "\n\n", $content);
        $content = trim($content);

        return $content;
    }

    /**
     * Extrae texto de PDF usando pdftotext (poppler-utils)
     */
    private function extractWithPdftotext(string $filePath): string
    {
        $output = [];
        $returnVar = 0;
        
        exec("which pdftotext 2>/dev/null", $output, $returnVar);
        
        if ($returnVar !== 0) {
            return '';
        }

        $textFile = $filePath . '.txt';
        exec("pdftotext -enc UTF-8 " . escapeshellarg($filePath) . " " . escapeshellarg($textFile) . " 2>/dev/null", $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($textFile)) {
            return '';
        }

        $text = file_get_contents($textFile);
        @unlink($textFile);

        return trim($text);
    }

    /**
     * Extrae texto de PDF usando OpenRouter (multimodal con file-parser plugin)
     * @throws \Exception si hay error para que el llamador sepa qué falló
     */
    private function extractWithOpenRouter(string $base64Data): string
    {
        $apiKey = \App\Env::get('OPENROUTER_API_KEY');
        if (empty($apiKey)) {
            throw new \Exception('Falta OPENROUTER_API_KEY para extraer PDF');
        }

        // Verificar tamaño del PDF
        $pdfSizeBytes = strlen(base64_decode($base64Data));
        $pdfSizeMB = $pdfSizeBytes / (1024 * 1024);
        if ($pdfSizeMB > 20) {
            throw new \Exception("El PDF es demasiado grande (" . round($pdfSizeMB, 1) . "MB). Máximo 20MB.");
        }

        $url = 'https://openrouter.ai/api/v1/chat/completions';
        
        // Formato OpenRouter para PDFs según documentación
        $payload = [
            'model' => 'google/gemini-3-flash-preview',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'file',
                            'file' => [
                                'filename' => 'document.pdf',
                                'file_data' => 'data:application/pdf;base64,' . $base64Data
                            ]
                        ],
                        [
                            'type' => 'text',
                            'text' => 'Extrae TODO el contenido de texto de este documento PDF. Devuelve SOLO el texto extraído, sin introducción, explicación ni formato adicional. Mantén la estructura de párrafos y secciones.'
                        ]
                    ]
                ]
            ],
            'plugins' => [
                [
                    'id' => 'file-parser',
                    'pdf' => ['engine' => 'pdf-text']
                ]
            ],
            'temperature' => 0.1,
            'max_tokens' => 16384
        ];

        $jsonPayload = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        
        if ($jsonPayload === false) {
            throw new \Exception('Error codificando PDF a JSON: ' . json_last_error_msg());
        }
        
        $payloadSizeMB = strlen($jsonPayload) / (1024 * 1024);
        if ($payloadSizeMB > 25) {
            throw new \Exception("El payload es demasiado grande (" . round($payloadSizeMB, 1) . "MB).");
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($jsonPayload),
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: ' . (\App\Env::get('APP_URL') ?? 'https://ebonia.es'),
            'X-Title: Ebonia'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 300);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlError) {
            throw new \Exception('Error de conexión: ' . $curlError);
        }

        if (!$response) {
            throw new \Exception('No se recibió respuesta de OpenRouter');
        }

        $data = json_decode($response, true);

        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Error desconocido';
            throw new \Exception('Error de OpenRouter: ' . $errorMsg);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Error HTTP {$httpCode} de OpenRouter");
        }

        if (isset($data['choices'][0]['message']['content'])) {
            $text = trim($data['choices'][0]['message']['content']);
            if (empty($text)) {
                throw new \Exception('OpenRouter devolvió respuesta vacía');
            }
            return $text;
        }

        throw new \Exception('Respuesta inesperada de OpenRouter');
    }

    /**
     * Mantener compatibilidad con nombre anterior del método
     */
    private function extractWithGemini(string $base64Data): string
    {
        return $this->extractWithOpenRouter($base64Data);
    }

    /**
     * Extracción básica de texto de PDF (sin dependencias externas)
     */
    private function extractPdfBasic(string $pdfData): string
    {
        $text = '';
        
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $pdfData, $matches)) {
            foreach ($matches[1] as $block) {
                if (preg_match_all('/\(([^)]+)\)/', $block, $stringMatches)) {
                    $text .= implode(' ', $stringMatches[1]) . ' ';
                }
                if (preg_match_all('/<([0-9A-Fa-f]+)>/', $block, $hexMatches)) {
                    foreach ($hexMatches[1] as $hex) {
                        $text .= hex2bin($hex) . ' ';
                    }
                }
            }
        }

        $text = preg_replace('/[^\x20-\x7E\xA0-\xFF\n]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
