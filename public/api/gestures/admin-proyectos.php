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
$model = 'google/gemini-2.5-flash';

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
Analiza el siguiente pliego de licitación pública y extrae TODOS los gastos, costes y requisitos económicos que NO sean de personal (salarios, cotizaciones, seguridad social).

## Qué buscar específicamente:

1. **Equipamiento y maquinaria obligatoria**
   - Equipos informáticos, software, licencias
   - Maquinaria específica del servicio
   - Vehículos o medios de transporte

2. **Materiales y consumibles**
   - Material de oficina
   - Uniformes y EPIs
   - Productos de limpieza, mantenimiento, etc.

3. **Seguros y garantías**
   - Seguros de responsabilidad civil
   - Garantías de ejecución
   - Avales bancarios

4. **Certificaciones y formación**
   - Certificaciones obligatorias
   - Cursos de formación requeridos
   - Homologaciones

5. **Infraestructura**
   - Obras o adaptaciones de instalaciones
   - Alquileres de locales
   - Suministros (agua, luz, teléfono)

6. **Otros costes**
   - Tasas y tributos
   - Gastos de desplazamiento
   - Cualquier otro coste directo o indirecto

## Formato de respuesta:

Presenta los resultados en formato estructurado:

### [Categoría]
| Concepto | Cantidad/Unidades | Coste estimado | Observaciones |
|----------|-------------------|----------------|---------------|
| ... | ... | ... | ... |

**Subtotal categoría: X€**

---

Al final, incluye:
- **TOTAL ESTIMADO DE GASTOS NO PERSONALES: X€**
- Lista de gastos sin cuantificar que necesitan valoración

Si algún coste no tiene valor específico en el pliego, indícalo como "A determinar" pero inclúyelo igualmente.

PROMPT;

    if ($additionalInstructions) {
        $prompt .= "\n\n## Instrucciones adicionales del usuario:\n" . $additionalInstructions;
    }

    return $prompt;
}

/**
 * Construye el prompt para conteo de horas
 */
function buildHoursPrompt(string $additionalInstructions = ''): string
{
    $prompt = <<<PROMPT
Analiza el siguiente pliego de licitación pública y localiza TODAS las horas de trabajo o dedicación mencionadas en cualquier parte del documento.

## Qué buscar específicamente:

1. **Horas de servicio directo**
   - Atención al público
   - Prestación del servicio principal
   - Horarios de apertura/cierre

2. **Horas de personal de apoyo**
   - Coordinación y supervisión
   - Administración
   - Mantenimiento

3. **Horas de formación**
   - Formación inicial obligatoria
   - Formación continua
   - Reciclajes

4. **Horas especiales**
   - Guardias o disponibilidad
   - Turnos nocturnos o festivos
   - Horas extras previstas

5. **Reuniones y coordinación**
   - Reuniones de seguimiento
   - Coordinación con la administración
   - Comités técnicos

## Formato de respuesta:

### [Tipo de horas]
| Descripción | Horas/período | Período | Horas/año | Ubicación en documento |
|-------------|---------------|---------|-----------|------------------------|
| ... | ... | ... | ... | Cláusula X, página Y |

**Subtotal: X horas/año**

---

Al final, incluye:
- **TOTAL HORAS/AÑO: X**
- Desglose por tipo de personal si aplica
- Notas sobre cálculos o estimaciones realizadas

Si encuentras referencias ambiguas o rangos, indica el mínimo y máximo.

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
