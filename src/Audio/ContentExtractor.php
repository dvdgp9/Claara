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
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return ['success' => false, 'error' => 'URL no válida'];
        }

        // SSRF Protection: descarga validada (valida cada salto de redirección
        // y fija la conexión a la IP ya verificada para evitar DNS rebinding).
        $fetch = $this->fetchUrlSafely($url);
        if ($fetch['success'] !== true) {
            return ['success' => false, 'error' => $fetch['error']];
        }
        $html = $fetch['body'];

        // Extraer título
        $title = '';
        if (preg_match('/<title[^>]*>([^<]+)<\/title>/i', $html, $matches)) {
            $title = html_entity_decode(trim($matches[1]), ENT_QUOTES, 'UTF-8');
        }

        // Extraer contenido principal
        $content = $this->extractMainContent($html);

        if (empty($content)) {
            return ['success' => false, 'error' => 'No se pudo extraer contenido del artículo'];
        }

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
     * Extrae texto de PDF usando solo herramientas locales.
     */
    public function extractFromPdfLocally(string $base64Data): array
    {
        $pdfData = base64_decode($base64Data);
        
        if ($pdfData === false) {
            return ['success' => false, 'error' => 'Datos PDF inválidos'];
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'pdf_');
        file_put_contents($tempFile, $pdfData);

        try {
            // Solo aceptamos pdftotext como extractor local. El fallback de
            // extractPdfBasic (regex sobre bytes crudos) devuelve basura en
            // PDFs modernos con FlateDecode, y esa basura pasaba por válida
            // reemplazando al PDF real en el contexto. Si pdftotext no está
            // disponible, devolvemos fallo y el llamador dejará el PDF
            // adjunto para que OpenRouter lo procese con su parser nativo.
            $text = $this->extractWithPdftotext($tempFile);

            @unlink($tempFile);

            if (empty($text)) {
                return ['success' => false, 'error' => 'No se pudo extraer texto del PDF localmente'];
            }

            $title = $this->extractTitleFromText($text);

            return [
                'success' => true,
                'title' => $title,
                'content' => $text,
                'source' => 'PDF upload (local)',
                'word_count' => str_word_count($text)
            ];
        } catch (\Exception $e) {
            @unlink($tempFile);
            return ['success' => false, 'error' => 'Error procesando PDF localmente: ' . $e->getMessage()];
        }
    }

    /**
     * Extrae el contenido de un archivo de texto plano
     */
    public function extractFromText(string $text): array
    {
        $text = trim($text);
        
        if (empty($text)) {
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
        // Usar DOMDocument para parsing más robusto
        $dom = new \DOMDocument();
        @$dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NOERROR | LIBXML_NOWARNING);
        
        // Eliminar elementos no deseados
        $tagsToRemove = ['script', 'style', 'nav', 'header', 'footer', 'aside', 'form', 'iframe', 'noscript'];
        foreach ($tagsToRemove as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            $elementsArray = [];
            foreach ($elements as $element) {
                $elementsArray[] = $element;
            }
            foreach ($elementsArray as $element) {
                $element->parentNode->removeChild($element);
            }
        }
        
        // Buscar contenido en selectores específicos (prioridad)
        $selectors = [
            'article',
            'main',
            ['tag' => 'div', 'class' => ['content', 'article', 'post', 'entry', 'story', 'single-post', 'blog-post', 'entry-content']]
        ];
        
        $contentNode = null;
        
        // Intentar encontrar por etiqueta directa
        foreach (['article', 'main'] as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            if ($nodes->length > 0) {
                $contentNode = $nodes->item(0);
                break;
            }
        }
        
        // Si no, buscar divs con clases de contenido
        if (!$contentNode) {
            $divs = $dom->getElementsByTagName('div');
            foreach ($divs as $div) {
                $class = $div->getAttribute('class');
                if (preg_match('/(content|article|post|entry|story|single|blog)/i', $class)) {
                    $contentNode = $div;
                    break;
                }
            }
        }
        
        // Si aún no encontramos, usar body
        if (!$contentNode) {
            $body = $dom->getElementsByTagName('body');
            if ($body->length > 0) {
                $contentNode = $body->item(0);
            }
        }
        
        // Si nada funciona, usar todo el documento
        if (!$contentNode) {
            $contentNode = $dom->documentElement;
        }
        
        // Extraer texto del nodo
        $content = $this->extractTextFromNode($contentNode);
        
        // Limpiar espacios múltiples y líneas vacías
        $content = preg_replace('/[ \t]+/', ' ', $content);
        $content = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $content);
        $content = trim($content);
        
        return $content;
    }
    
    /**
     * Extrae texto de un nodo DOM recursivamente
     */
    private function extractTextFromNode(\DOMNode $node): string
    {
        $text = '';
        
        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                $text .= $child->nodeValue;
            } elseif ($child->nodeType === XML_ELEMENT_NODE) {
                $tagName = strtolower($child->nodeName);
                
                // Añadir saltos de línea para elementos de bloque
                if (in_array($tagName, ['p', 'div', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $text .= "\n" . $this->extractTextFromNode($child) . "\n";
                } elseif ($tagName === 'li') {
                    $text .= "\n• " . $this->extractTextFromNode($child);
                } else {
                    $text .= $this->extractTextFromNode($child);
                }
            }
        }
        
        return $text;
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
            'HTTP-Referer: ' . (\App\Env::get('APP_URL') ?? 'https://claara.tech'),
            'X-Title: Claara'
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

    /**
     * Descarga una URL de forma resistente a SSRF.
     *
     * Sigue las redirecciones manualmente (file_get_contents/cURL las seguían
     * sin revalidar, permitiendo saltar a una IP interna) y fija la conexión a
     * la IP que se acaba de validar mediante CURLOPT_RESOLVE, de modo que un
     * cambio de DNS entre la validación y la descarga (DNS rebinding) no pueda
     * redirigir el socket a la red interna.
     *
     * @return array{success:bool, body?:string, error?:string}
     */
    private function fetchUrlSafely(string $url, int $maxRedirects = 4): array
    {
        if (!function_exists('curl_init')) {
            return ['success' => false, 'error' => 'No hay soporte cURL para descargar la URL de forma segura'];
        }

        $current = $url;

        for ($hop = 0; $hop <= $maxRedirects; $hop++) {
            $check = $this->validateUrlForSsrf($current);
            if ($check !== true) {
                return ['success' => false, 'error' => $check];
            }

            $parsed = parse_url($current);
            $scheme = strtolower($parsed['scheme']);
            $host = $parsed['host'];
            $port = (int)($parsed['port'] ?? ($scheme === 'https' ? 443 : 80));

            // validateUrlForSsrf ya garantiza que todas las IPs resueltas son
            // públicas; fijamos la conexión a la primera para que cURL no vuelva
            // a resolver el dominio.
            $ip = $this->resolveFirstIp($host);
            if ($ip === null) {
                return ['success' => false, 'error' => 'No se pudo resolver el dominio'];
            }

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $current,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; EbonIA/1.0)',
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"],
            ]);

            $body = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $redirectUrl = (string)curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($body === false) {
                return ['success' => false, 'error' => 'No se pudo acceder a la URL' . ($curlError ? ': ' . $curlError : '')];
            }

            // Redirección: revalidamos el destino en la siguiente iteración.
            if ($httpCode >= 300 && $httpCode < 400 && $redirectUrl !== '') {
                $current = $redirectUrl;
                continue;
            }

            return ['success' => true, 'body' => (string)$body];
        }

        return ['success' => false, 'error' => 'Demasiadas redirecciones'];
    }

    /**
     * Resuelve un host a su primera IP. Acepta literales IP directamente.
     */
    private function resolveFirstIp(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        $ips = gethostbynamel($host);
        return ($ips && isset($ips[0])) ? $ips[0] : null;
    }

    /**
     * Valida una URL contra ataques SSRF
     * @return true|string True si es válida, string con error si no
     */
    private function validateUrlForSsrf(string $url): bool|string
    {
        $parsed = parse_url($url);

        // Solo permitir http/https
        $scheme = strtolower($parsed['scheme'] ?? '');
        if (!in_array($scheme, ['http', 'https'])) {
            return 'Solo se permiten URLs HTTP/HTTPS';
        }

        $host = $parsed['host'] ?? '';
        if (empty($host)) {
            return 'URL sin host válido';
        }

        // Normalizar literal IPv6 entre corchetes ([::1] -> ::1)
        $hostForCheck = trim($host, '[]');

        // Bloquear localhost y variantes
        $blockedHosts = ['localhost', '127.0.0.1', '::1', '0.0.0.0'];
        if (in_array(strtolower($hostForCheck), $blockedHosts)) {
            return 'No se permiten URLs locales';
        }

        // Literal IP directo (IPv4 o IPv6): validar sin resolver DNS.
        if (filter_var($hostForCheck, FILTER_VALIDATE_IP)) {
            if ($this->isPrivateIp($hostForCheck)) {
                return 'No se permiten URLs que apunten a redes internas';
            }
            return true;
        }

        // Resolver DNS y verificar que no sea IP interna. gethostbynamel solo
        // devuelve IPv4; un host solo-IPv6 se rechaza (fail-closed).
        $ips = gethostbynamel($host);
        if ($ips === false) {
            return 'No se pudo resolver el dominio';
        }

        foreach ($ips as $ip) {
            if ($this->isPrivateIp($ip)) {
                return 'No se permiten URLs que apunten a redes internas';
            }
        }

        return true;
    }
    
    /**
     * Verifica si una IP es privada/interna
     */
    private function isPrivateIp(string $ip): bool
    {
        // Rangos de IPs privadas/internas
        $privateRanges = [
            '10.0.0.0/8',        // Clase A privada
            '172.16.0.0/12',     // Clase B privada
            '192.168.0.0/16',    // Clase C privada
            '127.0.0.0/8',       // Loopback
            '169.254.0.0/16',    // Link-local (AWS metadata, etc.)
            '0.0.0.0/8',         // "This" network
            '100.64.0.0/10',     // Carrier-grade NAT
            '192.0.0.0/24',      // IETF Protocol Assignments
            '192.0.2.0/24',      // TEST-NET-1
            '198.51.100.0/24',   // TEST-NET-2
            '203.0.113.0/24',    // TEST-NET-3
            '224.0.0.0/4',       // Multicast
            '240.0.0.0/4',       // Reserved
        ];
        
        $ipLong = ip2long($ip);
        if ($ipLong === false) {
            return true; // Si no se puede parsear, bloquear por seguridad
        }
        
        foreach ($privateRanges as $range) {
            [$subnet, $bits] = explode('/', $range);
            $subnetLong = ip2long($subnet);
            $mask = -1 << (32 - (int)$bits);
            
            if (($ipLong & $mask) === ($subnetLong & $mask)) {
                return true;
            }
        }
        
        return false;
    }
}
