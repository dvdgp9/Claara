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
            'google/google/gemini-3-flash-preview',
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

INSTRUCCIONES ESPECÍFICAS:
1. Crea un email atractivo y escaneable
2. Asunto impactante (si no se proporcionó)
3. Preheader (texto de preview, 40-100 caracteres)
4. Saludo personalizable con [NOMBRE]
5. Cuerpo estructurado con secciones claras
6. Llamadas a la acción claras (botones)
7. Tono cercano pero profesional
8. Firma corporativa sugerida

ESTRUCTURA:
---EMAIL---
ASUNTO: [Línea de asunto]
PREHEADER: [Texto de preview]

---CUERPO---
[Contenido del email en formato HTML simple]
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
