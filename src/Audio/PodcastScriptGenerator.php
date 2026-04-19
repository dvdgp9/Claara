<?php
namespace Audio;

use Chat\OpenRouterClient;

/**
 * Genera guiones de podcast en formato diálogo a partir de contenido de artículos
 */
class PodcastScriptGenerator
{
    private OpenRouterClient $llmClient;
    private string $speaker1 = 'Iris';
    private string $speaker2 = 'Bruno';

    public function __construct(?OpenRouterClient $llmClient = null)
    {
        $this->llmClient = $llmClient ?? new OpenRouterClient(
            null,
            'google/gemini-3-flash-preview',
            null,
            0.8,  // Mayor creatividad
            16384 // Más tokens para guiones largos
        );
    }

    /**
     * Genera un guion de podcast a partir del contenido de un artículo
     * 
     * @param string $content El contenido del artículo
     * @param string $title Título del artículo (opcional)
     * @param int $targetMinutes Duración objetivo en minutos
     * @return array ['success' => bool, 'script' => string, 'summary' => string, 'error' => string|null]
     */
    public function generate(string $content, string $title = '', int $targetMinutes = 10): array
    {
        $wordCount = str_word_count($content);
        
        // Estimar palabras del guion según duración objetivo
        // ~150 palabras por minuto hablado
        $targetWords = $targetMinutes * 150;
        
        // Ajustar si el artículo es muy corto
        if ($wordCount < 100) {
            return ['success' => false, 'error' => 'El artículo es demasiado corto para generar un podcast (mínimo ~100 palabras)'];
        }
        
        if ($wordCount < $targetWords / 2) {
            $targetMinutes = max(3, (int)($wordCount / 75)); // Mínimo 3 minutos
        }

        $prompt = $this->buildPrompt($content, $title, $targetMinutes);

        try {
            $response = $this->llmClient->generateText($prompt);
            
            // Parsear la respuesta
            $script = $this->parseScript($response);
            $summary = $this->extractSummary($response);

            if (empty($script)) {
                return ['success' => false, 'error' => 'No se pudo generar el guion del podcast'];
            }

            return [
                'success' => true,
                'script' => $script,
                'summary' => $summary,
                'speaker1' => $this->speaker1,
                'speaker2' => $this->speaker2,
                'estimated_duration' => $this->estimateDuration($script)
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error generando guion: ' . $e->getMessage()];
        }
    }

    /**
     * Construye el prompt para generar el guion
     */
    private function buildPrompt(string $content, string $title, int $targetMinutes): string
    {
        $titleSection = $title ? "TÍTULO DEL ARTÍCULO: {$title}\n\n" : '';
        $targetWords = $this->calculateTargetWords($targetMinutes);
        
        return <<<PROMPT
Eres un guionista experto en podcasts divulgativos de altísima calidad, estilo NotebookLM o "Deep Dive". Tu trabajo es transformar artículos técnicos en conversaciones naturales, profundas y entretenidas.
El guion será locutado con Gemini 3.1 Flash TTS, así que debes escribir un transcript limpio, expresivo y fácil de interpretar por un modelo de voz multi-speaker.

{$titleSection}CONTENIDO A TRANSFORMAR:
---
{$content}
---

═══════════════════════════════════════════════════════════════
OBJETIVO: Generar un guion de podcast de {$targetMinutes} minutos (~{$targetWords} palabras)
entre {$this->speaker1} (mujer, presentadora principal, español de españa, peninsular) y {$this->speaker2} (hombre, co-presentador experto, español de españa, peninsular).
═══════════════════════════════════════════════════════════════

## ESTILO CONVERSACIONAL (MUY IMPORTANTE)

El podcast debe sonar como DOS EXPERTOS AMIGOS charlando en un bar, no como presentadores leyendo un guion. Incluye OBLIGATORIAMENTE:

1. **REACCIONES CORTAS INTERCALADAS** - Esenciales para naturalidad:
   - "Exacto.", "Sí.", "Vale.", "Claro.", "Totalmente.", "Eso es."
   - "Uff.", "Vaya.", "Madre mía.", "Fíjate.", "Ah, vale."
   - "¿En serio?", "¿Uno solo?", "Espera, espera..."
   - Estas reacciones deben aparecer FRECUENTEMENTE (cada 2-3 intervenciones largas)

2. **ANÉCDOTAS PERSONALES FICTICIAS** - Hacen humano el contenido:
   - "Yo recuerdo una vez que me pasé horas con este problema..."
   - "Me pasó algo parecido el otro día..."
   - "Un compañero mío siempre dice que..."

3. **METÁFORAS Y ANALOGÍAS VÍVIDAS**:
   - "Es como tener los ladrillos pero no el pegamento"
   - "Es la diferencia entre buscar una aguja en un pajar y preguntarle a Google"
   - "Es como el fontanero: instala las tuberías pero no decide qué agua pasa"

4. **EXPRESIONES COLOQUIALES ESPAÑOLAS**:
   - "la madre del cordero", "el pan de cada día", "el quid de la cuestión" (español peninsular, ir variando expresiones y añadir otras nuevas, no usar solo las de aquí)
   - "no se anda con chiquitas", "vamos al grano", "atar cabos" (español peninsular)
   - "el marrón de turno", "quedarse flipando", "eso es otro cantar" (español peninsular)

5. **PREGUNTAS RETÓRICAS Y PAUSAS**:
   - "¿Y cuál es el problema? Pues que..."
   - "Piénsalo un momento: si tienes X, ¿cómo vas a...?"
   - "La pregunta del millón es..."

6. **INTERRUPCIONES NATURALES**:
   - Uno puede cortar al otro para añadir algo
   - "Espera, que esto es importante..."
   - "Perdona que te corte, pero..."

## ESTRUCTURA

1. **APERTURA (30-45 seg)**: Saludo (el podcast se llamará "El Pulso de Ebonia" + gancho provocador sobre el tema.
2. **DESARROLLO (85% del tiempo)**: 
   - Explorar cada concepto EN PROFUNDIDAD
   - No solo explicar QUÉ, sino POR QUÉ importa
   - Dar ejemplos concretos y escenarios reales
   - Hacer preguntas entre ellos que profundicen
3. **CIERRE (30-45 seg)**: Reflexión que invite a pensar + despedida cálida

## ROLES

- **{$this->speaker1}**: Introduce temas, hace preguntas inteligentes, resume puntos clave, conecta ideas
- **{$this->speaker2}**: Aporta profundidad técnica, cuenta anécdotas, usa analogías, responde con detalle

## DIRECCIÓN PARA GEMINI 3.1 TTS

- Puedes usar tags de audio en inglés entre corchetes para modular la interpretación: [warmly], [curious], [thoughtful], [serious], [short pause], [light laugh], [softly].
- Usa tags con moderación: máximo uno cada 4-6 intervenciones, y solo cuando ayuden a la naturalidad.
- Pon [short pause] antes de ideas importantes, cambios de sección o cierres reflexivos.
- Si hay emoción, escríbela en el texto y apóyala con un tag breve; no fuerces dramatismo.
- No uses acotaciones narrativas fuera de las líneas de {$this->speaker1} y {$this->speaker2}. Todo debe estar dentro del diálogo.
- Mantén siempre español de España en el texto hablado; los tags deben quedarse en inglés para que TTS los interprete mejor.

## REGLAS CRÍTICAS

- SOLO información del artículo. NUNCA inventar datos, cifras, fechas o nombres reales.
- Español de España (vosotros, expresiones peninsulares, acento español peninsular)
- Variar la longitud de intervenciones: algunas largas (3-5 frases), otras cortísimas (1 palabra)
- El guion debe ser MUCHO más largo que un resumen: desarrollar, no resumir
- No escribir instrucciones tipo "risas", "pausa" o "música" fuera de tags de audio entre corchetes.

## EJEMPLO DEL ESTILO DESEADO

{$this->speaker1}: [warmly] Hoy vamos a analizar un tema que es casi una provocación: por qué nuestros sistemas actuales están fallando.
{$this->speaker2}: Y ojo, no es que fallen con mala intención. Es algo peor: la idea es que son fundamentalmente inadecuados para los problemas de hoy. Yo recuerdo una vez que me pasé horas intentando encontrar un bug, solo para darme cuenta de que el problema venía de un sitio que ni sabía que existía.
{$this->speaker1}: Esa historia es el pan de cada día para muchísima gente.
{$this->speaker2}: Exacto.
{$this->speaker1}: Y el problema de fondo es que estamos usando herramientas del siglo XX para problemas del siglo XXI.
{$this->speaker2}: Sí, muy simple en teoría.
{$this->speaker1}: Pero es que hoy un solo clic puede desencadenar una reacción en cadena que toque veinte sistemas distintos. Y nuestras herramientas siguen siendo, en esencia, las mismas de antes.
{$this->speaker2}: La madre del cordero, vamos. El autor lo describe genial: dice que es como jugar a los detectives con una mano atada a la espalda.
{$this->speaker1}: Me encanta esa analogía.

## FORMATO DE SALIDA

Primero un resumen breve (1-2 líneas):
---RESUMEN---
[Resumen aquí]
---FIN_RESUMEN---

Luego el guion completo:
---GUION---
{$this->speaker1}: [Texto, con tags de audio solo si aportan naturalidad]
{$this->speaker2}: [Texto]
...
---FIN_GUION---

GENERA EL GUION COMPLETO AHORA. Recuerda: debe ser largo, profundo, natural y entretenido.
PROMPT;
    }

    /**
     * Calcula palabras objetivo según minutos
     */
    private function calculateTargetWords(int $minutes): int
    {
        return $minutes * 150;
    }

    /**
     * Parsea el guion de la respuesta
     */
    private function parseScript(string $response): string
    {
        // Buscar el guion entre marcadores
        if (preg_match('/---GUION---\s*([\s\S]*?)\s*---FIN_GUION---/i', $response, $matches)) {
            return trim($matches[1]);
        }
        
        // Fallback: buscar líneas que empiecen con los nombres de los speakers
        $lines = [];
        $pattern = '/^(' . preg_quote($this->speaker1, '/') . '|' . preg_quote($this->speaker2, '/') . '):\s*(.+)$/m';
        
        if (preg_match_all($pattern, $response, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $lines[] = $match[0];
            }
            return implode("\n", $lines);
        }

        return '';
    }

    /**
     * Extrae el resumen de la respuesta
     */
    private function extractSummary(string $response): string
    {
        if (preg_match('/---RESUMEN---\s*([\s\S]*?)\s*---FIN_RESUMEN---/i', $response, $matches)) {
            $sum = $this->normalizeSummary($matches[1]);
            if ($this->isValidSummary($sum)) return $sum;
        }

        if (preg_match('/---RESUMEN---\s*([\s\S]*?)\s*---GUION---/i', $response, $matches)) {
            $sum = $this->normalizeSummary($matches[1]);
            if ($this->isValidSummary($sum)) return $sum;
        }

        if (preg_match('/---RESUMEN---\s*([\s\S]*)$/i', $response, $matches)) {
            $sum = $this->normalizeSummary($matches[1]);
            if ($this->isValidSummary($sum)) return $sum;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $response))));
        foreach ($lines as $l) {
            if ($l === '' || stripos($l, '---') !== false) continue;
            if (preg_match('/^(' . preg_quote($this->speaker1, '/') . '|' . preg_quote($this->speaker2, '/') . '):/i', $l)) continue;
            $sum = $this->normalizeSummary($l);
            if ($this->isValidSummary($sum)) return $sum;
        }

        return 'Podcast generado';
    }

    private function normalizeSummary(string $text): string
    {
        $t = trim($text);
        $t = preg_replace('/\s+/', ' ', $t);
        return mb_substr($t, 0, 200);
    }

    private function isValidSummary(string $text): bool
    {
        if ($text === '' ) return false;
        $lower = mb_strtolower($text);
        if (strpos($lower, '---resumen---') !== false) return false;
        if (strpos($lower, '[resumen aquí]') !== false) return false;
        return true;
    }

    /**
     * Estima la duración del guion en segundos
     */
    private function estimateDuration(string $script): int
    {
        $wordCount = str_word_count($script);
        // ~2.5 palabras por segundo en habla natural
        return (int)($wordCount / 2.5);
    }

    /**
     * Getters para los nombres de los speakers
     */
    public function getSpeaker1(): string
    {
        return $this->speaker1;
    }

    public function getSpeaker2(): string
    {
        return $this->speaker2;
    }

    /**
     * Construye el prompt final para Gemini 3.1 Flash TTS.
     *
     * El guion ya contiene las palabras exactas a locutar; este prompt añade
     * dirección de escena, perfiles y notas para aprovechar el control expresivo
     * del modelo sin cambiar el contenido.
     */
    public function buildTtsPrompt(string $script): string
    {
        return <<<PROMPT
# AUDIO PROFILES
## {$this->speaker1}: presentadora principal
{$this->speaker1} es una presentadora española con voz cálida, clara y cercana. Suena natural, inteligente y con una sonrisa vocal ligera. Lleva la conversación, abre secciones, hace preguntas y resume ideas.

## {$this->speaker2}: co-presentador experto
{$this->speaker2} es un divulgador español con voz serena, segura y didáctica. Aporta profundidad, matices y ejemplos con tono conversacional, no académico.

# THE SCENE
Están grabando "El Pulso de Ebonia" en un estudio de podcast corporativo moderno, tranquilo y bien sonorizado. La conversación debe sonar como un podcast profesional para escuchar en el coche: entretenido, claro, humano y fácil de seguir sin mirar pantalla.

# DIRECTOR'S NOTES
Language and accent:
* Todo el audio debe estar en español de España.
* Usar pronunciación peninsular neutra y natural.
* Evitar acento latinoamericano, vocabulario latinoamericano y entonación de doblaje.

Style:
* Conversación profesional pero cercana, con energía controlada.
* Debe sonar espontáneo, como dos expertos amigos, no como una lectura plana.
* Mantener las reacciones cortas naturales, pero sin sobreactuar.

Pacing:
* Ritmo medio, cómodo para escucha prolongada.
* Pausas breves después de ideas importantes y antes de cambios de sección.
* Acelerar ligeramente en ejemplos o momentos de entusiasmo; bajar el ritmo en conclusiones.

Delivery:
* Respetar exactamente los turnos "{$this->speaker1}:" y "{$this->speaker2}:" del transcript.
* No leer los nombres de los speakers en voz alta.
* Interpretar los tags de audio en inglés entre corchetes como instrucciones de interpretación, no como texto hablado.
* No añadir música, efectos, títulos externos ni contenido que no esté en el transcript.

# TRANSCRIPT
TTS the following podcast conversation between {$this->speaker1} and {$this->speaker2}:

{$script}
PROMPT;
    }
}
