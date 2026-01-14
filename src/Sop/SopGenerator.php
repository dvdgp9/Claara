<?php

namespace Sop;

use App\Env;
use Audio\ContentExtractor;
use Utils\DocumentGenerator;

/**
 * Generador de SOPs (Standard Operating Procedures)
 * Orquesta la extracción de contenido y genera SOPs en múltiples formatos
 */
class SopGenerator
{
    private string $apiKey;
    private string $model = 'google/gemini-3-flash-preview';
    private string $baseUrl = 'https://openrouter.ai/api/v1/chat/completions';
    
    private ContentExtractor $contentExtractor;
    private AudioTranscriber $audioTranscriber;
    private ImageDescriber $imageDescriber;
    private DocumentGenerator $documentGenerator;
    
    public function __construct()
    {
        $this->apiKey = Env::get('OPENROUTER_API_KEY') ?? '';
        $this->contentExtractor = new ContentExtractor();
        $this->audioTranscriber = new AudioTranscriber();
        $this->imageDescriber = new ImageDescriber();
        $this->documentGenerator = new DocumentGenerator();
    }
    
    /**
     * Genera un SOP a partir de contenido mixto
     * 
     * @param array $input Datos de entrada
     * @return array Resultado con SOP en múltiples formatos
     */
    public function generate(array $input): array
    {
        $sopTitle = $input['title'] ?? 'Procedimiento Operativo';
        $extractedContent = [];
        $errors = [];
        
        // 1. Procesar texto directo
        if (!empty($input['text'])) {
            $extractedContent[] = [
                'type' => 'text',
                'content' => $input['text']
            ];
        }
        
        // 2. Procesar URL
        if (!empty($input['url'])) {
            $result = $this->contentExtractor->extractFromUrl($input['url']);
            if ($result['success']) {
                $extractedContent[] = [
                    'type' => 'url',
                    'source' => $result['source'],
                    'title' => $result['title'],
                    'content' => $result['content']
                ];
                if (empty($sopTitle) || $sopTitle === 'Procedimiento Operativo') {
                    $sopTitle = $result['title'];
                }
            } else {
                $errors[] = 'URL: ' . $result['error'];
            }
        }
        
        // 3. Procesar PDF
        if (!empty($input['pdf_base64'])) {
            $result = $this->contentExtractor->extractFromPdf($input['pdf_base64']);
            if ($result['success']) {
                $extractedContent[] = [
                    'type' => 'pdf',
                    'title' => $result['title'],
                    'content' => $result['content']
                ];
                if (empty($sopTitle) || $sopTitle === 'Procedimiento Operativo') {
                    $sopTitle = $result['title'];
                }
            } else {
                $errors[] = 'PDF: ' . $result['error'];
            }
        }
        
        // 4. Procesar audio
        if (!empty($input['audio_base64']) && !empty($input['audio_mime'])) {
            $result = $this->audioTranscriber->transcribe(
                $input['audio_base64'],
                $input['audio_mime'],
                $input['audio_filename'] ?? 'audio'
            );
            if ($result['success']) {
                $extractedContent[] = [
                    'type' => 'audio',
                    'content' => $result['text'],
                    'duration' => $result['duration_estimate'] ?? null
                ];
            } else {
                $errors[] = 'Audio: ' . $result['error'];
            }
        }
        
        // 5. Procesar imágenes
        if (!empty($input['images']) && is_array($input['images'])) {
            $result = $this->imageDescriber->describeMultiple($input['images']);
            if ($result['success']) {
                $extractedContent[] = [
                    'type' => 'images',
                    'content' => $result['combined'],
                    'count' => count($result['descriptions'])
                ];
            }
            if (!empty($result['errors'])) {
                $errors = array_merge($errors, $result['errors']);
            }
        }
        
        // Verificar que hay contenido para procesar
        if (empty($extractedContent)) {
            return [
                'success' => false,
                'error' => 'No se pudo extraer contenido de ninguna fuente.' . 
                    (!empty($errors) ? ' Errores: ' . implode('; ', $errors) : '')
            ];
        }
        
        // 6. Combinar todo el contenido extraído
        $combinedContent = $this->combineContent($extractedContent);
        
        // 7. Generar SOP en formato Markdown
        $markdownResult = $this->generateMarkdown($combinedContent, $sopTitle);
        
        if (!$markdownResult['success']) {
            return [
                'success' => false,
                'error' => 'Error generando SOP: ' . $markdownResult['error']
            ];
        }
        
        $markdown = $markdownResult['content'];
        
        // 8. Generar diagrama Mermaid
        $mermaidResult = $this->generateMermaid($markdown, $sopTitle);
        $mermaid = $mermaidResult['success'] ? $mermaidResult['content'] : null;
        
        // 9. Generar documentos descargables
        $pdfResult = $this->documentGenerator->generatePdf($markdown, $sopTitle);
        $docxResult = $this->documentGenerator->generateDocx($markdown, $sopTitle);
        
        return [
            'success' => true,
            'title' => $sopTitle,
            'formats' => [
                'markdown' => $markdown,
                'mermaid' => $mermaid,
                'pdf' => $pdfResult['success'] ? [
                    'url' => $pdfResult['url'],
                    'filename' => $pdfResult['filename']
                ] : null,
                'docx' => $docxResult['success'] ? [
                    'url' => $docxResult['url'],
                    'filename' => $docxResult['filename']
                ] : null,
            ],
            'sources' => array_map(fn($c) => $c['type'], $extractedContent),
            'warnings' => $errors
        ];
    }
    
    /**
     * Combina contenido de múltiples fuentes en un solo texto
     */
    private function combineContent(array $extractedContent): string
    {
        $sections = [];
        
        foreach ($extractedContent as $item) {
            $header = match($item['type']) {
                'text' => '## Contenido de texto',
                'url' => '## Contenido de URL' . (!empty($item['source']) ? " ({$item['source']})" : ''),
                'pdf' => '## Contenido de PDF' . (!empty($item['title']) ? ": {$item['title']}" : ''),
                'audio' => '## Transcripción de audio' . (!empty($item['duration']) ? " ({$item['duration']})" : ''),
                'images' => '## Análisis de imágenes' . (!empty($item['count']) ? " ({$item['count']} imágenes)" : ''),
                default => '## Contenido adicional'
            };
            
            $sections[] = $header . "\n\n" . $item['content'];
        }
        
        return implode("\n\n---\n\n", $sections);
    }
    
    /**
     * Genera el SOP en formato Markdown estructurado
     */
    private function generateMarkdown(string $content, string $title): array
    {
        $prompt = <<<PROMPT
Eres un experto en documentación de procesos y procedimientos operativos estándar (SOPs). Tu tarea es transformar el siguiente contenido en bruto en un SOP profesional y bien estructurado.

## CONTENIDO A TRANSFORMAR

{$content}

---

## INSTRUCCIONES

Genera un SOP completo con la siguiente estructura:

### 1. ENCABEZADO
- **Título del procedimiento**: {$title}
- **Objetivo**: Descripción clara del propósito del procedimiento
- **Alcance**: A quién aplica y en qué contexto
- **Responsables**: Roles involucrados (si se mencionan)

### 2. REQUISITOS PREVIOS
- Lista de materiales, herramientas, accesos o conocimientos necesarios antes de iniciar
- Usar formato de checklist: `- [ ] Requisito`

### 3. PROCEDIMIENTO
- Pasos numerados y detallados
- Cada paso debe ser una acción concreta y verificable
- Incluir sub-pasos si es necesario
- Si hay decisiones/bifurcaciones, indicarlas claramente
- Formato: `1. **Acción**: Descripción detallada`

### 4. PUNTOS DE CONTROL / VERIFICACIÓN
- Checkpoints para asegurar que el proceso va bien
- Resultados esperados en cada etapa crítica

### 5. SOLUCIÓN DE PROBLEMAS (si aplica)
- Problemas comunes y sus soluciones
- Formato tabla o lista

### 6. NOTAS Y ADVERTENCIAS
- Tips importantes
- Precauciones
- Excepciones

## REGLAS
- Usa español de España
- Sé preciso y profesional
- No inventes información que no esté en el contenido original
- Si algo no está claro, indica que necesita definirse
- Usa formato Markdown con headers, listas, checkboxes y negrita para énfasis

GENERA EL SOP AHORA:
PROMPT;

        return $this->callLlm($prompt);
    }
    
    /**
     * Genera un diagrama de flujo en formato Mermaid
     */
    private function generateMermaid(string $sopMarkdown, string $title): array
    {
        $prompt = <<<PROMPT
Analiza el siguiente SOP y genera un diagrama de flujo en formato Mermaid que represente visualmente el proceso.

## SOP A ANALIZAR

{$sopMarkdown}

---

## INSTRUCCIONES

Genera SOLO el código Mermaid (sin bloques de código markdown) siguiendo estas reglas:

1. Usa sintaxis `flowchart TD` (top-down)
2. Nodos de inicio/fin: `([texto])` - forma de estadio
3. Pasos normales: `[texto]` - rectángulo
4. Decisiones: `{texto}` - rombo
5. Sub-procesos: `[[texto]]` - rectángulo doble
6. Conecta con `-->` y añade etiquetas si hay decisiones: `-->|Sí|`
7. Usa IDs cortos para nodos: A, B, C... o step1, step2...
8. Máximo 15-20 nodos para mantener legibilidad
9. Agrupa pasos relacionados si hay muchos
10. No uses caracteres especiales que rompan Mermaid (evita comillas, paréntesis en texto de nodos)

## EJEMPLO DE FORMATO

flowchart TD
    A([Inicio]) --> B[Paso 1: Preparar materiales]
    B --> C{Materiales completos?}
    C -->|Sí| D[Paso 2: Ejecutar proceso]
    C -->|No| E[Obtener materiales faltantes]
    E --> B
    D --> F[Paso 3: Verificar resultado]
    F --> G{Resultado OK?}
    G -->|Sí| H([Fin])
    G -->|No| I[Corregir errores]
    I --> D

GENERA SOLO EL CÓDIGO MERMAID:
PROMPT;

        $result = $this->callLlm($prompt);
        
        if (!$result['success']) {
            return $result;
        }
        
        // Limpiar el resultado (quitar bloques de código si los hay)
        $mermaid = $result['content'];
        $mermaid = preg_replace('/^```mermaid?\s*/i', '', $mermaid);
        $mermaid = preg_replace('/\s*```$/i', '', $mermaid);
        $mermaid = trim($mermaid);
        
        // Validar que empieza con flowchart
        if (!preg_match('/^(flowchart|graph)\s+(TD|TB|BT|LR|RL)/i', $mermaid)) {
            // Intentar arreglar
            if (!str_starts_with($mermaid, 'flowchart') && !str_starts_with($mermaid, 'graph')) {
                $mermaid = "flowchart TD\n" . $mermaid;
            }
        }
        
        return [
            'success' => true,
            'content' => $mermaid
        ];
    }
    
    /**
     * Llama al LLM con el prompt dado
     */
    private function callLlm(string $prompt): array
    {
        if (empty($this->apiKey)) {
            return ['success' => false, 'error' => 'Falta OPENROUTER_API_KEY'];
        }
        
        $payload = [
            'model' => $this->model,
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 8192
        ];
        
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
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            CURLOPT_TIMEOUT => 180,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);
        
        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($curlError) {
            return ['success' => false, 'error' => 'Error de conexión: ' . $curlError];
        }
        
        if (!$response) {
            return ['success' => false, 'error' => 'No se recibió respuesta'];
        }
        
        $data = json_decode($response, true);
        
        if (isset($data['error'])) {
            $errorMsg = $data['error']['message'] ?? 'Error desconocido';
            return ['success' => false, 'error' => 'Error de API: ' . $errorMsg];
        }
        
        if ($httpCode !== 200) {
            return ['success' => false, 'error' => "Error HTTP {$httpCode}"];
        }
        
        $content = $data['choices'][0]['message']['content'] ?? '';
        
        if (empty($content)) {
            return ['success' => false, 'error' => 'Respuesta vacía'];
        }
        
        return [
            'success' => true,
            'content' => trim($content)
        ];
    }
}
