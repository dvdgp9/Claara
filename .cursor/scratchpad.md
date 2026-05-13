# Background and Motivation

Ebonia: plataforma interna de inteligencia corporativa (Grupo Ebone) basada en PHP, JS, MySQL. MVP: escritorio con chat central, sidebar con historiales por usuario, login propio y roles básicos. Proveedor LLM inicial: Gemini (1.5 Flash). Conversations en MySQL. Sin streaming (request→response). Preparado para multi-empresa a futuro.

# Key Challenges and Analysis

- Abstracción de proveedor LLM (arranque con Gemini 1.5 Flash, extensible a otros modelos).
- Modelo de datos escalable: users/departments/companies, conversations/messages, folders, roles/permissions.
- Seguridad: sesiones PHP, hashing Argon2id, HTTPS/HSTS/CSP, saneamiento inputs/CSRF.
- UI mínima con Tailwind CDN y JS vanilla manteniendo escalabilidad.
- Documentación de tablas en repo (única fuente de verdad de la BD).

# High-level Task Breakdown

1. Definir y acordar esquema BD (tablas, claves, índices) y documentarlo.
2. Definir estructura de proyecto (public/, api/, src/, config/, docs/, assets/...).
3. Preparar configuración: `.env.example`, `.gitignore`, configuración sesiones seguras.
4. Implementar autenticación (login/logout, registro admin inicial, RBAC mínimo: admin/user).
5. UI MVP: escritorio (chat central + sidebar), Tailwind CDN, layout base.
6. Endpoint `/api/chat` con Gemini 1.5 Flash (request→response), capa proveedor.
7. Persistencia de conversaciones/mensajes y CRUD básico (renombrar, archivar, folders, mover).
8. Semillas iniciales: empresas y departamentos proporcionados.
9. README con setup (PHP 8.2+, MySQL, variables entorno) y decisiones.

---

## Feature: FAQ Chatbot (Dudas Rápidas) con QWEN Turbo

### Motivación
Chatbot ligero para preguntas rápidas sobre el Grupo Ebone. Usa QWEN Turbo (`qwen-turbo`) por su velocidad. Sin persistencia en BD, pero con historial en memoria del modal para poder hacer seguimiento de la conversación.

### Decisiones técnicas
- **Modelo**: `qwen-turbo` (1M tokens contexto, optimizado velocidad) via Alibaba Cloud API
- **Endpoint**: `https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions` (ya configurado en QwenClient)
- **Sin RAG**: El contexto corporativo (~4.5KB) cabe perfectamente en el system prompt
- **Historial en sesión JS**: El modal mantiene array de mensajes en memoria para continuidad de conversación
- **Sin persistencia BD**: No se guardan mensajes FAQ (diferencia clave con chat principal)

### Tareas de implementación

1. [x] **Crear endpoint `/api/faq.php`**
   - Recibe: `{ message: string, history: array }`
   - Usa QwenClient con modelo `qwen-turbo`
   - System prompt optimizado para FAQ cortas
   - Retorna: `{ reply: string }`
   - Success: Respuesta en <2s para preguntas simples

2. [x] **Crear system prompt FAQ** (`docs/context/faq_prompt.md`)
   - Instrucciones para respuestas concisas
   - Incluye contexto corporativo inline
   - Directriz: responder en 2-3 párrafos máximo
   - Success: Respuestas focalizadas y breves

3. [x] **Agregar modal FAQ en `index.php`**
   - Botón "?" junto a la lupa en header
   - Modal con input + historial de mensajes
   - Sugerencias de preguntas frecuentes
   - Indicador de "escribiendo..."
   - Success: Modal funcional con UX fluida

4. [x] **Implementar lógica JS del modal**
   - Array `faqHistory` en memoria
   - Envío de historial completo en cada request
   - Renderizado de conversación en el modal
   - Botón para limpiar/nueva conversación
   - Success: Poder hacer follow-up questions

5. [ ] **Testing y ajustes**
   - Verificar velocidad de respuesta
   - Ajustar system prompt si respuestas muy largas
   - Probar límite de historial (~20 mensajes)
   - Success: UX fluida, respuestas relevantes

# Project Status Board

- [x] Crear `index.php` de placeholder.
- [x] Inicializar Git con rama `main` y primer commit.
- [x] Conectar `origin` y hacer `git push -u origin main`.
- [ ] Acordar esquema BD y registrarlo en `docs/db_schema.md`.
- [ ] Acordar estructura de proyecto y scaffolding inicial.
- [ ] Implementar autenticación básica (admin/user).
- [ ] Implementar `/api/chat` con Gemini 1.5 Flash.
- [ ] UI MVP: escritorio y sidebar con historiales.
- [x] Scaffolding MVP (public/api/src) y utilidades base.
- [x] Endpoints mínimos auth/login, auth/logout y chat.
- [x] `.env` local configurado.
- [x] SOP Generator: historial con eliminación y edición de título.

## Feature: Audio Transcriber para audios largos

### Motivación
El gesto de transcripción actual funciona en flujo síncrono y sube audio como base64 desde el navegador. Esto no escala bien para reuniones largas: puede provocar timeouts HTTP, uso alto de memoria, respuestas cortadas y poca visibilidad del progreso. La mejora debe permitir procesar audios de 40-45 minutos con jobs en background, subida multipart, progreso parcial y segmentación con `ffmpeg`.

### Documento operativo
Plan detallado: `docs/audio_transcription_implementation_scratchpad.md`

Informe técnico de referencia: `docs/audio_transcription_technical_report.md`

### Estado operativo del servidor
- `ffmpeg`: disponible en `/usr/bin/ffmpeg`
- `ffprobe`: disponible en `/usr/bin/ffprobe`
- `open_basedir`: no activo

### Estrategia de implementación
1. [ ] Añadir plumbing de jobs para `audio-transcribe`.
   - `BackgroundJobsRepo::updateProcessingSnapshot()`
   - soporte en `public/api/jobs/process.php`
   - guardado final en `gesture_executions`

2. [ ] Cambiar `/api/gestures/transcribe.php` a subida `multipart/form-data`.
   - campo principal `audio_file`
   - crear job async
   - mantener compatibilidad temporal con JSON/base64

3. [ ] Actualizar `public/gestos/transcriptor-audio.php`.
   - eliminar base64 desde navegador
   - hacer polling de `/api/jobs/status.php`
   - mostrar progreso y transcripción parcial
   - recuperar job activo tras recarga con `sessionStorage`

4. [ ] Refactorizar `src/Sop/AudioTranscriber.php`.
   - añadir `transcribeFile()`
   - prompt en inglés, manteniendo el idioma original del audio
   - etiquetas obligatorias de hablante (`Speaker 1:`, `Speaker 2:` o nombre/rol si se deduce)

5. [ ] Añadir duración y segmentación.
   - `ffprobe` para duración
   - `ffmpeg` para segmentos M4A/AAC mono 16 kHz
   - segmentar desde 10 minutos
   - segmentos base de 180s

6. [ ] Añadir fallbacks por segmento.
   - segmento vacío
   - `[no speech]`
   - `MAX_TOKENS`
   - repeticiones artificiales

7. [ ] Hardening operativo.
   - `.env.example` con variables de transcripción
   - log en `storage/transcribe-debug.log`
   - limpieza de temporales en `storage/transcribe-jobs`

### Primer corte recomendado
Implementar primero fases 1-3 y un `transcribeFile()` mínimo sin segmentación. Esto elimina base64, reduce riesgo de 504 y deja lista la UI de polling. Después implementar segmentación y fallbacks.

## Gesto: Redes Sociales (en progreso)

- [ ] Crear página `/public/gestos/redes-sociales.php`
- [ ] Crear JS `/public/assets/js/gesture-social-media.js`
- [ ] Actualizar `/public/gestos/index.php` con tarjeta del gesto
- [ ] Actualizar `generate.php` para tipo `social-media`
- [ ] Testing manual del flujo completo

---

## Feature: Sistema de Gestos

### Motivación
Los "gestos" son acciones predefinidas que los usuarios pueden ejecutar para tareas específicas. A diferencia del chat libre, cada gesto tiene parámetros estructurados y produce un resultado específico.

### Gestos planificados (6-10)
1. **Escribir artículos** (primer gesto) - Genera artículos siguiendo un estilo seleccionable
2. (Por definir)
3. (Por definir)
...

### Diseño UI/UX
- **Sidebar gestos**: Grid de tarjetas con icono, nombre y descripción corta
- **Workspace**: Al seleccionar un gesto, se muestra su interfaz específica en el área principal
- **Cada gesto**: Modal/panel con parámetros propios del gesto

### Tareas de implementación

1. [x] **Crear sidebar de gestos** (`gestures-sidebar`)
   - Grid con tarjetas de gestos
   - Cada tarjeta: icono, nombre, descripción, color distintivo
   - Hover/click states bonitos
   - ✅ Completado

2. [x] **Crear workspace de gestos** (`gesture-workspace`)
   - Área principal que muestra el gesto seleccionado
   - Estado inicial con mensaje de bienvenida
   - ✅ Completado

3. [x] **Lógica JS navegación gestos**
   - Mostrar/ocultar sidebars según tab activa
   - Seleccionar gesto → mostrar su interfaz
   - ✅ Completado

4. [x] **Implementar gesto "Escribir contenido"**
   - 3 tipos: Artículo informativo, Post de blog (SEO), Nota de prensa
   - Selector de línea de negocio (Ebone, CUBOFIT, UNIGES-3)
   - Campos dinámicos según tipo seleccionado
   - Prompts especializados para cada tipo
   - Copiar y regenerar resultado
   - ✅ Completado

5. [x] **Refactorizar gestos a páginas separadas**
   - Cada gesto en su propia página `/gestures/<nombre>.php`
   - JS modular en `/assets/js/gesture-<nombre>.js`
   - `index.php` solo contiene navegación (redirige a rutas)
   - ✅ Estructura lista para escalar a más gestos

## Mejora de UX: Scroll en Respuestas del Chat

### Motivación
Cuando se recibe una respuesta larga del asistente, el scroll automático actual se desplaza hasta el final del mensaje. Esto obliga al usuario a hacer scroll hacia arriba manualmente para empezar a leer desde el principio. Se desea que al recibir una respuesta, el scroll se posicione al inicio de la misma para mejorar la legibilidad.

### Tareas de implementación

1. [ ] **Modificar la lógica de scroll en `index.php`**
   - Ajustar la función `append()` para que el scroll se posicione al inicio del nuevo mensaje del asistente.
   - Asegurar que los mensajes cortos sigan siendo visibles sin problemas.
   - Mantener el comportamiento de scroll al final para los mensajes del usuario.
   - Success: Al recibir una respuesta larga, el usuario ve el comienzo del mensaje sin tener que subir manualmente.

---

## Feature: Gestor de Contexto (Superadmin)

### Motivación
Panel de administración para que los superadministradores puedan gestionar el contexto/RAG de los diferentes componentes de Ebonia:
- **Lex** (voz legal): Documentos del RAG (convenios laborales)
- **Eboniato** (chatbot de ayuda del inicio): Archivos de contexto FAQ
- **Ebonia general**: Archivos de contexto del chat principal

### Análisis de la arquitectura actual

**1. Lex (RAG con Qdrant)**
- **Ubicación física**: `docs/context/voices/lex/convenios/`
- **Formatos**: PDF (fuente), TXT (extraído)
- **Colección Qdrant**: `lex_convenios`
- **Procesamiento**: `scripts/rag/ingest_lex.php` → Chunking → Embeddings → Qdrant
- **Servicios**: `QdrantClient`, `EmbeddingService`, `LexRetriever`
- **Contenido actual**: 28 convenios colectivos (PDFs + TXTs)
- ⚠️ **Sin API de gestión**: Solo script CLI para ingestar

**2. Eboniato (Chatbot FAQ)**
- **Ubicación física**: `docs/context_faq/`
- **Formatos**: Markdown (.md)
- **Lectura**: `ContextBuilder($faqContextDir)` concatena todos los .md
- **Contenido actual**: 4 archivos (faq_prompt.md, Área Proyectos.md, etc.)
- **Sin RAG**: Contexto directo en system prompt (~6KB total)

**3. Ebonia general**
- **Ubicación física**: `docs/context/`
- **Formatos**: Markdown (.md)
- **Lectura**: `ContextBuilder()` concatena todos los .md
- **Contenido actual**: system_prompt.md, grupo_ebone_overview.md
- **Sin RAG**: Contexto directo en system prompt (~13KB total)

---

### Diseño propuesto

#### Decisión clave: ¿BD o sistema de archivos?

**Opción elegida: Híbrido (BD para metadatos + archivos físicos)**

Razones:
1. Los archivos .md se leen directamente del filesystem por `ContextBuilder`
2. Qdrant ya tiene los vectores, solo necesitamos tracking de qué documentos están procesados
3. Una tabla de metadatos permite tracking de estado, quién subió, cuándo, etc.

#### Estructura de datos

```sql
-- Tabla para tracking de documentos de contexto
CREATE TABLE context_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target ENUM('lex', 'eboniato', 'ebonia') NOT NULL,
  filename VARCHAR(255) NOT NULL,
  original_filename VARCHAR(255) NOT NULL,
  file_extension VARCHAR(10) NOT NULL,
  file_size INT NOT NULL DEFAULT 0,
  status ENUM('active', 'processing', 'error', 'pending') DEFAULT 'active',
  rag_status ENUM('not_applicable', 'pending', 'processed', 'error') DEFAULT 'not_applicable',
  rag_chunk_count INT DEFAULT 0,
  description TEXT NULL,
  created_by INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (created_by) REFERENCES users(id),
  INDEX idx_target (target),
  INDEX idx_status (status),
  UNIQUE KEY unique_target_filename (target, filename)
);
```

#### Rutas de archivos por target

| Target | Ruta física | Formatos permitidos |
|--------|-------------|--------------------|
| `lex` | `docs/context/voices/lex/convenios/` | .pdf, .txt, .md |
| `eboniato` | `docs/context_faq/` | .md |
| `ebonia` | `docs/context/` | .md |

#### UI: Página de gestión

**Ruta**: `/public/admin/context-manager.php`

**Layout**:
```
┌──────────────────────────────────────────────────────────────┐
│  Gestor de Contexto                           [Superadmin]   │
├──────────────────────────────────────────────────────────────┤
│  [Tab: Lex]  [Tab: Eboniato]  [Tab: Ebonia General]          │
├──────────────────────────────────────────────────────────────┤
│                                                              │
│  📊 Estadísticas: 28 documentos | 1,245 chunks | 5.2MB       │
│                                                              │
│  [+ Añadir documento]                                        │
│                                                              │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ 📄 CC1 - Instalaciones deportivas...  │ 565KB │ ✅ RAG │  │
│  │    [👁 Ver] [✏️ Editar] [🔄 Reprocesar] [🗑️ Eliminar]  │  │
│  ├────────────────────────────────────────────────────────┤  │
│  │ 📄 CC2 - Actividades deportivas...    │ 132KB │ ✅ RAG │  │
│  │    [👁 Ver] [✏️ Editar] [🔄 Reprocesar] [🗑️ Eliminar]  │  │
│  └────────────────────────────────────────────────────────┘  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

**Acciones por target**:

| Acción | Lex | Eboniato | Ebonia |
|--------|-----|----------|--------|
| Ver contenido | ✅ | ✅ | ✅ |
| Editar contenido | ✅ (solo .md/.txt) | ✅ | ✅ |
| Eliminar | ✅ (+borrar de Qdrant) | ✅ | ✅ |
| Añadir | ✅ (upload) | ✅ (upload) | ✅ (upload) |
| Procesar RAG | ✅ | ❌ | ❌ |
| Reprocesar RAG | ✅ | ❌ | ❌ |

#### Endpoints API

**Base**: `/api/admin/context/`

| Método | Endpoint | Descripción |
|--------|----------|-------------|
| GET | `list.php?target=lex` | Listar documentos de un target |
| GET | `view.php?id=X` | Ver contenido de un documento |
| POST | `upload.php` | Subir nuevo documento (multipart) |
| PUT | `update.php` | Actualizar contenido/metadatos |
| DELETE | `delete.php?id=X` | Eliminar documento |
| POST | `process-rag.php?id=X` | Procesar documento a Qdrant (solo Lex) |
| GET | `stats.php?target=lex` | Estadísticas del target |

#### Flujo de procesamiento RAG (Lex)

```
1. Usuario sube PDF/TXT
   ↓
2. Archivo guardado en docs/context/voices/lex/convenios/
   ↓
3. Registro creado en context_documents (status='active', rag_status='pending')
   ↓
4. Usuario pulsa "Procesar RAG" (o automático)
   ↓
5. Backend:
   a. Extraer texto (si PDF: usar pdftotext o similar)
   b. Chunking (~500 tokens, overlap 50)
   c. Generar embeddings via OpenRouter
   d. Upsert en Qdrant (colección lex_convenios)
   e. Actualizar rag_status='processed', rag_chunk_count=N
   ↓
6. Documento listo para búsqueda semántica
```

#### Consideraciones de seguridad

1. **Autenticación**: Todos los endpoints protegidos con `AdminGuard::requireSuperadmin()`
2. **Validación de archivos**:
   - Verificar extensión permitida por target
   - Verificar MIME type real
   - Límite de tamaño: 10MB por archivo
3. **Sanitización de nombres**: Evitar path traversal, caracteres especiales
4. **CSRF**: Tokens en todas las operaciones de escritura

---

### Tareas de implementación

#### Fase 1: Backend base
1. [ ] **Crear migración SQL** `docs/migrations/014_context_documents.sql`
2. [ ] **Crear `ContextDocsRepo.php`** en `src/Repos/`
   - `listByTarget(string $target): array`
   - `getById(int $id): ?array`
   - `create(array $data): int`
   - `update(int $id, array $data): bool`
   - `delete(int $id): bool`
   - `getStatsByTarget(string $target): array`

#### Fase 2: Endpoints API
3. [ ] **GET `/api/admin/context/list.php`** - Listar documentos
4. [ ] **GET `/api/admin/context/view.php`** - Ver contenido
5. [ ] **POST `/api/admin/context/upload.php`** - Subir documento
6. [ ] **PUT `/api/admin/context/update.php`** - Editar documento
7. [ ] **DELETE `/api/admin/context/delete.php`** - Eliminar documento
8. [ ] **POST `/api/admin/context/process-rag.php`** - Procesar RAG (Lex)
9. [ ] **GET `/api/admin/context/stats.php`** - Estadísticas

#### Fase 3: Servicio RAG
10. [ ] **Crear `RagProcessor.php`** en `src/Rag/`
    - Refactorizar lógica de `ingest_lex.php` a clase reutilizable
    - Métodos: `processDocument()`, `deleteDocumentVectors()`, `getDocumentChunkCount()`

#### Fase 4: UI
11. [ ] **Crear página `/public/admin/context-manager.php`**
    - Tabs para cada target
    - Tabla de documentos con acciones
    - Modal para upload
    - Modal para edición de contenido
12. [ ] **Crear JS `/public/assets/js/admin-context-manager.js`**
    - Fetch API para todas las operaciones
    - Feedback visual de estados

#### Fase 5: Testing y polish
13. [x] **Testing manual** de todos los flujos
14. [x] **Sincronizar documentos existentes** - Script `scripts/sync_context_docs.php`
15. [x] **Enlace en menú admin** - Añadido en header-unified.php

### Archivos creados
- `docs/migrations/013_context_documents.sql` - Migración BD
- `src/Repos/ContextDocsRepo.php` - Repositorio CRUD
- `src/Rag/RagProcessor.php` - Servicio RAG reutilizable
- `public/api/admin/context/list.php` - Listar documentos
- `public/api/admin/context/view.php` - Ver contenido
- `public/api/admin/context/stats.php` - Estadísticas
- `public/api/admin/context/upload.php` - Subir documento
- `public/api/admin/context/update.php` - Editar documento
- `public/api/admin/context/delete.php` - Eliminar documento
- `public/api/admin/context/process-rag.php` - Procesar RAG
- `public/admin/context-manager.php` - UI completa
- `scripts/sync_context_docs.php` - Script sincronización

### Archivos modificados
- `src/Rag/QdrantClient.php` - Añadidos métodos deletePointsByFilter, countPointsByFilter
- `public/includes/header-unified.php` - Enlace al gestor en menú admin

### Pasos para activar
1. Ejecutar migración: `php scripts/migrate.php`
2. Sincronizar documentos existentes: `php scripts/sync_context_docs.php`
3. Acceder desde menú de perfil → "Gestor de contexto"

---

---

## Feature: Gesto "Admin Proyectos" (Análisis de Pliegos)

### Motivación
Herramienta para el equipo de administración/licitaciones que analiza pliegos de concursos públicos. El gesto recibe documentos (PDFs de pliegos) y ofrece análisis automatizados para extraer información clave que ayude a decidir si presentarse a un concurso y preparar la oferta.

### Funcionalidades principales

**1. Extracción de gastos no personales**
- Identifica TODOS los costes obligatorios que no sean personal (maquinaria, equipamiento, licencias, materiales, seguros, etc.)
- Agrupa por categoría
- Suma totales cuando corresponda
- Presenta de forma clara y estructurada

**2. Conteo de horas**
- Localiza TODAS las horas de trabajo mencionadas en el pliego (dispersas en diferentes secciones)
- Agrupa por tipo/categoría (técnico, administrativo, formación, etc.)
- Suma totales por categoría y total general
- Muestra ubicación/referencia en el documento

### Input del gesto
- **Documentos**: Uno o varios PDFs de pliegos (obligatorio)
- **Texto adicional**: Instrucciones o contexto opcional del usuario
- **Acción**: Selector de qué análisis realizar (gastos / horas / ambos)

### Output esperado
- Resultado estructurado en formato legible
- Tablas con totales y subtotales
- Posibilidad de copiar/exportar

### Diseño UI

```
┌─────────────────────────────────────────────────────────────────┐
│  ← Todos los gestos    Admin Proyectos              [Historial]│
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  Sidebar (historial)  │  Área principal                        │
│                       │                                         │
│  [Análisis recientes] │  ┌─────────────────────────────────┐   │
│                       │  │  📄 Arrastra pliegos aquí       │   │
│  • Pliego 2024-001    │  │     o haz clic para subir       │   │
│  • Pliego 2024-002    │  │     (PDF, máx 10MB)              │   │
│                       │  └─────────────────────────────────┘   │
│                       │                                         │
│                       │  [Lista de archivos subidos]            │
│                       │                                         │
│                       │  ┌─────────────────────────────────┐   │
│                       │  │ Instrucciones adicionales       │   │
│                       │  │ (opcional)                       │   │
│                       │  └─────────────────────────────────┘   │
│                       │                                         │
│                       │  ¿Qué quieres analizar?                 │
│                       │  ┌─────────┐  ┌─────────┐              │
│                       │  │💰 Gastos│  │⏱️ Horas │              │
│                       │  │no person│  │ totales │              │
│                       │  └─────────┘  └─────────┘              │
│                       │                                         │
│                       │  [Analizar pliego]                      │
│                       │                                         │
└─────────────────────────────────────────────────────────────────┘
```

### Arquitectura técnica

**Archivos a crear:**
1. `/public/gestos/admin-proyectos.php` - Vista PHP del gesto
2. `/public/assets/js/gesture-admin-proyectos.js` - Lógica JS
3. `/public/api/gestures/admin-proyectos.php` - Endpoint API

**Archivos a modificar:**
1. `/public/includes/left-tabs.php` - Añadir al submenú de gestos
2. (Opcional) `UserFeatureAccessRepo` - Control de acceso si es necesario

### Prompts especializados

**Para extracción de gastos:**
```
Analiza el siguiente pliego de licitación pública y extrae TODOS los gastos, 
costes y requisitos económicos que NO sean de personal (salarios, cotizaciones).

Busca específicamente:
- Equipamiento y maquinaria obligatoria
- Licencias y software requerido
- Materiales y consumibles
- Seguros y garantías
- Certificaciones necesarias
- Obras o adaptaciones de instalaciones
- Cualquier otro coste directo o indirecto

Presenta los resultados en formato estructurado:
1. Agrupa por categoría
2. Indica cantidad/unidades si se especifica
3. Incluye estimación de coste si aparece
4. Suma subtotales por categoría
5. Calcula total general estimado

Si algún coste no tiene valor específico, indícalo como "A determinar" pero inclúyelo.
```

**Para conteo de horas:**
```
Analiza el siguiente pliego de licitación pública y localiza TODAS las horas 
de trabajo o dedicación mencionadas en cualquier parte del documento.

Busca específicamente:
- Horas de servicio directo
- Horas de atención al público
- Horas de formación requerida
- Horas de reuniones/coordinación
- Horas de guardia o disponibilidad
- Cualquier otra referencia temporal

Presenta los resultados:
1. Agrupa por tipo/categoría de horas
2. Indica período (semanal/mensual/anual)
3. Normaliza a horas/año cuando sea posible
4. Suma subtotales por categoría
5. Calcula total general de horas

Incluye la sección/página del documento donde se encuentra cada dato.
```

### Tareas de implementación

#### Fase 1: Estructura base
1. [x] Crear `/public/gestos/admin-proyectos.php` con layout base (sidebar + main)
2. [x] Crear `/public/assets/js/gesture-admin-proyectos.js` con lógica básica
3. [x] Añadir gesto a `left-tabs.php` e `index.php`

#### Fase 2: Upload de documentos
4. [x] Implementar zona de drag & drop para PDFs
5. [x] Mostrar lista de archivos subidos con opción de eliminar
6. [x] Enviar PDFs como base64 al backend (procesados por Gemini)

#### Fase 3: Análisis
7. [x] Crear endpoint `/api/gestures/admin-proyectos.php`
8. [x] Implementar prompt de extracción de gastos
9. [x] Implementar prompt de conteo de horas
10. [x] Parsear y formatear resultados

#### Fase 4: Resultados y UX
11. [x] Renderizar resultados con markdown
12. [x] Añadir botones copiar/exportar
13. [x] Implementar historial de análisis

#### Fase 5: Testing
14. [ ] Probar con pliegos reales
15. [ ] Ajustar prompts según resultados

### Success Criteria
- El gesto puede recibir uno o varios PDFs de pliegos
- Extrae correctamente los gastos no personales agrupados
- Extrae correctamente las horas totales agrupadas
- Los resultados son claros y exportables
- El historial permite recuperar análisis anteriores

---

# Current Status / Progress Tracking

- 2025-11-03: `index.php` creado. Repo inicializado en `main` y push a remoto realizado.
- 2025-11-03: Borrador de `docs/db_schema.md` creado para revisión.
- 2025-11-03: Scaffolding y endpoints mínimos creados. `.env` configurado con credenciales locales.
- Listo para pruebas locales con `php -S -t public`.
- 2025-11-26: **SEGURIDAD**: Corregido problema de autenticación en `index.php`. Se agregó verificación de sesión en PHP antes de renderizar HTML. Antes solo se verificaba con JavaScript, permitiendo que usuarios no autenticados vieran la interfaz brevemente.
- 2025-11-27: **ARQUITECTURA MULTI-PROVEEDOR**: Implementada capa de abstracción LLM (LlmProvider, GeminiProvider, LlmProviderFactory). Preparado para soportar múltiples proveedores (Gemini, ChatGPT, etc.) mediante configuración.
- 2025-12-01: **CONTEXTO CORPORATIVO**: Implementado sistema de contexto unificado con ContextBuilder. Ebonia ahora recibe conocimiento base del Grupo Ebone mediante systemInstruction en todas las conversaciones. Carpeta `docs/context/` creada con `system_prompt.md` y `grupo_ebone_overview.md`.
- 2025-12-01: **FOLDERS**: Implementada funcionalidad completa de carpetas para organizar conversaciones. Usuarios pueden crear, renombrar, eliminar carpetas y mover conversaciones entre ellas. Incluye FoldersRepo, 6 endpoints API (/folders/list, create, rename, delete, move, reorder) y UI completa en sidebar.
- 2026-01-30: **SOP Generator**: Historial compacto con botones de eliminar y editar título; añadido endpoint para actualización de título y lógica JS de edición.
- 2026-04-08: **CHAT UX ARCHIVOS**: Implementado drag & drop de archivos y pegado multimedia (clipboard) en `public/index.php` para ambos estados (vacío y chat activo). Refactorizada validación de archivos a función reutilizable (`validateAndAddFiles`). Añadido overlay visual de drop y mensajes de aviso cuando `imageMode` está activo (adjuntar/arrastrar/pegar bloqueado).
- 2026-04-13: **FIX HTTP 400 en conversaciones existentes**: Corregido bug en `OpenRouterClient.php` donde mensajes con contenido vacío (ej: respuestas solo-imagen de nanobanana) generaban `content: []` (array vacío) en el payload enviado a OpenRouter, causando rechazo HTTP 400. Fix: omitir mensajes sin contenido real en ambos métodos (streaming y no-streaming). Mejorado diagnóstico de errores: ahora se captura el body de error de OpenRouter en modo streaming para mostrar el mensaje real en lugar de solo "Error HTTP 400".
- 2026-04-13: **FALLBACK PDF local en chat streaming**: Detectado error específico de OpenRouter con ciertos PDFs (`Failed to parse ...pdf`). Se añadió fallback en `public/api/chat-stream.php`: si OpenRouter no puede parsear un PDF, el backend usa `ContentExtractor::extractFromPdfLocally()` (pdftotext + extracción básica), sustituye el PDF por texto en el último mensaje del historial y reintenta la consulta en streaming sin depender del parser remoto.
Se han identificado **20 hallazgos** de seguridad clasificados por severidad: 5 CRÍTICOS, 6 ALTOS, 7 MEDIOS, 2 BAJOS. Los problemas críticos deben resolverse **antes de publicar** la aplicación.

---

### 🔴 CRÍTICOS (resolver antes de publicar)

#### C1. Archivos de debug/admin expuestos públicamente
- **Archivos**: `public/debug-sop.php`, `public/api/voices/ingest_lex_web.php`
- **Riesgo**: `debug-sop.php` tiene `display_errors=1`, expone rutas internas del servidor, correo del admin y clases internas. `ingest_lex_web.php` permite a CUALQUIERA (sin auth) borrar y reconstruir la colección RAG de Qdrant.
- **Fix**: Eliminar ambos archivos antes de desplegar. También eliminar `public/index.php.backup`.

#### C2. SSRF (Server-Side Request Forgery) en ContentExtractor
- **Archivo**: `src/Audio/ContentExtractor.php:12-31`
- **Riesgo**: `extractFromUrl()` acepta URLs arbitrarias del usuario, solo valida con `FILTER_VALIDATE_URL` (que acepta IPs internas como `http://169.254.169.254`, `http://127.0.0.1`, `http://10.0.0.1`). Además tiene SSL verification deshabilitado (`verify_peer => false`). Un atacante podría acceder a metadata de la nube (AWS/GCP), servicios internos, Qdrant (puerto 6333), BD, etc.
- **Fix**: 
  1. Validar que URL sea http/https
  2. Resolver DNS y bloquear IPs privadas/internas (127.0.0.0/8, 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16, 169.254.0.0/16)
  3. Habilitar `verify_peer => true`

#### C3. Error disclosure en producción (rutas internas, stack traces)
- **Archivos**: `public/api/gestures/generate.php:10-21`, `public/api/gestures/generate-image.php`, `public/api/jobs/process.php`, y ~15 endpoints más
- **Riesgo**: Custom error handlers que exponen `$errfile:$errline`, `$e->getFile()`, `$e->getLine()` en respuestas JSON. Esto revela la estructura de directorios del servidor a atacantes.
- **Fix**: En producción, devolver solo mensajes genéricos. Loguear detalles internamente con `error_log()`, nunca enviarlos al cliente.

#### C4. Session fixation — No se regenera session ID tras login
- **Archivo**: `src/Auth/AuthService.php:41`, `src/App/Session.php`
- **Riesgo**: `session_regenerate_id()` solo se llama en `rememberDays()`, **nunca después del login**. Un atacante podría fijar un session ID en la cookie de la víctima (ej. vía XSS en otro subdominio) y luego secuestrar la sesión autenticada.
- **Fix**: Añadir `session_regenerate_id(true)` en `Session::login()` inmediatamente después de `$_SESSION['user'] = $user`.

#### C5. Falta de rate limiting en login (brute force)
- **Archivo**: `public/api/auth/login.php`
- **Riesgo**: Sin límite de intentos de login. Un atacante puede probar miles de contraseñas por minuto. Combinado con la contraseña débil del admin (`Cacaperr1`), esto es especialmente peligroso.
- **Fix**: Implementar rate limiting (ej. máx 5 intentos por IP cada 15 min). Opciones: tabla `login_attempts` en BD, o middleware con Redis/APCu.

---

### 🟠 ALTOS (resolver pronto)

#### A1. CSRF con comparación vulnerable a timing attacks
- **Archivos**: `gestures/generate.php:46`, `gestures/generate-image.php:43`, `voices/chat.php:37`, `voices/delete.php:23`, `gestures/delete.php:30`, `gestures/update-title.php:28`, `gestures/transcribe.php:64`
- **Riesgo**: Usan `$csrfHeader !== $csrfSession` en vez de `hash_equals()`. Vulnerable a ataques de timing que permiten deducir el token carácter a carácter.
- **Fix**: Reemplazar `!==` por `!hash_equals($csrfSession, $csrfHeader)` en todos los endpoints afectados (como ya hace `Session::requireCsrf()`).

#### A2. Endpoints POST sin protección CSRF
- **Archivos afectados**:
  - `gestures/sop.php` — Sin CSRF
  - `gestures/podcast.php` — Sin CSRF
  - `gestures/course-creator.php` — Sin CSRF
  - `gestures/course-develop.php` — Sin CSRF
  - `gestures/course-export.php` — Sin CSRF
  - `gestures/course-materials.php` — Sin CSRF
  - `gestures/repurposer.php` — Sin CSRF
  - `jobs/create.php` — Sin CSRF
  - `jobs/cancel.php` — Sin CSRF
  - `chat/generate-document.php` — Sin CSRF
- **Riesgo**: Un sitio malicioso puede ejecutar acciones en nombre del usuario autenticado (generar contenido, crear jobs, consumir API credits).
- **Fix**: Añadir `Session::requireCsrf()` al inicio de cada endpoint POST/DELETE.

#### A3. Sin headers de seguridad HTTP
- **Riesgo**: Ninguna página envía `Content-Security-Policy`, `X-Frame-Options`, `Strict-Transport-Security`, `Referrer-Policy`, `Permissions-Policy`.
  - Sin CSP: vulnerable a XSS persistente
  - Sin X-Frame-Options: vulnerable a clickjacking
  - Sin HSTS: vulnerable a downgrade a HTTP
- **Fix**: Crear middleware o include PHP que envíe estos headers en cada respuesta:
  ```php
  header('X-Frame-Options: DENY');
  header('X-Content-Type-Options: nosniff');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
  header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' cdn.tailwindcss.com cdn.jsdelivr.net; style-src 'self' 'unsafe-inline' cdn.tailwindcss.com cdn.jsdelivr.net fonts.googleapis.com; font-src fonts.gstatic.com; img-src 'self' data:; connect-src 'self'");
  ```

#### A4. Sin rate limiting en API de chat/gestos (abuso de costes)
- **Riesgo**: Un usuario autenticado (o atacante con sesión robada) puede enviar miles de requests al chat, generando facturas enormes en OpenRouter. No hay límite diario/por hora.
- **Fix**: Implementar rate limiting por usuario: ej. 100 mensajes/hora para chat, 50 generaciones/día para gestos.

#### A5. Podcast files in public /tmp/ con nombres predecibles
- **Archivo**: `public/api/jobs/process.php:252-259`
- **Riesgo**: Los archivos WAV de podcasts se guardan en `public/tmp/podcast_<uniqid>.wav`. Son accesibles sin autenticación y con nombres parcialmente predecibles (`uniqid()` es basado en timestamp).
- **Fix**: Mover a `storage/` (fuera de public) y servir mediante endpoint autenticado, similar a `files/serve.php`.

#### A6. Logout público no limpia remember tokens
- **Archivo**: `public/logout.php:5-8`
- **Riesgo**: Llama `Session::logout()` pero NO `RememberService::clearAllForUser()`. La cookie de remember sigue siendo válida y restaura la sesión automáticamente.
- **Fix**: Añadir limpieza de remember tokens en logout.php.

---

### 🟡 MEDIOS

#### M1. Contraseña débil del admin en .env
- **Valor**: `ADMIN_PASSWORD=Cacaperr1`
- **Fix**: Cambiar a contraseña fuerte (>16 chars, aleatoria). Aunque este valor solo se usa para seed, la misma contraseña podría seguir vigente en BD.

#### M2. Modelo LLM seleccionable desde cliente
- **Archivo**: `public/api/chat.php:54-56`
- **Riesgo**: El cliente puede enviar cualquier `model` name, potencialmente seleccionando modelos mucho más caros (ej. `anthropic/claude-3.5-opus`).
- **Fix**: Validar contra whitelist de modelos permitidos en el backend.

#### M3. Content-Disposition header injection en serve.php
- **Archivo**: `public/api/files/serve.php:51`
- **Riesgo**: Usa `addslashes()` para filename, que no es sanitización correcta para HTTP headers. Un nombre de archivo malicioso podría inyectar headers.
- **Fix**: Usar `rawurlencode()` con formato RFC 5987: `Content-Disposition: inline; filename*=UTF-8''` . rawurlencode($name).

#### M4. Sin límite de tamaño en mensajes al LLM
- **Riesgo**: No hay validación de longitud máxima del mensaje del usuario, permitiendo enviar mensajes enormes que incrementan costes.
- **Fix**: Limitar `$message` a un máximo razonable (ej. 50.000 caracteres).

#### M5. Cookie domain demasiado amplio
- **Archivo**: `src/App/Session.php:30-32`
- **Riesgo**: La cookie se fija al dominio base (`ebonia.es`), lo que significa que cualquier subdominio (ej. `evil.ebonia.es`) podría leer la cookie de sesión.
- **Fix**: No fijar domain (el navegador lo limita al hostname exacto) o ser más restrictivo.

#### M6. Tailwind CDN en producción
- **Archivo**: `public/includes/head.php:30`
- **Riesgo**: `cdn.tailwindcss.com` es para desarrollo, no producción. Si el CDN se compromete, se inyecta código en todas las páginas. También implica dependencia de un tercero para el funcionamiento.
- **Fix**: Compilar Tailwind localmente y servir CSS propio.

#### M7. document.php no valida ownership
- **Archivo**: `public/api/files/document.php:21-31`
- **Riesgo**: Aunque usa `basename()` para prevenir traversal, no verifica que el documento pertenezca al usuario autenticado. Cualquier usuario autenticado puede descargar documentos de otros conociendo el filename.
- **Fix**: Asociar documentos a usuarios y verificar ownership.

---

### 🟢 BAJOS

#### B1. CSRF token expuesto en HTML source
- **Archivo**: `public/includes/head.php:33`
- **Detalle**: `window.CSRF_TOKEN = '<?= $csrfToken ?>'` — visible en view source. Esto es estándar para SPAs, pero combinado con la falta de CSP podría facilitar extracción vía XSS.

#### B2. .env.example con ADMIN_PASSWORD por defecto predecible
- **Archivo**: `.env.example:22`
- **Detalle**: `ADMIN_PASSWORD=admin1234` podría usarse accidentalmente en producción.

---

### ✅ Lo que está BIEN hecho

1. **Argon2id para passwords** — Algoritmo de hashing más seguro disponible.
2. **Prepared statements en todos los Repos** — Sin SQL injection detectable. Todas las queries usan `$stmt->execute([...])`.
3. **CSRF token generado con `random_bytes(32)`** — Criptográficamente seguro.
4. **Remember tokens con rotación** — Se rota el token en cada validación, limitando ventana de ataque.
5. **Cookies con HttpOnly, SameSite=Lax, Secure** — Buena configuración de cookies.
6. **File upload con whitelist de MIME types** — Solo tipos específicos permitidos.
7. **`basename()` para prevenir path traversal** en document.php.
8. **Archivos en storage/ fuera de public** para chat files.
9. **.env nunca commiteado a Git** — Verificado en historial de Git.
10. **Ownership check en serve.php** — `findByIdAndUser()` verifica que el archivo pertenece al usuario.

---

### 📋 Plan de acción prioritario (pre-publicación)

| # | Severidad | Acción | Esfuerzo |
|---|-----------|--------|----------|
| 1 | 🔴 CRÍTICO | Eliminar `debug-sop.php`, `ingest_lex_web.php`, `index.php.backup` | 2 min |
| 2 | 🔴 CRÍTICO | Proteger contra SSRF en ContentExtractor | 30 min |
| 3 | 🔴 CRÍTICO | Eliminar error disclosure (getMessage, getFile, getLine) | 30 min |
| 4 | 🔴 CRÍTICO | Añadir `session_regenerate_id(true)` tras login | 5 min |
| 5 | 🔴 CRÍTICO | Implementar rate limiting en login | 1 hora |
| 6 | 🟠 ALTO | Cambiar `!==` por `hash_equals()` en CSRF checks | 15 min |
| 7 | 🟠 ALTO | Añadir `Session::requireCsrf()` a ~10 endpoints | 20 min |
| 8 | 🟠 ALTO | Añadir security headers (CSP, HSTS, X-Frame) | 30 min |
| 9 | 🟠 ALTO | Mover podcast WAVs fuera de public/ | 30 min |
| 10 | 🟠 ALTO | Fijar logout para limpiar remember tokens | 5 min |
| 11 | 🟡 MEDIO | Cambiar contraseña admin | 5 min |
| 12 | 🟡 MEDIO | Whitelist de modelos LLM en backend | 15 min |
| 13 | 🟡 MEDIO | Rate limiting en API de chat/gestos | 1 hora |

---

## Feature: Drag & Drop y Paste de archivos multimedia en Chat

### Motivación
Actualmente los archivos solo se pueden adjuntar al chat mediante el botón de adjuntar (clip). Se quiere mejorar la UX permitiendo:
1. **Arrastrar archivos** directamente sobre la ventana de chat o el estado vacío para adjuntarlos
2. **Pegar archivos multimedia** (imágenes, PDFs, etc.) desde el portapapeles con Ctrl/Cmd+V, no solo texto

### Análisis del estado actual

**Archivo principal**: `public/index.php` (~3011 líneas, JS inline)

**Dos zonas de input independientes:**
1. **Estado vacío** (`#empty-state` / `#chat-form-empty`): Formulario hero con textarea, se muestra cuando no hay mensajes
2. **Chat activo** (`#chat-footer` / `#chat-form`): Footer fijo con textarea, se muestra cuando hay mensajes

**Variables de archivos existentes:**
- `currentFiles[]` → archivos adjuntos en chat activo
- `currentFilesEmpty[]` → archivos adjuntos en estado vacío
- Funciones de render: `renderFilesPreview()` y `renderFilesPreviewEmpty()`

**Validación de archivos (ya existe, reutilizable):**
- Tipos permitidos: PDF, PNG, JPEG, GIF, WebP, CSV, XLS, XLSX
- Tamaño máximo: 30MB por archivo
- Lógica duplicada en `fileInput.change` y `fileInputEmpty.change`

**Backend**: Ya soporta FormData upload (`/api/files/upload.php`) y procesamiento multimodal. No requiere cambios.

### Key Challenges

1. **Drag & drop visual feedback**: Necesitamos un overlay/indicador visual cuando el usuario arrastra un archivo sobre la ventana, y desactivarlo cuando sale o suelta
2. **Distinguir entre las dos zonas**: Según si estamos en `emptyState` visible o `chatFooter` visible, los archivos deben ir a `currentFilesEmpty[]` o `currentFiles[]`
3. **Paste multimedia**: El evento `paste` del textarea puede contener `clipboardData.files` (imágenes pegadas desde captura de pantalla) o `clipboardData.items` con tipo `file`. Hay que interceptar solo cuando hay archivos, no cuando se pega texto normal
4. **No romper el pegado de texto**: Si el clipboard solo tiene texto, el comportamiento por defecto debe mantenerse intacto
5. **Modo imagen (nanobanana)**: Cuando imageMode está activo, los archivos adjuntos están deshabilitados. Drag & drop y paste deben respetar este estado

### Diseño propuesto

#### 1. Drag & Drop

- **Zona de drop global**: Escuchar `dragenter`/`dragover`/`dragleave`/`drop` en `#messages-container` (toda el área principal)
- **Overlay visual**: Al detectar drag con archivos, mostrar un overlay semitransparente con borde dashed y texto "Suelta archivos aquí" centrado
- **Al soltar**: Extraer archivos del `DataTransfer`, validar tipos/tamaño, añadir al array correcto (`currentFiles` o `currentFilesEmpty` según estado visible)
- **Al salir sin soltar**: Ocultar overlay

#### 2. Paste multimedia

- Escuchar evento `paste` en ambos textareas (`#chat-input` y `#chat-input-empty`)
- Si `e.clipboardData.files.length > 0` o hay items de tipo `file`:
  - Prevenir default
  - Extraer archivos, validar y añadir al array correspondiente
- Si no hay archivos en el clipboard: no hacer nada (dejar paste de texto normal)

### High-level Task Breakdown

#### Tarea 1: Refactorizar validación de archivos a función compartida
- Extraer la lógica de validación (tipos, tamaño) que está duplicada en `fileInput.change` y `fileInputEmpty.change` a una función `validateAndAddFiles(files, targetArray, renderFn)`
- **Success criteria**: La función acepta un FileList/Array, valida cada archivo, lo añade al array target, y llama al render. Los event listeners de `fileInput.change` y `fileInputEmpty.change` la reutilizan sin duplicar código.

#### Tarea 2: Implementar Drag & Drop en el área de chat
- Añadir un div overlay oculto (`#drop-overlay`) dentro de `#messages-container` (o como sibling)
- Escuchar eventos `dragenter`, `dragover`, `dragleave`, `drop` en `#messages-container`
- En `dragenter`/`dragover`: mostrar overlay si hay archivos en el dataTransfer y no estamos en imageMode
- En `dragleave`: ocultar overlay (con cuidado del bubbling entre hijos)
- En `drop`: ocultar overlay, extraer archivos, llamar a `validateAndAddFiles()` con el array correcto
- **Success criteria**: Al arrastrar un archivo sobre el chat aparece un overlay visual. Al soltarlo, el archivo se adjunta a la conversación (aparece en preview de archivos). Funciona tanto en estado vacío como en chat activo.

#### Tarea 3: Implementar Paste de archivos multimedia
- Añadir event listener `paste` en ambos textareas
- Detectar si clipboard contiene archivos (`clipboardData.files` o items de tipo `file`)
- Si hay archivos: `preventDefault()`, extraer y llamar a `validateAndAddFiles()`
- Si no hay archivos: no intervenir (paste de texto normal)
- **Success criteria**: Pegar una imagen (ej. captura de pantalla) en el textarea la adjunta como archivo. Pegar texto sigue funcionando normalmente. Funciona en ambas zonas (vacío y chat).

#### Tarea 4: Testing manual y ajustes
- Verificar drag & drop en estado vacío
- Verificar drag & drop en chat activo
- Verificar paste de imagen (captura de pantalla)
- Verificar paste de texto no se ve afectado
- Verificar que imageMode bloquea drag & drop y paste de archivos
- **Success criteria**: Todos los flujos funcionan sin regresiones

### Archivos a modificar
- `public/index.php` — Añadir overlay HTML + lógica JS de drag/drop y paste

### Archivos que NO requieren cambios
- Backend (`upload.php`, `chat-stream.php`, `ChatFilesRepo.php`): Ya soportan los archivos
- Validación de tipos ya existe en el frontend, solo hay que centralizarla

---

## Feature: Lead Finder Gesture

### Background and Motivation
Nuevo gesture en inglés para convertir una intención simple del usuario en una búsqueda estructurada de leads. El usuario escribe una petición natural, por ejemplo `schools and high schools in Castellón`, iaiaPRO busca entidades relevantes mediante un proveedor externo, ordena los datos, permite revisión humana y exporta los resultados. En una fase posterior, los leads validados podrán alimentar un flujo de envío de emails.

### Product Scope
Nombre del gesture: `Lead Finder`.

Tono de producto: cercano, claro y profesional. La interfaz debe evitar lenguaje técnico como “scraper” de cara al usuario; internamente puede existir una capa de provider/scraping.

Inputs mínimos del MVP:
- `Search request`: campo principal de lenguaje natural.
- `Max results`: selector corto con valores razonables, por ejemplo 25, 50, 100.

No añadir filtros avanzados en el primer corte. Si el usuario quiere buscar por ubicación, sector o tipo de entidad, debe poder escribirlo en el campo principal.

Campos mínimos de salida:
- Name
- Website
- Email
- Phone
- Address
- Source URL
- Confidence
- Status: `Pending`, `Validated`, `Rejected`

Export inicial:
- CSV obligatorio.
- XLSX deseable si el proyecto ya tiene dependencia simple o si se implementa en backend sin añadir peso innecesario.

### Key Challenges and Analysis

1. **Proveedor API pendiente**
   - No se debe implementar scraping directo contra Google desde PHP.
   - La integración real queda bloqueada hasta elegir proveedor: SerpAPI, Apify, Google Places, Tavily, Firecrawl, Brave Search API u otro.
   - Antes de implementar el provider real, pedir al usuario que etiquete `@web` o proporcione documentación actualizada del proveedor elegido. Crear después un `.md` específico con notas de API.

2. **Arquitectura desacoplada**
   - Crear una interfaz interna tipo `LeadSearchProvider`.
   - Implementar primero un `MockLeadSearchProvider` o `StaticLeadSearchProvider` para poder construir UX, historial, validación y export sin esperar proveedor.
   - El provider real debe devolver datos normalizados en un formato común.

3. **Calidad de datos**
   - Hay que deduplicar por website, email, teléfono y nombre aproximado.
   - Cada fila debe mostrar fuente y confidence para que el usuario pueda validar.
   - No se debe ocultar incertidumbre. Si un email no está disponible, mostrar estado vacío claro.

4. **UX principal**
   - Mantener sidebar e historial como otros gestures.
   - La zona principal debe ser más cuidada que un formulario estándar.
   - Usar una composición de trabajo real, no landing page: prompt arriba, progreso en línea, tabla editable/revisable y acciones persistentes.
   - Debe incluir estados completos: empty, loading, partial results, error, no results, completed.

5. **Futuro email outreach**
   - No enviar emails en el MVP.
   - Preparar modelo de datos con validación humana explícita para evitar mezclar leads encontrados con leads aprobados.
   - En fase futura habrá que contemplar consentimiento, bajas, límites de envío, reputación del dominio y logs.

### UX Direction

Aplicar criterios de `design-taste-frontend` adaptados a iaiaPRO:
- Software UI, no marketing page.
- Paleta neutral con un único acento. Evitar estética morada/azul “AI”.
- Densidad media: tabla legible y rápida de escanear.
- No usar hero sobredimensionado.
- No usar tarjetas anidadas.
- Layout recomendado: panel de búsqueda compacto arriba, debajo una banda de progreso/resultados, y tabla principal con acciones de validación.
- Acciones por fila con iconos y labels claros: validate, reject, open source, edit.
- Loading con skeletons de filas, no spinner genérico.
- Empty state útil: ejemplos de búsquedas reales en botones discretos.
- Error state inline con mensaje técnico mínimo y sugerencia accionable.

### Proposed Data Model

Tabla `lead_finder_runs`:
- `id`
- `user_id`
- `query`
- `max_results`
- `provider`
- `status`: `pending`, `processing`, `completed`, `failed`
- `error_message`
- `created_at`, `started_at`, `completed_at`

Tabla `lead_finder_results`:
- `id`
- `run_id`
- `name`
- `website`
- `email`
- `phone`
- `address`
- `source_url`
- `confidence`
- `status`: `pending`, `validated`, `rejected`
- `raw_data` JSON
- `created_at`, `updated_at`

Nota: evaluar si reutilizar `background_jobs` para proceso async. Recomendación: sí, usar job type `lead-finder` si la búsqueda puede tardar más de unos segundos.

### API / Backend Shape

Endpoints propuestos:
- `POST /api/gestures/lead-finder/search.php`
  - Crea run y job.
  - Input: `query`, `max_results`.
  - Output: `run_id`, `job_id`.
- `GET /api/gestures/lead-finder/get.php?id=RUN_ID`
  - Devuelve run + results.
- `POST /api/gestures/lead-finder/update-result.php`
  - Edita campos y status de una fila.
- `POST /api/gestures/lead-finder/export.php`
  - Exporta CSV/XLSX.
- `GET /api/gestures/lead-finder/history.php`
  - Lista búsquedas del usuario.
- `DELETE /api/gestures/lead-finder/delete.php`
  - Borra una búsqueda y sus resultados.

Provider contract propuesto:
- `search(string $query, int $maxResults): array`
- cada resultado normalizado debe incluir `name`, `website`, `email`, `phone`, `address`, `source_url`, `confidence`, `raw_data`.

### High-level Task Breakdown

#### Task 1: Inspect existing gesture patterns
- Revisar páginas y endpoints de gestures con historial, especialmente audio transcriber, SOP, social media o project analysis.
- Identificar componentes/partials reutilizables para sidebar, historial, layout y delete/load behavior.
- Success criteria: lista concreta de archivos a copiar/adaptar y convenciones confirmadas antes de crear código.

#### Task 2: Database migration for Lead Finder
- Crear migración para `lead_finder_runs` y `lead_finder_results`.
- Incluir foreign keys con `ON DELETE CASCADE`.
- Índices por `user_id`, `run_id`, `status`, `created_at`.
- Success criteria: migración idempotente o segura para producción; documentar comando de ejecución.

#### Task 3: Backend repos and provider interface
- Crear repositorio para runs/results.
- Crear `LeadSearchProvider` y provider mock inicial.
- Añadir deduplicación básica.
- Success criteria: se puede crear un run y guardar resultados mock normalizados desde PHP sin frontend.

#### Task 4: Async job integration
- Añadir job type `lead-finder` en `public/api/jobs/process.php`.
- Actualizar progreso: `Preparing search`, `Collecting sources`, `Normalizing results`, `Saving leads`.
- Success criteria: un job lead-finder pasa de pending a completed y deja resultados asociados al run.

#### Task 5: API endpoints
- Crear endpoints de search/get/history/update/export/delete.
- Aplicar sesión, ownership y CSRF.
- Success criteria: endpoints responden JSON consistente y bloquean acceso a runs de otros usuarios.

#### Task 6: Main gesture UI
- Crear `public/gestos/lead-finder.php`.
- Mantener sidebar/historial igual que otros gestures.
- Diseñar zona principal premium y funcional:
  - single prompt input
  - max results selector compacto
  - examples como quick chips
  - skeleton loading table
  - results table editable
  - validate/reject row actions
  - export action
- Success criteria: flujo completo usable con provider mock, responsive desktop/mobile, sin solapes ni texto cortado.

#### Task 7: Register gesture in navigation
- Añadir Lead Finder a lista de gestures disponibles.
- Usar nombre visible `Lead Finder`.
- Descripción breve: `Find and validate structured leads`.
- Success criteria: aparece en el panel de gestures, historial y navegación coherente.

#### Task 8: Export
- Implementar CSV primero.
- Evaluar XLSX según dependencias disponibles.
- Success criteria: export contiene solo columnas útiles, respeta cambios/validaciones del usuario y descarga correctamente.

#### Task 9: Provider real integration
- Bloqueado hasta elegir proveedor.
- Pedir al usuario documentación actualizada o usar `@web`.
- Crear `docs/apis/<provider>_lead_finder.md`.
- Implementar provider real detrás del contrato existente.
- Success criteria: cambiar provider no requiere tocar UI ni estructura de datos.

#### Task 10: Manual QA
- Probar búsqueda mock.
- Probar historial/load/delete.
- Probar validación/rechazo/edición.
- Probar export.
- Probar responsive.
- Success criteria: el usuario puede validar una búsqueda completa y exportarla sin intervención técnica.

### Project Status Board: Lead Finder

- [x] Task 1: Inspect existing gesture patterns.
- [x] Task 2: Database migration for Lead Finder.
- [x] Task 3: Backend repos and provider interface.
- [x] Task 4: Async job integration.
- [ ] Task 5: API endpoints.
- [ ] Task 6: Main gesture UI.
- [ ] Task 7: Register gesture in navigation.
- [ ] Task 8: Export.
- [ ] Task 9: Provider real integration.
- [ ] Task 10: Manual QA.

### Planner Notes

MVP recomendado: implementar hasta Task 8 con provider mock. Esto permite cerrar UX, historial, validación y exportación sin esperar decisión de API. Cuando el usuario elija proveedor, Task 9 sustituye solo la capa provider.

### Executor Notes

2026-05-13 Task 1 findings:
- Base visual recomendada: `public/gestos/transcriptor-audio.php`, porque ya tiene acceso por feature, unified header, history sidebar, mobile drawer, async job polling, resume via `sessionStorage`, empty/loading/result sections y delete/load history.
- Patrón de historial común: `public/api/gestures/history.php`, `get.php`, `delete.php` con `GestureExecutionsRepo`. Para Lead Finder no basta con `gesture_executions` como almacenamiento principal porque necesitamos editar/validar filas individuales; se puede guardar un resumen en `gesture_executions` opcionalmente, pero el source of truth debe ser `lead_finder_runs` + `lead_finder_results`.
- Patrón de jobs: `BackgroundJobsRepo` + `public/api/jobs/process.php`. Lead Finder debe seguir el estilo de `audio-transcribe`: endpoint específico crea el job y el frontend dispara/pollear `/api/jobs/process.php` + `/api/jobs/status.php`.
- Registro en catálogo: `public/gestos/index.php` añade una card manual protegida por `UserFeatureAccessRepo::hasGestureAccess($userId, 'lead-finder')`.
- Permisos: añadir `gesture:lead-finder` en `UserFeatureAccessRepo::DEFAULT_NEW_USER_ACCESS` si debe estar disponible para nuevos usuarios; también habrá que añadirlo a `available_features` vía migración/seed.
- API job genérica `public/api/jobs/create.php` solo permite `podcast`; no conviene depender de ella para Lead Finder en el MVP. Mejor crear endpoint propio `public/api/gestures/lead-finder/search.php` que valide input, cree run y cree job `lead-finder`.
- JS recomendado: crear archivo propio `public/assets/js/gesture-lead-finder.js` en vez de meter toda la lógica inline. Reutilizar funciones equivalentes a `loadHistory`, `renderHistory`, `deleteFromHistory`, `pollJobStatus`, pero adaptadas a runs/results.
- UX concern: varios gestures actuales tienen CSS inline. Para esta feature, mover CSS nuevo a `public/assets/css/styles.css` o a la hoja global existente, respetando la lección del proyecto de no añadir CSS inline.

2026-05-13 Task 2 findings:
- Añadida migración `docs/migrations/016_lead_finder.sql`.
- Crea `lead_finder_runs` con ownership por usuario, vínculo opcional a `background_jobs`, estado del run y contadores de resultados.
- Crea `lead_finder_results` con campos editables del lead, estado de validación, confidence y `raw_data` JSON.
- Registra `gesture:lead-finder` en `available_features` con textos en inglés.
- Da acceso inicial a superadmins existentes mediante `user_feature_access`.
- No se ha ejecutado la migración todavía en local ni producción.
- 2026-05-14 fix: `users.id` es `BIGINT UNSIGNED`, por lo que `lead_finder_runs.user_id`, `lead_finder_runs.id` y `lead_finder_results.run_id` deben usar tipos compatibles. Corregida la migración tras error MySQL errno 150 en producción.
- 2026-05-14 fix 2: eliminada la FK opcional de `lead_finder_runs.job_id` contra `background_jobs.id`. El vínculo no es crítico y evita fallos por tipos históricos inconsistentes en `background_jobs`; `job_id` queda indexado.

2026-05-14 Task 3 findings:
- Añadido `src/LeadFinder/LeadSearchProvider.php` como contrato de provider.
- Añadido `src/LeadFinder/MockLeadSearchProvider.php` con resultados deterministas, campos normalizados y deduplicación básica por web/email/name.
- Añadido `src/LeadFinder/LeadFinderRepo.php` para crear runs, asociar job, marcar estado, guardar/reemplazar resultados, listar historial, editar filas y refrescar contadores.
- Registrados los nuevos archivos en `src/App/bootstrap.php`.
- Validado con `php -l` en los nuevos archivos y prueba PHP del provider mock con query `schools and high schools in Castellón`.

2026-05-14 Task 4 findings:
- Añadido job type `lead-finder` en `public/api/jobs/process.php`.
- Añadida función `processLeadFinderJob()` que lee `run_id`, `query`, `max_results`, marca el run como processing, usa `MockLeadSearchProvider`, guarda resultados en `lead_finder_results`, marca run completed y registra usage log.
- El worker emite snapshots: `Preparing search...`, `Collecting sources...`, `Normalizing results...`, `Saving leads...`.
- Si falla el procesamiento, el job queda failed por el catch global y el run queda `failed` mediante `LeadFinderRepo::markRunFailed()`.
- Validado con `php -l public/api/jobs/process.php`.

---

# Current Status / Progress Tracking

- 2026-04-13 (Executor): Iniciada implementación de catálogo de modelos editable para superadmin.
- 2026-04-13 (Executor): Añadida migración `docs/migrations/015_llm_models.sql` con tabla `llm_models` + seed inicial.
- 2026-04-13 (Executor): Añadido repositorio `src/Repos/LlmModelsRepo.php`.
- 2026-04-13 (Executor): Añadidos endpoints:
  - `public/api/models/list.php` (lista activa para selector)
  - `public/api/admin/models/list.php` (lista completa para superadmin)
  - `public/api/admin/models/create.php` (alta)
  - `public/api/admin/models/delete.php` (baja)
- 2026-04-13 (Executor): `public/index.php` actualizado para cargar modelos dinámicamente y gestionar alta/baja desde frontend (botón ⚙ con prompts).
- 2026-05-13 (Executor): Diagnóstico de transcripción larga atascada. El worker reseteaba jobs `processing` tras 15 minutos aunque `BACKGROUND_JOB_MAX_SECONDS` permite ejecuciones largas; además el frontend relanzaba `/api/jobs/process.php` cada 30 segundos mientras el job seguía activo. Ajustado el reset a una ventana dependiente del runtime, cambiado el frontend para despertar worker solo al inicio o si el job está `pending`, y añadidos snapshots de progreso antes de segmentar y antes de cada segmento.
- 2026-05-13 (Executor): Segundo diagnóstico de transcripción larga. El job avanzaba a `Analyzing audio duration...` y quedaba bloqueado, señal de `ffprobe` sin timeout. Añadido runner con timeout para `ffprobe` y `ffmpeg`; si no puede obtener duración pero el archivo pesa >= 8MB, se intenta segmentar igualmente para evitar enviar audios largos completos a Gemini.
- 2026-05-13 (Planner): Planificado nuevo gesture `Lead Finder` con UX premium, provider desacoplado, historial, validación de resultados y export. La integración real de API queda pendiente de elegir proveedor y revisar documentación actualizada.
- 2026-05-13 (Executor): Lead Finder Task 1 completada. Inspeccionados patrones de gestures, historial, jobs, permisos y registro en catálogo. Próximo paso: migración de BD para `lead_finder_runs` y `lead_finder_results`.
- 2026-05-13 (Executor): Lead Finder Task 2 completada. Creada migración `016_lead_finder.sql` para runs/results y registro del feature. Pendiente de ejecutar cuando el usuario lo valide.
- 2026-05-14 (Executor): Lead Finder Task 3 completada. Añadidos repo backend, contrato de provider y provider mock; sintaxis validada y provider probado.
- 2026-05-14 (Executor): Lead Finder Task 4 completada. Integrado job type `lead-finder` en el worker con progreso, provider mock y persistencia de resultados.

# Executor's Feedback or Assistance Requests

- Milestone completado: ya puedes añadir y eliminar modelos sin tocar código, desde el chat como superadmin.
- Pendiente validación manual por parte del usuario:
  1. Ejecutar migración `015_llm_models.sql` en la BD.
  2. Recargar sesión de superadmin y comprobar que el selector carga desde API.
  3. Probar `add` y `remove` desde el botón de gestión en ambos selectores (empty/chat).
- Solicitud al planner/usuario: confirmar si este MVP por prompts es suficiente o si quieres que en el siguiente paso lo convierta a modal completo con edición inline/reordenación.
- Lead Finder: Task 1 completada. Solicitud al usuario/planner: validar que avancemos a Task 2, que crea migración de base de datos para runs/results. No se ha tocado todavía la BD ni código funcional.
- Lead Finder: Task 2 completada. Solicitud al usuario/planner: validar migración antes de ejecutarla; siguiente paso de implementación sería Task 3, repositorio backend + provider mock.
- Lead Finder: Task 3 completada. Siguiente paso sugerido: Task 4, integrar job type `lead-finder` en `public/api/jobs/process.php`.
- Lead Finder: Task 4 completada. Siguiente paso sugerido: Task 5, crear endpoints `search/get/history/update-result/export/delete`.

# Lessons

- Para cambios de configuración editable por superadmin, conviene desacoplar la lista hardcodeada del frontend y moverla a una tabla + API admin, manteniendo un endpoint de solo lectura para UI (`/api/models/list.php`).
- En jobs largos de audio, no usar una ventana fija corta de `resetStuckJobs()`. Debe ser mayor que `BACKGROUND_JOB_MAX_SECONDS`, porque si no se reinician jobs legítimos en mitad de la transcripción y pueden lanzarse workers duplicados desde el polling del frontend.
- Los comandos externos (`ffprobe`, `ffmpeg`) deben ejecutarse con timeout explícito. `exec()` sin timeout puede dejar un job indefinidamente en la misma fase si un contenedor de audio bloquea el análisis.
- `.gitignore` ignoraba `migrations/`, lo que también ocultaba SQL nuevos bajo `docs/migrations/`. Para nuevas migraciones versionadas, mantener la excepción `!docs/migrations/` y `!docs/migrations/*.sql`; si no, `git add .` no las sube.
- En migraciones con foreign keys, confirmar que los tipos coinciden exactamente con la tabla referenciada. `users.id` usa `BIGINT UNSIGNED`; usar `INT` en tablas nuevas provoca MySQL errno 150.
- Evitar foreign keys no esenciales contra tablas antiguas con historial de tipos inconsistente. Para `lead_finder_runs.job_id`, basta índice normal y validación en aplicación.
