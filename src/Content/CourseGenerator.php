<?php
namespace Content;

use Chat\OpenRouterClient;

/**
 * Generador de material de cursos a partir de contenido fuente (PDF, texto)
 * 
 * Flujo de 2 fases:
 * - Fase 1: generateOutline() - Genera índice pedagógico editable (JSON)
 * - Fase 2: developModules() - Desarrolla contenido completo por módulo
 * 
 * Otros outputs: fichas, quizzes, flashcards, podcasts, examen final
 */
class CourseGenerator
{
    private OpenRouterClient $llmClient;
    private OpenRouterClient $llmClientLong; // Cliente con más tokens para módulos largos

    private const OUTPUT_FORMATS = [
        'outline' => [
            'name' => 'Índice del curso',
            'icon' => 'iconoir-list',
            'description' => 'Estructura editable del curso'
        ],
        'full_course' => [
            'name' => 'Curso completo',
            'icon' => 'iconoir-book-stack',
            'description' => 'Módulos desarrollados con contenido completo'
        ],
        'content_cards' => [
            'name' => 'Fichas de contenido',
            'icon' => 'iconoir-journal-page',
            'description' => 'Resúmenes y conceptos clave por lección'
        ],
        'quiz' => [
            'name' => 'Autoevaluación',
            'icon' => 'iconoir-check-circle',
            'description' => 'Preguntas tipo test para cada módulo'
        ],
        'flashcards' => [
            'name' => 'Microlearning',
            'icon' => 'iconoir-brain',
            'description' => 'Flashcards y píldoras de conocimiento'
        ],
        'podcast' => [
            'name' => 'Podcast educativo',
            'icon' => 'iconoir-podcast',
            'description' => 'Guion de conversación para audio'
        ],
        'final_exam' => [
            'name' => 'Examen final',
            'icon' => 'iconoir-clipboard-check',
            'description' => 'Evaluación completa del temario'
        ]
    ];

    private const DURATION_MAP = [
        '4h' => ['hours' => 4, 'modules' => '2-3', 'lessons_per_module' => '2-3'],
        '8h' => ['hours' => 8, 'modules' => '3-4', 'lessons_per_module' => '3-4'],
        '16h' => ['hours' => 16, 'modules' => '4-6', 'lessons_per_module' => '3-5'],
        '40h' => ['hours' => 40, 'modules' => '6-10', 'lessons_per_module' => '4-6']
    ];

    private const LEVEL_MAP = [
        'basico' => 'básico (sin conocimientos previos requeridos)',
        'intermedio' => 'intermedio (conocimientos básicos asumidos)',
        'avanzado' => 'avanzado (para profesionales o estudiantes avanzados)'
    ];

    private const FORMAT_MAP = [
        'presencial' => 'presencial (dinámicas de grupo, ejercicios en clase)',
        'online' => 'online asíncrono (autoaprendizaje, recursos digitales)',
        'hibrido' => 'híbrido (combinación de sesiones en vivo y materiales autónomos)'
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
        
        // Cliente con más tokens para desarrollo de módulos largos
        $this->llmClientLong = new OpenRouterClient(
            null,
            'google/gemini-3-flash-preview',
            null,
            0.7,
            32768
        );
    }

    // =========================================================================
    // FASE 1: GENERACIÓN DE ÍNDICE EDITABLE
    // =========================================================================

    /**
     * Fase 1: Genera un índice pedagógico editable del curso
     * El usuario puede modificar este índice antes de desarrollar los módulos
     * 
     * @return array{success: bool, outline?: array, raw?: string, error?: string}
     */
    public function generateOutline(string $content, string $title = '', array $config = []): array
    {
        $wordCount = str_word_count($content);
        if ($wordCount < 100) {
            return ['success' => false, 'error' => 'El contenido es demasiado corto (mínimo 100 palabras)'];
        }

        $prompt = $this->buildOutlinePrompt($content, $title, $config);

        try {
            $response = $this->llmClient->generateText($prompt);
            
            if (empty($response)) {
                return ['success' => false, 'error' => 'No se pudo generar el índice'];
            }

            // Parsear el JSON del índice
            $outline = $this->parseOutlineResponse($response);
            
            if (!$outline) {
                return [
                    'success' => true,
                    'outline' => null,
                    'raw' => $response,
                    'parse_error' => 'No se pudo parsear el JSON, revisa el formato',
                    'model' => $this->llmClient->getModel()
                ];
            }

            return [
                'success' => true,
                'outline' => $outline,
                'raw' => $response,
                'model' => $this->llmClient->getModel()
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Error generando índice: ' . $e->getMessage()];
        }
    }

    /**
     * Parsea la respuesta del LLM para extraer el JSON del índice
     */
    private function parseOutlineResponse(string $response): ?array
    {
        // Buscar JSON en la respuesta (puede venir con texto adicional)
        if (preg_match('/```json\s*(.+?)\s*```/s', $response, $matches)) {
            $json = $matches[1];
        } elseif (preg_match('/\{[\s\S]*"modules"[\s\S]*\}/s', $response, $matches)) {
            $json = $matches[0];
        } else {
            return null;
        }

        $decoded = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        // Validar estructura mínima
        if (!isset($decoded['modules']) || !is_array($decoded['modules'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Construye el prompt para generar el índice
     */
    private function buildOutlinePrompt(string $content, string $title, array $config): string
    {
        $duration = $config['duration'] ?? '8h';
        $level = $config['level'] ?? 'intermedio';
        $courseFormat = $config['course_format'] ?? 'online';
        
        $durationInfo = self::DURATION_MAP[$duration] ?? self::DURATION_MAP['8h'];
        $levelDesc = self::LEVEL_MAP[$level] ?? self::LEVEL_MAP['intermedio'];
        $formatDesc = self::FORMAT_MAP[$courseFormat] ?? self::FORMAT_MAP['online'];

        $titleSection = $title ? "TÍTULO DEL MATERIAL FUENTE: {$title}\n\n" : '';

        return <<<PROMPT
Eres un diseñador instruccional experto. Tu tarea es analizar el contenido proporcionado y crear un ÍNDICE PEDAGÓGICO estructurado para un curso formativo.

{$titleSection}CONTENIDO FUENTE:
---
{$content}
---

CONFIGURACIÓN DEL CURSO:
- Duración total: {$durationInfo['hours']} horas
- Número de módulos recomendado: {$durationInfo['modules']}
- Lecciones por módulo: {$durationInfo['lessons_per_module']}
- Nivel: {$levelDesc}
- Modalidad: {$formatDesc}

INSTRUCCIONES:
1. Analiza TODO el contenido fuente y extrae los conceptos principales
2. Reorganiza el contenido de forma PEDAGÓGICA (no copies la estructura original)
3. Crea un índice progresivo: de lo básico a lo avanzado
4. Define objetivos de aprendizaje claros y medibles para cada módulo
5. Especifica qué contenido del documento fuente corresponde a cada lección
6. El índice debe ser completo pero editable

DEVUELVE ÚNICAMENTE un JSON válido con esta estructura exacta:

```json
{
  "course_title": "Título propuesto para el curso",
  "course_description": "Descripción general del curso en 2-3 frases",
  "total_hours": {$durationInfo['hours']},
  "level": "{$level}",
  "objectives": [
    "Objetivo general 1",
    "Objetivo general 2",
    "Objetivo general 3"
  ],
  "modules": [
    {
      "id": 1,
      "title": "Título del Módulo 1",
      "description": "Breve descripción del módulo",
      "duration_hours": 2,
      "objectives": [
        "Objetivo específico 1 del módulo",
        "Objetivo específico 2 del módulo"
      ],
      "lessons": [
        {
          "id": "1.1",
          "title": "Título de la lección",
          "duration_minutes": 30,
          "topics": ["Tema 1", "Tema 2"],
          "source_reference": "Breve indicación de qué parte del documento cubre"
        }
      ]
    }
  ]
}
```

IMPORTANTE:
- Devuelve SOLO el JSON, sin texto adicional antes o después
- Asegúrate de que el JSON sea válido y parseable
- Incluye entre {$durationInfo['modules']} módulos
- Cada módulo debe tener {$durationInfo['lessons_per_module']} lecciones
- Los IDs deben ser consistentes (1, 2, 3... para módulos; 1.1, 1.2, 2.1... para lecciones)
PROMPT;
    }

    // =========================================================================
    // FASE 2: DESARROLLO DE MÓDULOS
    // =========================================================================

    /**
     * Fase 2: Desarrolla todos los módulos del curso basándose en el índice
     * Genera contenido completo para cada módulo de forma secuencial
     * 
     * @param string $content Contenido fuente original
     * @param array $outline Índice del curso (de generateOutline o editado por usuario)
     * @param callable|null $progressCallback Función para reportar progreso
     * @return array{success: bool, modules: array, errors: array}
     */
    public function developModules(string $content, array $outline, ?callable $progressCallback = null): array
    {
        $modules = $outline['modules'] ?? [];
        $courseTitle = $outline['course_title'] ?? 'Curso';
        
        if (empty($modules)) {
            return ['success' => false, 'modules' => [], 'errors' => ['No hay módulos en el índice']];
        }

        $developedModules = [];
        $errors = [];
        $totalModules = count($modules);

        foreach ($modules as $index => $module) {
            $moduleNumber = $index + 1;
            
            // Reportar progreso
            if ($progressCallback) {
                $progressCallback([
                    'status' => 'developing',
                    'current' => $moduleNumber,
                    'total' => $totalModules,
                    'module_title' => $module['title'] ?? "Módulo {$moduleNumber}"
                ]);
            }

            $result = $this->developSingleModule($content, $module, $outline, $courseTitle);
            
            if ($result['success']) {
                $developedModules[] = [
                    'module_id' => $module['id'] ?? $moduleNumber,
                    'title' => $module['title'] ?? "Módulo {$moduleNumber}",
                    'content' => $result['content'],
                    'html' => $this->markdownToHtml($result['content']),
                    'word_count' => str_word_count($result['content'])
                ];
            } else {
                $errors[] = "Módulo {$moduleNumber}: " . ($result['error'] ?? 'Error desconocido');
            }
        }

        // Reportar completado
        if ($progressCallback) {
            $progressCallback([
                'status' => 'completed',
                'current' => $totalModules,
                'total' => $totalModules
            ]);
        }

        return [
            'success' => count($developedModules) > 0,
            'modules' => $developedModules,
            'course_title' => $courseTitle,
            'total_developed' => count($developedModules),
            'total_failed' => count($errors),
            'errors' => $errors,
            'model' => $this->llmClientLong->getModel()
        ];
    }

    /**
     * Desarrolla el contenido completo de un solo módulo
     */
    public function developSingleModule(string $content, array $module, array $outline, string $courseTitle): array
    {
        $prompt = $this->buildModuleDevelopmentPrompt($content, $module, $outline, $courseTitle);

        try {
            $response = $this->llmClientLong->generateText($prompt);
            
            if (empty($response)) {
                return ['success' => false, 'error' => 'No se pudo generar el contenido del módulo'];
            }

            // Limpiar el contenido (quitar delimitadores si los hay)
            $cleanContent = $this->cleanModuleContent($response);

            return [
                'success' => true,
                'content' => $cleanContent
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Construye el prompt para desarrollar un módulo específico
     */
    private function buildModuleDevelopmentPrompt(string $content, array $module, array $outline, string $courseTitle): string
    {
        $moduleTitle = $module['title'] ?? 'Módulo';
        $moduleDescription = $module['description'] ?? '';
        $moduleObjectives = implode("\n- ", $module['objectives'] ?? []);
        $lessons = $module['lessons'] ?? [];
        
        // Construir lista de lecciones
        $lessonsText = '';
        foreach ($lessons as $lesson) {
            $lessonTitle = $lesson['title'] ?? '';
            $topics = implode(', ', $lesson['topics'] ?? []);
            $sourceRef = $lesson['source_reference'] ?? '';
            $lessonsText .= "\n### {$lesson['id']}. {$lessonTitle}\n";
            $lessonsText .= "- Temas a cubrir: {$topics}\n";
            if ($sourceRef) {
                $lessonsText .= "- Referencia del material fuente: {$sourceRef}\n";
            }
        }

        // Contexto del curso completo (índice resumido)
        $outlineContext = "Curso: {$courseTitle}\nMódulos del curso:\n";
        foreach ($outline['modules'] ?? [] as $m) {
            $outlineContext .= "- {$m['id']}. {$m['title']}\n";
        }

        return <<<PROMPT
Eres un experto en diseño instruccional y redacción de contenidos formativos de alto nivel. Tu misión es desarrollar el contenido didáctico detallado de un módulo basándote ÚNICAMENTE en el material fuente proporcionado.

REGLA DE ORO DE FIDELIDAD:
- TODO el contenido desarrollado debe provenir directamente del MATERIAL FUENTE.
- QUEDA ESTRICTAMENTE PROHIBIDO inventar conceptos, datos, fechas, nombres, procedimientos o cualquier información que NO figure en el material fuente.
- Si una lección o tema solicitado en el índice NO tiene base suficiente en el material fuente, explica los conceptos generales basándote en lo poco que haya, pero NO alucines ni rellenes con información externa de Internet o de tus conocimientos previos.
- Tu valor no reside en "añadir información nueva", sino en "estructurar pedagógicamente la información existente".

CONTEXTO DEL CURSO:
{$outlineContext}

MÓDULO A DESARROLLAR: {$module['id']}. {$moduleTitle}
Descripción del módulo: {$moduleDescription}

OBJETIVOS DEL MÓDULO:
- {$moduleObjectives}

LECCIONES A DESARROLLAR:
{$lessonsText}

MATERIAL FUENTE (ÚNICA FUENTE DE VERDAD):
---
{$content}
---

INSTRUCCIONES DE DESARROLLO:

1. **Fidelidad Absoluta:** No utilices conocimientos externos. Si el material dice "A", tú explicas "A". Si el material no menciona "B", tú NO hablas de "B", por mucho que creas que falta.
2. **Estructura de cada lección:**
   - Título de la lección
   - Introducción: Presenta el tema usando exclusivamente el contexto del material.
   - Desarrollo: Explica detalladamente los puntos del material fuente, organizándolos con subtítulos si es necesario para mejorar la claridad.
   - Puntos clave: Resume lo más importante extraído de la fuente.
   - Transición: Conecta con la siguiente lección.
3. **Estilo:**
   - Tono profesional, didáctico y directo.
   - Usa español de España (normativo/peninsular).
   - Máxima claridad pedagógica.
4. **Formato Markdown:**
   - ## para el título del módulo.
   - ### para cada lección.
   - #### para subapartados.
   - Usa listas y negritas para facilitar la lectura.

RECUERDA: Cero alucinaciones. Fidelidad 100% al material fuente.

DESARROLLA EL MÓDULO COMPLETO:
PROMPT;
    }

    /**
     * Limpia el contenido del módulo generado
     */
    private function cleanModuleContent(string $content): string
    {
        // Quitar posibles delimitadores markdown de código
        $content = preg_replace('/^```(?:markdown)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        
        return trim($content);
    }

    /**
     * Convierte Markdown a HTML básico
     */
    private function markdownToHtml(string $markdown): string
    {
        // Conversión básica de Markdown a HTML
        $html = $markdown;
        
        // Headers
        $html = preg_replace('/^#### (.+)$/m', '<h4>$1</h4>', $html);
        $html = preg_replace('/^### (.+)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.+)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.+)$/m', '<h1>$1</h1>', $html);
        
        // Bold and italic
        $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
        
        // Lists
        $html = preg_replace('/^- (.+)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>\n?)+/s', '<ul>$0</ul>', $html);
        
        // Horizontal rules
        $html = preg_replace('/^---$/m', '<hr>', $html);
        
        // Paragraphs
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';
        $html = preg_replace('/<p><(h[1-4]|ul|hr)/', '<$1', $html);
        $html = preg_replace('/<\/(h[1-4]|ul|hr)><\/p>/', '</$1>', $html);
        
        return $html;
    }

    /**
     * Obtiene los formatos de salida disponibles
     */
    public static function getOutputFormats(): array
    {
        return self::OUTPUT_FORMATS;
    }

    /**
     * Genera contenido en un formato específico
     */
    public function generate(string $content, string $format, string $title = '', array $config = []): array
    {
        if (!isset(self::OUTPUT_FORMATS[$format])) {
            return ['success' => false, 'error' => "Formato no soportado: {$format}"];
        }

        $wordCount = str_word_count($content);
        if ($wordCount < 50) {
            return ['success' => false, 'error' => 'El contenido es demasiado corto (mínimo 50 palabras)'];
        }

        $prompt = $this->buildPrompt($content, $format, $title, $config);

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
     */
    public function generateMultiple(string $content, array $formats, string $title = '', array $config = []): array
    {
        $results = [];
        $errors = [];

        // Si se pide temario, generarlo primero para usar como referencia
        $syllabus = null;
        if (in_array('syllabus', $formats)) {
            $syllabusResult = $this->generate($content, 'syllabus', $title, $config);
            if ($syllabusResult['success']) {
                $syllabus = $syllabusResult['output'];
                $parsed = $this->parseOutput($syllabusResult['output'], 'syllabus');
                $results['syllabus'] = [
                    'format' => 'syllabus',
                    'format_name' => self::OUTPUT_FORMATS['syllabus']['name'],
                    'icon' => self::OUTPUT_FORMATS['syllabus']['icon'],
                    'raw' => $syllabusResult['output'],
                    'parsed' => $parsed,
                    'model' => $syllabusResult['model']
                ];
            } else {
                $errors['syllabus'] = $syllabusResult['error'];
            }
        }

        // Generar el resto de formatos
        foreach ($formats as $format) {
            if ($format === 'syllabus') continue; // Ya procesado
            
            // Añadir temario como contexto si está disponible
            $configWithSyllabus = $config;
            if ($syllabus) {
                $configWithSyllabus['syllabus_context'] = $syllabus;
            }
            
            $result = $this->generate($content, $format, $title, $configWithSyllabus);
            
            if ($result['success']) {
                $parsed = $this->parseOutput($result['output'], $format);
                $results[$format] = [
                    'format' => $format,
                    'format_name' => self::OUTPUT_FORMATS[$format]['name'] ?? $format,
                    'icon' => self::OUTPUT_FORMATS[$format]['icon'] ?? 'iconoir-document',
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
     * Parsea el output estructurado según el formato
     */
    public function parseOutput(string $output, string $format): array
    {
        $parsed = ['raw' => $output];

        switch ($format) {
            case 'syllabus':
                $parsed['modules'] = $this->extractSection($output, 'TEMARIO');
                $parsed['objectives'] = $this->extractSection($output, 'OBJETIVOS');
                $parsed['duration_breakdown'] = $this->extractSection($output, 'DISTRIBUCION');
                break;

            case 'content_cards':
                $parsed['cards'] = $this->extractSection($output, 'FICHAS');
                break;

            case 'quiz':
                $parsed['questions'] = $this->extractSection($output, 'PREGUNTAS');
                $parsed['answers'] = $this->extractSection($output, 'RESPUESTAS');
                break;

            case 'flashcards':
                $parsed['flashcards'] = $this->extractSection($output, 'FLASHCARDS');
                $parsed['tips'] = $this->extractSection($output, 'TIPS');
                break;

            case 'podcast':
                $parsed['script'] = $this->extractSection($output, 'GUION');
                $parsed['summary'] = $this->extractSection($output, 'RESUMEN');
                break;

            case 'final_exam':
                $parsed['exam'] = $this->extractSection($output, 'EXAMEN');
                $parsed['rubric'] = $this->extractSection($output, 'RUBRICA');
                $parsed['answers'] = $this->extractSection($output, 'SOLUCIONES');
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
     * Construye el prompt según el formato de salida
     */
    private function buildPrompt(string $content, string $format, string $title, array $config): string
    {
        $duration = $config['duration'] ?? '8h';
        $level = $config['level'] ?? 'intermedio';
        $courseFormat = $config['course_format'] ?? 'online';
        
        $durationInfo = self::DURATION_MAP[$duration] ?? self::DURATION_MAP['8h'];
        $levelDesc = self::LEVEL_MAP[$level] ?? self::LEVEL_MAP['intermedio'];
        $formatDesc = self::FORMAT_MAP[$courseFormat] ?? self::FORMAT_MAP['online'];

        $titleSection = $title ? "TÍTULO DEL MATERIAL FUENTE: {$title}\n\n" : '';
        
        // Contexto del temario si está disponible
        $syllabusContext = '';
        if (!empty($config['syllabus_context']) && $format !== 'syllabus') {
            $syllabusContext = "\n\nTEMARIO YA GENERADO (usar como referencia de estructura):\n---\n" . 
                              mb_substr($config['syllabus_context'], 0, 4000) . "\n---\n";
        }

        $baseContext = <<<CONTEXT
Eres un diseñador instruccional experto. Tu tarea es crear material formativo de alta calidad a partir del contenido proporcionado.

{$titleSection}CONTENIDO FUENTE:
---
{$content}
---

CONFIGURACIÓN DEL CURSO:
- Duración total: {$durationInfo['hours']} horas
- Número de módulos: {$durationInfo['modules']}
- Lecciones por módulo: {$durationInfo['lessons_per_module']}
- Nivel: {$levelDesc}
- Modalidad: {$formatDesc}
{$syllabusContext}

REGLAS GENERALES:
- Extrae y estructura TODOS los conceptos importantes del contenido fuente
- NO inventes información que no esté en el material
- Adapta la complejidad al nivel especificado
- Usa español de España (vosotros, expresiones peninsulares)
- Sé didáctico, claro y práctico
- Incluye ejemplos cuando sea posible
CONTEXT;

        return match($format) {
            'syllabus' => $this->buildSyllabusPrompt($baseContext, $durationInfo),
            'content_cards' => $this->buildContentCardsPrompt($baseContext),
            'quiz' => $this->buildQuizPrompt($baseContext),
            'flashcards' => $this->buildFlashcardsPrompt($baseContext),
            'podcast' => $this->buildPodcastPrompt($baseContext, $title),
            'final_exam' => $this->buildFinalExamPrompt($baseContext, $durationInfo),
            default => $baseContext
        };
    }

    private function buildSyllabusPrompt(string $context, array $durationInfo): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: TEMARIO ESTRUCTURADO

INSTRUCCIONES ESPECÍFICAS:
1. Crea un temario completo con {$durationInfo['modules']} módulos
2. Cada módulo debe tener {$durationInfo['lessons_per_module']} lecciones
3. Define objetivos de aprendizaje específicos y medibles (verbos de acción)
4. Incluye estimación de tiempo por módulo/lección
5. Añade prerrequisitos si los hay
6. Sugiere orden lógico de progresión

ESTRUCTURA:
---OBJETIVOS---
## Objetivos Generales del Curso
[Lista de 3-5 objetivos principales]

## Competencias a Desarrollar
[Lista de competencias que adquirirá el alumno]
---FIN_OBJETIVOS---

---TEMARIO---
# [Nombre del Curso]

## Módulo 1: [Título]
**Duración estimada:** X horas
**Objetivos del módulo:**
- [Objetivo 1]
- [Objetivo 2]

### Lección 1.1: [Título]
- Contenidos: [lista de temas]
- Duración: X min

### Lección 1.2: [Título]
[etc.]

## Módulo 2: [Título]
[Continuar con la misma estructura...]
---FIN_TEMARIO---

---DISTRIBUCION---
| Módulo | Duración | Peso |
|--------|----------|------|
[Tabla de distribución de tiempo]
---FIN_DISTRIBUCION---
PROMPT;
    }

    private function buildContentCardsPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: FICHAS DE CONTENIDO

INSTRUCCIONES ESPECÍFICAS:
1. Crea una ficha para cada lección/tema importante
2. Cada ficha debe incluir:
   - Título del tema
   - Resumen (2-3 párrafos)
   - Conceptos clave (definiciones claras)
   - Puntos importantes a recordar
   - Ejemplo práctico si aplica
3. Usa formato visual con iconos (emojis) para categorías
4. Las fichas deben ser autocontenidas (entendibles sin contexto adicional)

ESTRUCTURA:
---FICHAS---
## 📚 Ficha 1: [Título del Tema]

### 📝 Resumen
[Resumen del tema en 2-3 párrafos]

### 🔑 Conceptos Clave
- **[Concepto 1]**: [Definición clara]
- **[Concepto 2]**: [Definición clara]
- **[Concepto 3]**: [Definición clara]

### ⭐ Puntos Importantes
1. [Punto 1]
2. [Punto 2]
3. [Punto 3]

### 💡 Ejemplo Práctico
[Ejemplo aplicado del concepto]

---

## 📚 Ficha 2: [Título del Tema]
[Continuar con la misma estructura...]
---FIN_FICHAS---
PROMPT;
    }

    private function buildQuizPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: PREGUNTAS DE AUTOEVALUACIÓN

INSTRUCCIONES ESPECÍFICAS:
1. Crea 3-5 preguntas por cada módulo/tema principal
2. Tipos de preguntas:
   - Tipo test (4 opciones, solo 1 correcta)
   - Verdadero/Falso con justificación
   - Preguntas cortas (1-2 frases de respuesta)
3. Las preguntas deben verificar comprensión, no solo memorización
4. Incluye feedback para respuestas incorrectas
5. Varía la dificultad (algunas fáciles, otras de reflexión)

ESTRUCTURA:
---PREGUNTAS---
## Módulo 1: [Nombre]

### Pregunta 1 (Tipo test)
¿[Pregunta]?

a) [Opción A]
b) [Opción B]
c) [Opción C]
d) [Opción D]

### Pregunta 2 (Verdadero/Falso)
"[Afirmación]"
- [ ] Verdadero
- [ ] Falso

### Pregunta 3 (Respuesta corta)
[Pregunta abierta]

---

## Módulo 2: [Nombre]
[Continuar...]
---FIN_PREGUNTAS---

---RESPUESTAS---
## Soluciones Módulo 1

### Pregunta 1
**Respuesta correcta:** [letra]
**Explicación:** [Por qué es correcta y por qué las otras no]

### Pregunta 2
**Respuesta correcta:** [V/F]
**Explicación:** [Justificación]

### Pregunta 3
**Respuesta modelo:** [Respuesta esperada]
**Puntos clave:** [Qué debe incluir una buena respuesta]

[Continuar...]
---FIN_RESPUESTAS---
PROMPT;
    }

    private function buildFlashcardsPrompt(string $context): string
    {
        return $context . <<<PROMPT


FORMATO DE SALIDA: MICROLEARNING Y FLASHCARDS

INSTRUCCIONES ESPECÍFICAS:
1. Crea 15-25 flashcards (pregunta/respuesta) para memorización activa
2. Añade 5-10 "píldoras" de conocimiento (datos curiosos, tips, "¿Sabías que...?")
3. Las flashcards deben ser:
   - Concisas (respuesta en 1-3 líneas)
   - Específicas (un solo concepto por tarjeta)
   - Accionables (fáciles de recordar y aplicar)
4. Incluye mnemotécnicas si son útiles

ESTRUCTURA:
---FLASHCARDS---
### 🎴 Flashcard 1
**Pregunta:** [Pregunta corta]
**Respuesta:** [Respuesta concisa]

### 🎴 Flashcard 2
**Pregunta:** [Pregunta corta]
**Respuesta:** [Respuesta concisa]

[Continuar con 15-25 flashcards...]
---FIN_FLASHCARDS---

---TIPS---
### 💡 Píldoras de Conocimiento

1. **¿Sabías que...?** [Dato interesante relacionado]

2. **Tip profesional:** [Consejo práctico]

3. **Error común:** [Qué evitar y por qué]

4. **Recuerda:** [Mnemotécnico o regla fácil]

[Continuar con 5-10 tips...]
---FIN_TIPS---
PROMPT;
    }

    private function buildPodcastPrompt(string $context, string $title): string
    {
        $courseName = $title ?: 'este curso';
        
        return $context . <<<PROMPT


FORMATO DE SALIDA: GUION DE PODCAST EDUCATIVO

INSTRUCCIONES ESPECÍFICAS:
1. Crea un guion para un podcast de 2 presentadores: Iris (mujer) y Bruno (hombre)
2. El podcast debe ser una CONVERSACIÓN DIDÁCTICA, no una lectura
3. Duración objetivo: 10-15 minutos de audio
4. Estructura del episodio:
   - Intro: Presentación del tema y por qué es importante
   - Desarrollo: Explicación conversacional de los conceptos clave
   - Ejemplos: Casos prácticos o analogías para entender mejor
   - Cierre: Resumen de puntos clave y despedida
5. Tono: Cercano, profesional pero accesible, con algo de humor natural
6. IMPORTANTE: Cubrir TODOS los conceptos importantes sin omitir nada relevante
7. Usar preguntas entre presentadores para simular dudas del oyente

REGLAS DEL DIÁLOGO:
- Iris y Bruno se complementan, no se repiten
- Uno explica, el otro pregunta o añade ejemplos
- Evitar monólogos largos (máx 3-4 frases seguidas)
- Incluir transiciones naturales ("Eso me recuerda...", "Exacto, y además...")

ESTRUCTURA:
---RESUMEN---
**Título del episodio:** [Título atractivo]
**Tema principal:** [Descripción breve]
**Conceptos cubiertos:** [Lista de conceptos]
**Duración estimada:** [X minutos]
---FIN_RESUMEN---

---GUION---
**[INTRO]**

Iris: ¡Hola! Bienvenidos a un nuevo episodio. Hoy vamos a hablar de {$courseName}. Bruno, ¿preparado?

Bruno: ¡Por supuesto! Es un tema que...

[Continuar el diálogo natural cubriendo todos los puntos del contenido]

**[DESARROLLO]**

[Explicación conversacional de cada concepto importante]

**[EJEMPLOS Y CASOS PRÁCTICOS]**

[Analogías y ejemplos para facilitar la comprensión]

**[CIERRE]**

Iris: Bueno, para resumir lo que hemos visto hoy...

Bruno: [Resumen de puntos clave]

Iris: ¡Hasta el próximo episodio!

Bruno: ¡Nos vemos!
---FIN_GUION---
PROMPT;
    }

    private function buildFinalExamPrompt(string $context, array $durationInfo): string
    {
        $numQuestions = max(15, $durationInfo['hours'] * 2);
        
        return $context . <<<PROMPT


FORMATO DE SALIDA: EXAMEN FINAL DEL CURSO

INSTRUCCIONES ESPECÍFICAS:
1. Crea un examen completo de aproximadamente {$numQuestions} preguntas
2. El examen debe cubrir TODOS los módulos/temas del curso
3. Distribución de tipos de preguntas:
   - 50% Tipo test (4 opciones)
   - 20% Verdadero/Falso
   - 20% Respuesta corta
   - 10% Desarrollo/Caso práctico
4. Incluye puntuación por pregunta
5. Añade rúbrica de evaluación para preguntas de desarrollo
6. Tiempo estimado de realización

ESTRUCTURA:
---EXAMEN---
# EXAMEN FINAL: [Nombre del Curso]

**Instrucciones:**
- Tiempo estimado: [X minutos]
- Puntuación total: 100 puntos
- Para aprobar: mínimo 60 puntos
- Lee todas las preguntas antes de empezar

---

## SECCIÓN A: Preguntas Tipo Test (50 puntos)
*Cada pregunta vale 5 puntos. Marca la opción correcta.*

**1.** [Pregunta]
   a) [Opción]
   b) [Opción]
   c) [Opción]
   d) [Opción]

[Continuar con más preguntas tipo test...]

---

## SECCIÓN B: Verdadero o Falso (20 puntos)
*Cada pregunta vale 4 puntos. Indica V o F.*

**11.** "[Afirmación]" ___

[Continuar...]

---

## SECCIÓN C: Respuesta Corta (20 puntos)
*Responde en 2-3 frases. Cada pregunta vale 5 puntos.*

**16.** [Pregunta]

[Continuar...]

---

## SECCIÓN D: Desarrollo (10 puntos)
*Responde de forma completa y argumentada.*

**20.** [Pregunta de desarrollo o caso práctico]
---FIN_EXAMEN---

---RUBRICA---
## Rúbrica de Evaluación - Sección D

| Criterio | Excelente (10) | Bien (7-8) | Suficiente (5-6) | Insuficiente (<5) |
|----------|----------------|------------|------------------|-------------------|
| Comprensión | [Descripción] | [Descripción] | [Descripción] | [Descripción] |
| Argumentación | [Descripción] | [Descripción] | [Descripción] | [Descripción] |
| Aplicación | [Descripción] | [Descripción] | [Descripción] | [Descripción] |
---FIN_RUBRICA---

---SOLUCIONES---
## Clave de Respuestas

### Sección A
1. [Respuesta] - [Breve explicación]
2. [Respuesta] - [Breve explicación]
[Continuar...]

### Sección B
11. [V/F] - [Explicación]
[Continuar...]

### Sección C
16. **Respuesta modelo:** [Respuesta completa]
[Continuar...]

### Sección D
20. **Puntos clave que debe incluir:**
- [Punto 1]
- [Punto 2]
- [Punto 3]
---FIN_SOLUCIONES---
PROMPT;
    }
}
