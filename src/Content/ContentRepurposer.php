<?php
namespace Content;

use Chat\OpenRouterClient;

/**
 * Generador de contenido adaptado a diferentes formatos
 * Transforma contenido fuente en: posts sociales, blogs, landing pages, newsletters, FAQs
 */
class ContentRepurposer
{
    private OpenRouterClient $llmClient;

    private const OUTPUT_FORMATS = [
        'instagram' => [
            'name' => 'Post Instagram',
            'icon' => 'instagram',
            'description' => 'Post visual con emojis y hashtags'
        ],
        'facebook' => [
            'name' => 'Post Facebook',
            'icon' => 'facebook',
            'description' => 'Publicación para Facebook'
        ],
        'linkedin' => [
            'name' => 'Post LinkedIn',
            'icon' => 'linkedin',
            'description' => 'Contenido profesional'
        ],
        'twitter' => [
            'name' => 'Post X (Twitter)',
            'icon' => 'twitter',
            'description' => 'Tweet o hilo de tweets'
        ],
        'blog' => [
            'name' => 'Entrada de blog',
            'icon' => 'blog',
            'description' => 'Artículo completo con estructura SEO'
        ],
        'landing' => [
            'name' => 'Landing Page',
            'icon' => 'landing',
            'description' => 'Código HTML/CSS/JS listo para usar'
        ],
        'newsletter' => [
            'name' => 'Newsletter',
            'icon' => 'newsletter',
            'description' => 'Email para envío masivo'
        ],
        'faq' => [
            'name' => 'Preguntas Frecuentes',
            'icon' => 'faq',
            'description' => 'Lista de FAQs estructuradas'
        ]
    ];

    public function __construct(?OpenRouterClient $llmClient = null)
    {
        $this->llmClient = $llmClient ?? new OpenRouterClient(
            null,
            'google/gemini-3-flash-preview',
            null,
            0.7,
            16384
        );
    }

    /**
     * Obtiene los formatos de salida disponibles
     */
    public static function getOutputFormats(): array
    {
        return self::OUTPUT_FORMATS;
    }

    /**
     * Genera contenido en el formato especificado
     * 
     * @param string $content Contenido fuente
     * @param string $format Formato de salida (instagram, facebook, linkedin, twitter, blog, landing, newsletter, faq)
     * @param string $title Título del contenido original (opcional)
     * @param array $options Opciones adicionales (tono, longitud, etc.)
     * @return array ['success' => bool, 'output' => string, 'format' => string, 'error' => string|null]
     */
    public function generate(string $content, string $format, string $title = '', array $options = []): array
    {
        if (!isset(self::OUTPUT_FORMATS[$format])) {
            return ['success' => false, 'error' => "Formato no soportado: {$format}"];
        }

        $wordCount = str_word_count($content);
        if ($wordCount < 20) {
            return ['success' => false, 'error' => 'El contenido es demasiado corto (mínimo 20 palabras)'];
        }

        $prompt = $this->buildPrompt($content, $format, $title, $options);

        try {
            $response = $this->llmClient->generateText($prompt);
            
            if (empty($response)) {
                return ['success' => false, 'error' => 'No se pudo generar el contenido'];
            }

            return [
                'success' => true,
                'output' => $response,
                'format' => $format,
                'format_name' => self::OUTPUT_FORMATS[$format]['name'],
                'model' => $this->llmClient->getModel()
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error generando contenido: ' . $e->getMessage()];
        }
    }

    /**
     * Genera contenido en múltiples formatos
     * 
     * @param string $content Contenido fuente
     * @param array $formats Array de formatos a generar
     * @param string $title Título del contenido original
     * @param array $options Opciones adicionales
     * @return array ['success' => bool, 'results' => array, 'errors' => array]
     */
    public function generateMultiple(string $content, array $formats, string $title = '', array $options = []): array
    {
        $results = [];
        $errors = [];

        foreach ($formats as $format) {
            $result = $this->generate($content, $format, $title, $options);
            
            if ($result['success']) {
                $parsed = $this->parseOutput($result['output'], $format);
                $results[$format] = [
                    'format' => $format,
                    'format_name' => self::OUTPUT_FORMATS[$format]['name'] ?? $format,
                    'icon' => self::OUTPUT_FORMATS[$format]['icon'] ?? 'document',
                    'raw' => $result['output'],
                    'parsed' => $parsed,
                    'model' => $result['model']
                ];
            } else {
                $errors[$format] = $result['error'];
            }
        }

        return [
            'success' => count($results) > 0,
            'results' => $results,
            'errors' => $errors,
            'total_generated' => count($results),
            'total_failed' => count($errors)
        ];
    }

    /**
     * Parsea el output estructurado del LLM según el formato
     */
    public function parseOutput(string $output, string $format): array
    {
        $parsed = ['raw' => $output];

        switch ($format) {
            case 'instagram':
                $parsed['caption'] = $this->extractSection($output, 'CAPTION');
                $parsed['hashtags'] = $this->extractSection($output, 'HASHTAGS');
                $parsed['visual'] = $this->extractSection($output, 'VISUAL_SUGERIDO');
                break;

            case 'facebook':
                $parsed['post'] = $this->extractSection($output, 'POST');
                $parsed['suggestions'] = $this->extractSection($output, 'SUGERENCIAS');
                break;

            case 'linkedin':
                $parsed['post'] = $this->extractSection($output, 'POST');
                $parsed['hashtags'] = $this->extractSection($output, 'HASHTAGS');
                break;

            case 'twitter':
                $parsed['tweets'] = $this->extractTweets($output);
                $parsed['hashtags'] = $this->extractSection($output, 'HASHTAGS');
                break;

            case 'blog':
                $parsed['meta'] = $this->extractSection($output, 'META');
                $parsed['article'] = $this->extractSection($output, 'ARTICULO');
                // Parse meta fields
                if ($parsed['meta']) {
                    preg_match('/Título SEO:\s*(.+)/i', $parsed['meta'], $m);
                    $parsed['seo_title'] = trim($m[1] ?? '');
                    preg_match('/Meta descripción:\s*(.+)/i', $parsed['meta'], $m);
                    $parsed['meta_description'] = trim($m[1] ?? '');
                    preg_match('/Keywords:\s*(.+)/i', $parsed['meta'], $m);
                    $parsed['keywords'] = trim($m[1] ?? '');
                }
                break;

            case 'landing':
                $parsed['html'] = $this->extractSection($output, 'HTML');
                $parsed['notes'] = $this->extractSection($output, 'NOTAS');
                break;

            case 'newsletter':
                $fullEmail = $this->extractSection($output, 'EMAIL');
                if ($fullEmail) {
                    preg_match('/ASUNTO:\s*(.+)/i', $fullEmail, $m);
                    $parsed['subject'] = trim($m[1] ?? '');
                    preg_match('/PREHEADER:\s*(.+)/i', $fullEmail, $m);
                    $parsed['preheader'] = trim($m[1] ?? '');
                }
                $parsed['body'] = $this->extractSection($output, 'CUERPO');
                $parsed['plain_text'] = $this->extractSection($output, 'TEXTO_PLANO');
                break;

            case 'faq':
                $parsed['faqs'] = $this->extractSection($output, 'FAQS');
                $parsed['schema'] = $this->extractSection($output, 'SCHEMA_JSON');
                // Parse individual FAQs
                if ($parsed['faqs']) {
                    preg_match_all('/\*\*P:\s*(.+?)\*\*\s*\n\s*R:\s*(.+?)(?=\n\n|\*\*P:|$)/s', $parsed['faqs'], $matches, PREG_SET_ORDER);
                    $parsed['faq_items'] = array_map(fn($m) => ['question' => trim($m[1]), 'answer' => trim($m[2])], $matches);
                }
                break;
        }

        return $parsed;
    }

    /**
     * Extrae una sección delimitada del output
     */
    private function extractSection(string $output, string $section): string
    {
        $pattern = "/---{$section}---\s*(.*?)\s*---FIN_{$section}---/is";
        if (preg_match($pattern, $output, $matches)) {
            return trim($matches[1]);
        }
        return '';
    }

    /**
     * Extrae tweets individuales de un hilo
     */
    private function extractTweets(string $output): array
    {
        $tweets = [];
        $i = 1;
        while (true) {
            $pattern = "/---TWEET_{$i}---\s*(.*?)\s*---FIN_TWEET---/is";
            if (preg_match($pattern, $output, $matches)) {
                $tweets[] = trim($matches[1]);
                $i++;
            } else {
                break;
            }
        }
        return $tweets;
    }

    /**
     * Construye el prompt según el formato de salida
     */
    private function buildPrompt(string $content, string $format, string $title, array $options): string
    {
        $tone = $options['tone'] ?? 'profesional';
        $language = $options['language'] ?? 'es';
        $titleSection = $title ? "TÍTULO DEL CONTENIDO ORIGINAL: {$title}\n\n" : '';

        $baseContext = <<<CONTEXT
Eres un experto en marketing de contenidos y copywriting. Tu tarea es transformar el siguiente contenido en un formato específico.

{$titleSection}CONTENIDO FUENTE:
---
{$content}
---

TONO: {$tone}
IDIOMA: Español de España (peninsular)

REGLAS GENERALES:
- Mantén la esencia y los puntos clave del contenido original
- Adapta el lenguaje al formato y plataforma destino
- NO inventes datos, cifras o información que no esté en el contenido fuente
- Sé conciso pero completo
- Usa español de España (vosotros, expresiones peninsulares)
CONTEXT;

        return match($format) {
            'instagram' => $this->buildInstagramPrompt($baseContext),
            'facebook' => $this->buildFacebookPrompt($baseContext),
            'linkedin' => $this->buildLinkedInPrompt($baseContext),
            'twitter' => $this->buildTwitterPrompt($baseContext),
            'blog' => $this->buildBlogPrompt($baseContext, $options),
            'landing' => $this->buildLandingPrompt($baseContext, $options),
            'newsletter' => $this->buildNewsletterPrompt($baseContext, $options),
            'faq' => $this->buildFaqPrompt($baseContext),
            default => $baseContext
        };
    }

    private function buildInstagramPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: POST DE INSTAGRAM

INSTRUCCIONES ESPECÍFICAS:
1. Crea un caption atractivo y visual (máximo 2200 caracteres, ideal 150-300)
2. Comienza con un gancho potente (pregunta, dato impactante, o frase memorable)
3. Estructura en párrafos cortos con saltos de línea
4. Usa emojis estratégicamente (no excesivo, 3-8 emojis)
5. Incluye una llamada a la acción clara (CTA)
6. Añade 20-30 hashtags relevantes al final, organizados por relevancia
7. Sugiere un tipo de imagen/visual que acompañe el post

ESTRUCTURA:
---CAPTION---
[Caption aquí]
---FIN_CAPTION---

---HASHTAGS---
[Hashtags aquí]
---FIN_HASHTAGS---

---VISUAL_SUGERIDO---
[Descripción del visual ideal]
---FIN_VISUAL---
PROMPT;
    }

    private function buildFacebookPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: POST DE FACEBOOK

INSTRUCCIONES ESPECÍFICAS:
1. Crea una publicación conversacional y cercana (300-500 palabras ideal)
2. Comienza con algo que capte atención en el feed
3. Desarrolla el mensaje con párrafos legibles
4. Incluye preguntas para fomentar comentarios
5. Añade una llamada a la acción
6. Sugiere si incluir enlace, imagen o video

ESTRUCTURA:
---POST---
[Contenido del post aquí]
---FIN_POST---

---SUGERENCIAS---
[Sugerencias de formato: imagen, video, enlace, encuesta, etc.]
---FIN_SUGERENCIAS---
PROMPT;
    }

    private function buildLinkedInPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: POST DE LINKEDIN

INSTRUCCIONES ESPECÍFICAS:
1. Tono profesional pero accesible
2. Comienza con un gancho en las primeras 2 líneas (antes del "ver más")
3. Estructura con espacios y párrafos cortos para escaneo rápido
4. Incluye insights o aprendizajes valiosos
5. Usa bullet points si es apropiado
6. Termina con pregunta para generar engagement
7. Máximo 3000 caracteres, ideal 1300-1500

ESTRUCTURA:
---POST---
[Contenido del post aquí]
---FIN_POST---

---HASHTAGS---
[3-5 hashtags profesionales relevantes]
---FIN_HASHTAGS---
PROMPT;
    }

    private function buildTwitterPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: POST PARA X (TWITTER)

INSTRUCCIONES ESPECÍFICAS:
1. Si el contenido es breve: crea un único tweet (máximo 280 caracteres)
2. Si es extenso: crea un HILO (thread) numerado
3. Cada tweet debe tener sentido por sí solo
4. Primer tweet = gancho impactante
5. Usa formato visual: saltos de línea, emojis estratégicos
6. Último tweet: CTA o reflexión final

ESTRUCTURA:
---TWEET_1---
[Contenido tweet 1]
---FIN_TWEET---

---TWEET_2---
[Contenido tweet 2, si aplica]
---FIN_TWEET---

(Continúa con más tweets si es necesario)

---HASHTAGS---
[2-3 hashtags máximo para Twitter]
---FIN_HASHTAGS---
PROMPT;
    }

    private function buildBlogPrompt(string $context, array $options): string
    {
        $wordTarget = $options['word_count'] ?? 800;
        
        return $context . <<<PROMPT


FORMATO DE SALIDA: ENTRADA DE BLOG (SEO OPTIMIZADA)

INSTRUCCIONES ESPECÍFICAS:
1. Extensión objetivo: ~{$wordTarget} palabras
2. Título H1 atractivo y con keyword principal
3. Meta descripción (150-160 caracteres)
4. Estructura con H2 y H3 para escaneo
5. Introducción que enganche (hook + promesa)
6. Desarrollo con ejemplos y datos del contenido fuente
7. Conclusión con CTA
8. Usa listas y negritas para facilitar lectura
9. Incluye sugerencias de keywords secundarias

ESTRUCTURA:
---META---
Título SEO: [título]
Meta descripción: [descripción]
Keywords: [keyword1, keyword2, keyword3]
---FIN_META---

---ARTICULO---
# [Título H1]

[Contenido completo del artículo con formato Markdown]
---FIN_ARTICULO---
PROMPT;
    }

    private function buildLandingPrompt(string $context, array $options): string
    {
        $style = $options['style'] ?? 'moderno';
        
        return $context . <<<PROMPT


FORMATO DE SALIDA: LANDING PAGE (HTML/CSS/JS)

INSTRUCCIONES ESPECÍFICAS:
1. Crea una landing page completa y funcional
2. Estilo: {$style}, profesional, responsive
3. Usa TailwindCSS (CDN) para estilos
4. Estructura: Hero → Beneficios → Detalles → CTA
5. Incluye animaciones sutiles con CSS o JS vanilla
6. Colores: usa una paleta profesional coherente
7. El código debe ser autónomo y listo para usar

ESTRUCTURA:
---HTML---
<!DOCTYPE html>
<html lang="es">
<!-- Código HTML completo aquí -->
</html>
---FIN_HTML---

---NOTAS---
[Instrucciones de personalización si las hay]
---FIN_NOTAS---
PROMPT;
    }

    private function buildNewsletterPrompt(string $context, array $options): string
    {
        $subject = $options['subject'] ?? '';
        $subjectLine = $subject ? "ASUNTO SUGERIDO: {$subject}\n" : '';
        
        return $context . <<<PROMPT


FORMATO DE SALIDA: NEWSLETTER (EMAIL)

{$subjectLine}

INSTRUCCIONES ESPECÍFICAS DE TONO Y ESTILO:
1. ENFOQUE PERSONALIZADO: Escribe como si enviaras este correo a un amigo o colega cercano. No hables "a la masa" o a "nuestros suscriptores". Usa la primera persona del plural ("Nosotros") y dirígete directamente a la segunda ("Tú").
2. CONSCIENCIA DEL CONTEXTO: En lugar de solo resumir información, aporta una perspectiva de "por qué esto te importa a TI hoy".
3. EMPATÍA: Reconoce los posibles retos o intereses del lector relacionados con el contenido.
4. LENGUAJE CERCANO: Evita tecnicismos innecesarios o un tono excesivamente corporativo. Sé directo, cálido y auténtico.
5. SALUDO: Usa obligatoriamente [NOMBRE] para que parezca una conversación personal.

INSTRUCCIONES DE ESTRUCTURA:
1. Asunto: Que sea curioso, personal o que resuelva un problema inmediato.
2. Preheader: Una extensión natural del asunto que invite a abrir.
3. Saludo personalizable: "Hola [NOMBRE],"
4. Intro: Empieza con una frase que conecte con el lector antes de entrar en materia.
5. Cuerpo: Desarrolla el contenido fuente de forma narrativa (storytelling).
6. CTA: No uses "Haz clic aquí". Usa frases que inviten a continuar la conversación o explorar el beneficio.
7. Despedida: Cercana y humana (ej: "Un abrazo", "Hablamos pronto").

ESTRUCTURA:
---EMAIL---
ASUNTO: [Línea de asunto]
PREHEADER: [Texto de preview]

---CUERPO---
[Contenido del email en formato Markdown/HTML simple]
---FIN_CUERPO---

---TEXTO_PLANO---
[Versión texto plano del email]
---FIN_TEXTO_PLANO---
---FIN_EMAIL---
PROMPT;
    }

    private function buildFaqPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: PREGUNTAS FRECUENTES (FAQs)

INSTRUCCIONES ESPECÍFICAS:
1. Extrae las preguntas más probables que haría un usuario
2. Genera 5-10 FAQs relevantes
3. Respuestas concisas pero completas (2-4 frases)
4. Ordena por importancia/frecuencia probable
5. Usa lenguaje claro y directo
6. Incluye categorías si hay variedad de temas

ESTRUCTURA:
---FAQS---
## [Categoría 1 si aplica]

**P: [Pregunta 1]**
R: [Respuesta 1]

**P: [Pregunta 2]**
R: [Respuesta 2]

[Continúa con más preguntas...]
---FIN_FAQS---

---SCHEMA_JSON---
[Schema JSON-LD para FAQPage - opcional para SEO]
---FIN_SCHEMA---
PROMPT;
    }
}
