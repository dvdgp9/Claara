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
   - Usa acuerdos, dudas, sorpresa suave, matices y pequeñas réplicas de escucha activa.
   - Deben sentirse espontáneas y variadas, no como una lista repetida.
   - Alterna microintervenciones de una palabra con frases breves, sin abusar de ninguna muletilla.

2. **ANÉCDOTAS PERSONALES FICTICIAS** - Hacen humano el contenido:
   - Puedes incluir pequeñas escenas cotidianas o recuerdos ficticios si ayudan a entender el contenido.
   - No inventes datos del artículo; las anécdotas deben ser genéricas y claramente ilustrativas.
   - Varía el tipo de ejemplo: oficina, equipo, carretera, cocina, aula, deporte, tecnología, trámites.

3. **METÁFORAS Y ANALOGÍAS VÍVIDAS**:
   - Crea analogías nuevas y contextuales para cada episodio.
   - Evita reutilizar fórmulas demasiado reconocibles o comodines genéricos.
   - Si una idea se entiende mejor en lenguaje directo, no fuerces una metáfora.

4. **EXPRESIONES COLOQUIALES ESPAÑOLAS**:
   - Usa español peninsular natural, con expresiones coloquiales solo cuando encajen.
   - No conviertas el podcast en una sucesión de frases hechas.
   - Ninguna expresión coloquial debe repetirse dentro del mismo episodio.
   - Prioriza frescura, precisión y variedad sobre ocurrencias llamativas.

5. **PREGUNTAS RETÓRICAS Y PAUSAS**:
   - Formula preguntas naturales para abrir bloques, profundizar o cambiar de ángulo.
   - Varía las transiciones: pregunta directa, recapitulación, contraste, ejemplo, matiz.
   - Evita usar siempre la misma estructura de apertura o de sorpresa.

6. **INTERRUPCIONES NATURALES**:
   - Uno puede cortar al otro para aclarar, matizar o aterrizar una idea.
   - Que parezca conversación viva, no interrupción teatral.
   - No repitas la misma fórmula de interrupción.

7. **VARIEDAD DE EPISODIO**:
   - Cada podcast debe tener aperturas, transiciones, analogías y cierres distintos.
   - No uses fórmulas de entusiasmo grandilocuentes por defecto.
   - Evita prometer que algo "sorprenderá" si el contenido no lo justifica.

## ESTRUCTURA

1. **APERTURA (30-45 seg)**: Saludo (el podcast se llamará "The iaiaPRO Brief" + gancho provocador sobre el tema.
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
- No apoyarte en expresiones comodín: si una frase hecha aparece, debe ser puntual, contextual y no repetida.

## EJEMPLO DEL ESTILO DESEADO

{$this->speaker1}: [warmly] Hoy traemos un tema que parece técnico, pero que afecta a decisiones muy del día a día.
{$this->speaker2}: Sí, y lo interesante es que el artículo no se queda en la superficie. Plantea una pregunta bastante práctica: qué cambia cuando intentamos aplicar esto en un entorno real.
{$this->speaker1}: Vale, empecemos por ahí. ¿Cuál es la primera idea importante?
{$this->speaker2}: La primera es que no basta con entender el concepto en abstracto. Hay que mirar qué implica para una persona, un equipo o una organización cuando tiene que tomar decisiones con información incompleta.
{$this->speaker1}: [short pause] Y eso ya nos lleva a un punto delicado.
{$this->speaker2}: Claro. Porque en teoría todo parece ordenado, pero en la práctica aparecen prioridades, plazos, dudas y pequeñas excepciones.
{$this->speaker1}: Ahí es donde el tema empieza a tener vida.

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
     * Elimina tags de audio tipo [warmly] o [short pause] para la transcripción visible.
     */
    public static function cleanAudioTags(string $script): string
    {
        $clean = preg_replace('/[ \t]*\[[a-zA-Z][a-zA-Z \t,\-]*\][ \t]*/u', ' ', $script);
        $clean = preg_replace('/[ \t]+/u', ' ', $clean);
        $clean = preg_replace('/\s+\n/u', "\n", $clean);
        $clean = preg_replace('/\n\s+/u', "\n", $clean);
        return trim($clean);
    }

    /**
     * Divide el guion en bloques manteniendo turnos completos de speaker.
     *
     * @return array<int, string>
     */
    public function splitScriptForTts(string $script, int $targetWords = 320, int $maxWords = 430): array
    {
        $turns = $this->extractTurns($script);
        if (empty($turns)) {
            return [trim($script)];
        }

        $segments = [];
        $current = [];
        $currentWords = 0;

        foreach ($turns as $turn) {
            $turnWords = str_word_count(PodcastScriptGenerator::cleanAudioTags($turn));
            $wouldExceed = $currentWords > 0 && ($currentWords + $turnWords) > $maxWords;
            $hasReachedTarget = $currentWords >= $targetWords;

            if ($wouldExceed || ($hasReachedTarget && $turnWords > 0)) {
                $segments[] = trim(implode("\n", $current));
                $current = [];
                $currentWords = 0;
            }

            $current[] = $turn;
            $currentWords += $turnWords;
        }

        if (!empty($current)) {
            $segments[] = trim(implode("\n", $current));
        }

        return array_values(array_filter($segments));
    }

    /**
     * @return array<int, string>
     */
    private function extractTurns(string $script): array
    {
        $pattern = '/(?=^(' . preg_quote($this->speaker1, '/') . '|' . preg_quote($this->speaker2, '/') . '):)/m';
        $parts = preg_split($pattern, trim($script), -1, PREG_SPLIT_NO_EMPTY);
        if (!$parts) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts), function ($part) {
            return preg_match('/^(' . preg_quote($this->speaker1, '/') . '|' . preg_quote($this->speaker2, '/') . '):/u', $part) === 1;
        }));
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
        return $this->buildTtsPromptForSegment($script, null, null);
    }

    /**
     * Construye el prompt TTS para un segmento. Se usa para generar podcasts largos
     * por bloques y evitar deriva de calidad en audios de muchos minutos.
     */
    public function buildTtsPromptForSegment(string $script, ?int $segmentIndex = null, ?int $totalSegments = null): string
    {
        $segmentNote = '';
        if ($segmentIndex !== null && $totalSegments !== null && $totalSegments > 1) {
            $segmentNote = "\nSegment continuity:\n* Este es el segmento {$segmentIndex} de {$totalSegments} de un mismo podcast.\n* Mantén exactamente la misma presencia de estudio, distancia de micro, timbre, claridad, volumen percibido y energía que en el resto de segmentos.\n* No sonar lejos, amortiguado, reverberante, filtrado, dentro de un vaso ni como si las voces estuvieran en otra habitación.\n";
        }

        return <<<PROMPT
# AUDIO PROFILES
## {$this->speaker1}: presentadora principal
{$this->speaker1} es una presentadora española con voz cálida, clara y cercana. Suena natural, inteligente y con una sonrisa vocal ligera. Lleva la conversación, abre secciones, hace preguntas y resume ideas.

## {$this->speaker2}: co-presentador experto
{$this->speaker2} es un divulgador español con voz serena, segura y didáctica. Aporta profundidad, matices y ejemplos con tono conversacional, no académico.

# THE SCENE
Están grabando "The iaiaPRO Brief" en un estudio de podcast corporativo moderno, tranquilo y bien sonorizado. La conversación debe sonar como un podcast profesional para escuchar en el coche: entretenido, claro, humano y fácil de seguir sin mirar pantalla.

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
* Mantener voces claras, cercanas y limpias durante todo el segmento, sin cambiar la calidad acústica.
{$segmentNote}

# TRANSCRIPT
TTS the following podcast conversation between {$this->speaker1} and {$this->speaker2}:

{$script}
PROMPT;
    }
}
