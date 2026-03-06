<?php
/**
 * API: Gesto Admin Proyectos - Análisis de Pliegos
 * POST /api/gestures/admin-proyectos.php
 * 
 * Analiza pliegos de concursos públicos para extraer:
 * - Gastos no personales (equipamiento, materiales, etc.)
 * - Conteo de horas de trabajo
 */

require_once __DIR__ . '/../../../src/App/bootstrap.php';

use App\Session;
use App\Response;
use Chat\LlmProviderFactory;
use Chat\OpenRouterClient;
use Gestures\GestureExecutionsRepo;
use Repos\UsageLogRepo;

Session::start();
$user = Session::user();
if (!$user) {
    Response::error('unauthorized', 'Sesión no válida', 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'Sólo POST', 405);
}

// Validar CSRF
$csrfHeader = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
$csrfSession = $_SESSION['csrf_token'] ?? '';
if (!$csrfHeader || !$csrfSession || !hash_equals($csrfSession, $csrfHeader)) {
    Response::error('csrf_invalid', 'Token CSRF inválido', 403);
}

// Parsear body
$body = json_decode(file_get_contents('php://input'), true);
if (!$body) {
    Response::error('invalid_body', 'Body JSON inválido', 400);
}

$gestureType = $body['gesture_type'] ?? 'project-admin';
$files = $body['files'] ?? [];
$actions = $body['actions'] ?? ['expenses'];
$instructions = trim($body['instructions'] ?? '');

if (empty($files)) {
    Response::error('missing_files', 'Se requiere al menos un documento', 400);
}

// Modelo para análisis de documentos largos
$model = 'google/gemini-3-flash-preview';

// Procesar cada archivo y concatenar contenido
$documentsContent = [];
foreach ($files as $file) {
    $fileName = $file['name'] ?? 'documento.pdf';
    $fileData = $file['data'] ?? '';
    $mimeType = $file['mime_type'] ?? 'application/pdf';
    
    if (empty($fileData)) {
        continue;
    }
    
    $documentsContent[] = [
        'name' => $fileName,
        'data' => $fileData,
        'mime_type' => $mimeType
    ];
}

if (empty($documentsContent)) {
    Response::error('no_valid_files', 'No se encontraron archivos válidos', 400);
}

// Preparar resultados
$results = [];
$usedModel = $model;

// === ANÁLISIS DE GASTOS ===
if (in_array('expenses', $actions)) {
    $expensesPrompt = buildExpensesPrompt($instructions);
    
    try {
        $client = new OpenRouterClient(null, $model, null);
        
        // Construir mensaje con archivos adjuntos
        $content = [
            ['type' => 'text', 'text' => $expensesPrompt]
        ];
        
        foreach ($documentsContent as $doc) {
            $content[] = [
                'type' => 'file',
                'file' => [
                    'filename' => $doc['name'],
                    'file_data' => 'data:' . $doc['mime_type'] . ';base64,' . $doc['data']
                ]
            ];
        }
        
        $messages = [['role' => 'user', 'content' => $content]];
        $response = $client->generateWithMessages($messages);
        $usedModel = $client->getModel();
        
        $results['expenses'] = [
            'content' => $response,
            'model' => $usedModel
        ];
    } catch (\Exception $e) {
        $results['expenses'] = [
            'content' => 'Error al analizar gastos: ' . $e->getMessage(),
            'error' => true
        ];
    }
}

// === ANÁLISIS DE HORAS ===
if (in_array('hours', $actions)) {
    $hoursPrompt = buildHoursPrompt($instructions);
    
    try {
        $client = new OpenRouterClient(null, $model, null);
        
        // Construir mensaje con archivos adjuntos
        $content = [
            ['type' => 'text', 'text' => $hoursPrompt]
        ];
        
        foreach ($documentsContent as $doc) {
            $content[] = [
                'type' => 'file',
                'file' => [
                    'filename' => $doc['name'],
                    'file_data' => 'data:' . $doc['mime_type'] . ';base64,' . $doc['data']
                ]
            ];
        }
        
        $messages = [['role' => 'user', 'content' => $content]];
        $response = $client->generateWithMessages($messages);
        $usedModel = $client->getModel();
        
        $results['hours'] = [
            'content' => $response,
            'model' => $usedModel
        ];
    } catch (\Exception $e) {
        $results['hours'] = [
            'content' => 'Error al analizar horas: ' . $e->getMessage(),
            'error' => true
        ];
    }
}

// Generar título
$fileNames = array_map(fn($f) => $f['name'], $documentsContent);
$title = generateTitle($fileNames, $actions);

// Guardar en historial
$repo = new GestureExecutionsRepo();
$executionId = $repo->create([
    'user_id' => $user['id'],
    'gesture_type' => $gestureType,
    'title' => $title,
    'input_data' => [
        'files' => array_map(fn($f) => ['name' => $f['name']], $documentsContent),
        'actions' => $actions,
        'instructions' => $instructions
    ],
    'output_content' => json_encode($results, JSON_UNESCAPED_UNICODE),
    'output_data' => [
        'results' => $results,
        'files' => array_map(fn($f) => ['name' => $f['name']], $documentsContent)
    ],
    'model' => $usedModel,
]);

// Registrar uso
$usageLog = new UsageLogRepo();
$usageLog->log((int)$user['id'], 'gesture', 1, ['gesture_type' => $gestureType]);

Response::json([
    'success' => true,
    'execution_id' => $executionId,
    'title' => $title,
    'results' => $results,
    'files' => array_map(fn($f) => ['name' => $f['name']], $documentsContent),
    'model' => $usedModel
]);

/**
 * Construye el prompt para extracción de gastos no personales
 */
function buildExpensesPrompt(string $additionalInstructions = ''): string
{
    $prompt = <<<PROMPT
Eres un experto en análisis de pliegos de licitaciones públicas españolas. Analiza el documento y extrae información sobre gastos NO PERSONALES de forma INTELIGENTE y PRÁCTICA.

## REGLAS CRÍTICAS:

1. **NO listes elementos genéricos** como "equipos y medios técnicos" sin especificar qué son exactamente.
2. **SOLO incluye gastos CONCRETOS** que aparezcan en el pliego con suficiente detalle.
3. **AGRUPA conceptos similares** en lugar de listar cada mención por separado.
4. **CALCULA cuando sea posible**: si dice "500 camisetas por municipio" y hay 15 municipios, calcula 7.500 camisetas.
5. **ESTIMA costes de mercado** cuando el pliego no los especifique pero el concepto sea concreto (ej: "camisetas serigrafiadas" → estima 5-8€/ud).
6. **IGNORA las penalidades** - no son gastos, son riesgos.
7. **DIFERENCIA claramente** entre gastos OBLIGATORIOS y gastos OPCIONALES/propuestos.

## FORMATO DE RESPUESTA:

Responde en formato estructurado usando SOLO estas secciones:

---

## 📋 RESUMEN EJECUTIVO

Breve párrafo (2-3 líneas) indicando el tipo de contrato, presupuesto base y principales partidas de gasto detectadas.

---

## 💰 GASTOS CUANTIFICADOS

Lista SOLO los gastos donde puedas dar una cifra concreta o estimación razonable:

- **[Concepto]**: [Cantidad] × [Precio unitario estimado] = **[Total]€**
  - Fuente: [Dónde aparece en el pliego]

---

## 📦 GASTOS OBLIGATORIOS SIN CUANTIFICAR

Lista los gastos que el contratista DEBE asumir pero que requieren valoración:

- **[Concepto]**: [Descripción breve de qué incluye]
  - Cómo valorar: [Sugerencia práctica para estimar]

---

## 🔒 GARANTÍAS Y AVALES

- **Garantía definitiva**: [Porcentaje]% = [Importe]€
- **Otros avales requeridos**: [Si aplica]

---

## ⚠️ RIESGOS ECONÓMICOS A CONSIDERAR

Menciona brevemente (sin detallar cada penalidad):
- Principales incumplimientos penalizados
- Rango de penalidades (mín-máx)

---

## 📊 ESTIMACIÓN TOTAL

| Categoría | Estimación |
|-----------|------------|
| Gastos cuantificados | X€ |
| Garantías | X€ |
| **Gastos a valorar** | Pendiente |
| **TOTAL MÍNIMO ESTIMADO** | **X€** |

---

## 💡 RECOMENDACIONES

Lista 2-3 puntos clave que el licitador debería investigar o valorar con más detalle.

PROMPT;

    if ($additionalInstructions) {
        $prompt .= "\n\n## INSTRUCCIONES ADICIONALES DEL USUARIO:\n" . $additionalInstructions . "\n\nTen muy en cuenta estas instrucciones en tu análisis.";
    }

    return $prompt;
}

/**
 * Construye el prompt para conteo de horas
 */
function buildHoursPrompt(string $additionalInstructions = ''): string
{
    $prompt = <<<PROMPT
Eres un experto en análisis de pliegos de licitaciones públicas españolas. Analiza el documento y extrae información sobre HORAS DE TRABAJO de forma INTELIGENTE y PRÁCTICA.

## REGLAS CRÍTICAS:

1. **BUSCA datos concretos**: horarios, jornadas, dedicaciones específicas.
2. **CALCULA siempre que puedas**: si dice "8h/día × 5 días × 52 semanas", haz el cálculo.
3. **NORMALIZA todo a horas/año** para facilitar comparaciones.
4. **AGRUPA por tipo de servicio o perfil profesional** si el pliego los distingue.
5. **INDICA la ubicación** en el documento donde encontraste cada dato.
6. **DISTINGUE** entre horas de servicio directo y horas de coordinación/gestión.

## FORMATO DE RESPUESTA:

---

## 📋 RESUMEN EJECUTIVO

Breve párrafo indicando el tipo de servicio, duración del contrato y volumen aproximado de horas detectado.

---

## ⏱️ HORAS DE SERVICIO DIRECTO

Horas donde el personal está prestando el servicio principal:

### [Nombre del servicio/actividad]
- **Horario**: [Ej: L-V de 9:00 a 14:00]
- **Días/semana**: [X días]
- **Semanas/año**: [X semanas]
- **Cálculo**: [Detalle del cálculo]
- **Total**: **[X] horas/año**
- 📍 Fuente: [Cláusula/página]

---

## 👥 HORAS DE COORDINACIÓN Y GESTIÓN

Reuniones, supervisión, tareas administrativas:

- **[Tipo de reunión/tarea]**: [Frecuencia] × [Duración] = **[X] horas/año**
  - 📍 Fuente: [Cláusula/página]

---

## 📚 HORAS DE FORMACIÓN

Formación inicial y continua requerida:

- **[Tipo de formación]**: [Horas totales]
  - 📍 Fuente: [Cláusula/página]

---

## 📊 RESUMEN DE HORAS

| Categoría | Horas/año |
|-----------|----------:|
| Servicio directo | X |
| Coordinación/gestión | X |
| Formación | X |
| **TOTAL** | **X** |

---

## 👷 EQUIVALENCIA EN PERSONAL

Si es posible calcular:
- **Jornada completa** (1.720 h/año): [X] personas
- **Media jornada** (860 h/año): [X] personas

---

## ⚠️ DATOS AMBIGUOS O INCOMPLETOS

Menciona cualquier referencia a horas que no hayas podido cuantificar y por qué.

---

## 💡 NOTAS DEL ANÁLISIS

Cualquier consideración importante sobre los cálculos realizados o supuestos asumidos.

PROMPT;

    if ($additionalInstructions) {
        $prompt .= "\n\n## Instrucciones adicionales del usuario:\n" . $additionalInstructions;
    }

    return $prompt;
}

/**
 * Genera un título descriptivo para el análisis
 */
function generateTitle(array $fileNames, array $actions): string
{
    $actionLabels = [
        'expenses' => 'Gastos',
        'hours' => 'Horas'
    ];
    
    $actionsStr = implode(' + ', array_map(fn($a) => $actionLabels[$a] ?? $a, $actions));
    
    if (count($fileNames) === 1) {
        $name = pathinfo($fileNames[0], PATHINFO_FILENAME);
        // Truncar si es muy largo
        if (mb_strlen($name) > 40) {
            $name = mb_substr($name, 0, 37) . '...';
        }
        return "{$actionsStr}: {$name}";
    }
    
    return "{$actionsStr}: " . count($fileNames) . " documentos";
}
