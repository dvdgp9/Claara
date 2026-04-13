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
- 2025-12-31: **CONSCIENCIA DE PLATAFORMA**: Actualizado `system_prompt.md` para que Ebonia sea consciente de sus capacidades (adjuntar archivos, modo nanobanana, gestos) y limitaciones (no generación de archivos descargables .pptx/.pdf, no acceso a Teams/M365). Evita promesas falsas de archivos.
- 2025-12-31: **SOPORTE DE TABLAS EN CHAT**: Añadido soporte básico para renderizar tablas Markdown en el chat general (`public/index.php`). Incluye estilos CSS en `public/includes/head.php` y lógica de conversión en la función `mdToHtml`.
- 2025-12-31: **FIX TABLAS**: Corregida la expresión regular en `mdToHtml` para capturar bloques de tablas multilínea de forma robusta.
- 2025-12-31: **REVISIÓN DE COLISIONES EN BD**: Analizada la estructura de la base de datos para evitar colisiones de nombres entre usuarios. Creada la migración `docs/migrations/007_fix_name_collisions.sql` que añade claves únicas compuestas en `folders`, `voices` y `gestures`.
- 2025-12-31: **MEJORAS UI ADMIN**: Añadido botón de mostrar/ocultar contraseña en la gestión de usuarios y detección de OS para atajos de teclado (CMD/Ctrl + Enter).
- 2025-12-31: **SELECTOR DE MODELOS (SUPERADMIN)**: Implementado selector de modelos LLM al lado del botón de Nanobanana exclusivo para superadministradores. Incluye GLM 4.7, Gemini 3 Flash, Deepseek v3.2 y Xiaomi Mimo v2. Sincronización automática entre vistas y envío del modelo seleccionado al backend.
- 2026-01-30: **SOP Generator**: Historial compacto con botones de eliminar y editar título; añadido endpoint para actualización de título y lógica JS de edición.
- 2026-04-08: **CHAT UX ARCHIVOS**: Implementado drag & drop de archivos y pegado multimedia (clipboard) en `public/index.php` para ambos estados (vacío y chat activo). Refactorizada validación de archivos a función reutilizable (`validateAndAddFiles`). Añadido overlay visual de drop y mensajes de aviso cuando `imageMode` está activo (adjuntar/arrastrar/pegar bloqueado).
- 2026-04-13: **FIX HTTP 400 en conversaciones existentes**: Corregido bug en `OpenRouterClient.php` donde mensajes con contenido vacío (ej: respuestas solo-imagen de nanobanana) generaban `content: []` (array vacío) en el payload enviado a OpenRouter, causando rechazo HTTP 400. Fix: omitir mensajes sin contenido real en ambos métodos (streaming y no-streaming). Mejorado diagnóstico de errores: ahora se captura el body de error de OpenRouter en modo streaming para mostrar el mensaje real en lugar de solo "Error HTTP 400".

---

## Feature: Sistema de Voces

### Motivación
Las "voces" son asistentes especializados con conocimiento profundo de dominios específicos. A diferencia del chat genérico, cada voz tiene contexto especializado y acceso a documentación relevante.

### Voces planificadas
1. **Lex** (primera voz) - Asistente legal de Ebone: convenios, normativas, artículos legales
2. **Cubo** - Asistente CUBOFIT: productos fitness, especificaciones técnicas
3. **Uniges** - Asistente UNIGES-3: gestión deportiva, servicios municipales

### Decisión técnica: RAG vs Context directo

**Recomendación: RAG (Retrieval Augmented Generation)**

| Aspecto | Context directo | RAG |
|---------|-----------------|-----|
| Documentos pequeños (<50KB total) | ✅ Viable | Overkill |
| Documentos grandes (convenios, normativas) | ❌ Excede contexto | ✅ Ideal |
| Precisión en citas | ❌ Aproximada | ✅ Exacta con fuentes |
| Coste por request | Alto (todo el contexto) | Bajo (solo chunks relevantes) |
| Escalabilidad | ❌ Limitada | ✅ Ilimitada |

**Implementación RAG propuesta:**
1. **Ingesta**: Procesar documentos legales → chunks de ~500 tokens
2. **Embeddings**: Usar modelo de embeddings (ej: text-embedding-3-small de OpenAI, o Gemini embeddings)
3. **Vector Store**: SQLite con extensión vector, o tabla MySQL con búsqueda por similitud
4. **Retrieval**: Top-k chunks relevantes según query del usuario
5. **Generation**: LLM recibe chunks + query → respuesta con citas

**Alternativa simplificada (MVP):**
- Archivos markdown en `docs/context/voices/lex/`
- ContextBuilder especializado que carga solo los docs de la voz activa
- Funciona si total de docs < 100KB por voz

### Tareas de implementación

1. [ ] **Crear estructura `/public/voices/`**
   - `lex.php` - Página de la voz Lex
   - JS modular en `/assets/js/voice-lex.js`
   - Success: Estructura lista

2. [ ] **Crear UI de voz Lex**
   - Similar a write-article.php pero orientado a consultas
   - Sidebar con historial de consultas
   - Área de chat especializada
   - Panel lateral con documentos disponibles
   - Success: UI funcional

3. [ ] **Crear contexto especializado Lex**
   - `docs/context/voices/lex/` con documentos legales
   - System prompt específico para asistente legal
   - Success: Contexto cargado correctamente

4. [ ] **Implementar endpoint `/api/voices/chat.php`**
   - Recibe: voice_id, message, history
   - Carga contexto especializado de la voz
   - Retorna respuesta con posibles citas
   - Success: Respuestas legales precisas

5. [ ] **(Futuro) Implementar RAG**
   - Cuando los documentos excedan el contexto
   - Vector store + embeddings
   - Success: Búsqueda semántica en documentos

---

## Feature: Migración a OpenRouter

### Motivación
Consolidar todos los proveedores LLM (Gemini, Qwen, etc.) en un único gateway: **OpenRouter**. Esto simplifica la gestión de API keys, permite cambiar modelos sin código, y unifica la facturación.

### Decisiones técnicas
- **Endpoint**: `https://openrouter.ai/api/v1/chat/completions` (compatible OpenAI)
- **Modelos**: Se especifican como `provider/model` (ej: `google/gemini-2.5-flash`, `qwen/qwen-plus`)
- **Headers extras**: `HTTP-Referer` y `X-Title` opcionales para rankings
- **API Key**: Único para todos los modelos

### Archivos a modificar
1. **Nuevo**: `src/Chat/OpenRouterClient.php` - Cliente único basado en API OpenAI
2. **Nuevo**: `src/Chat/OpenRouterProvider.php` - Implementa LlmProvider
3. **Modificar**: `src/Chat/LlmProviderFactory.php` - Añadir caso 'openrouter'
4. **Modificar**: `.env` - Añadir `OPENROUTER_API_KEY` y `OPENROUTER_MODEL`
5. **Modificar**: `public/api/chat.php` - Actualizar requires y lógica de modelo
6. **Modificar**: `public/api/faq.php` - Usar OpenRouter
7. **Modificar**: `public/api/gestures/generate.php` - Usar OpenRouter
8. **Modificar**: `public/api/voices/chat.php` - Usar OpenRouter

### Tareas de implementación

1. [x] **Crear OpenRouterClient.php**
   - Endpoint: `https://openrouter.ai/api/v1/chat/completions`
   - Formato mensajes: OpenAI compatible (system, user, assistant)
   - Soporte para imágenes base64
   - Temperature y max_tokens opcionales
   - ✅ Completado

2. [x] **Crear OpenRouterProvider.php**
   - Implementa LlmProvider
   - Usa ContextBuilder para system prompt
   - ✅ Completado

3. [x] **Actualizar LlmProviderFactory.php**
   - Añadir caso 'openrouter'
   - Cambiar default a 'openrouter'
   - ✅ Completado

4. [x] **Actualizar .env**
   - Añadir OPENROUTER_API_KEY
   - Añadir OPENROUTER_MODEL (default)
   - ✅ Completado

5. [x] **Actualizar endpoints API**
   - chat.php, faq.php, gestures/generate.php, voices/chat.php
   - Cambiar requires a OpenRouter
   - ✅ Completado

6. [x] **Testing**
   - ✅ Chat, FAQ, Gestos, Voces funcionando
   
7. [x] **Limpieza y optimización**
   - ✅ Modelo por defecto: `openrouter/auto` (selección automática)
   - ✅ Captura modelo real usado en respuesta (para tracking)
   - ✅ Eliminado parámetro `$provider` de LlmProviderFactory (ignorado)
   - ✅ Eliminados archivos legacy: GeminiClient, GeminiProvider, QwenClient, QwenProvider
   - ✅ Limpiado .env: solo OPENROUTER_API_KEY y OPENROUTER_MODEL

---

## Feature: Persistencia de documentos en Chat

### Motivación
Los documentos subidos al chat (PDFs, imágenes) se envían como base64 en cada request pero no se almacenan. Al recargar la página o volver a una conversación antigua, los archivos desaparecen. Se requiere persistencia con limpieza automática a los 5 días.

### Diseño técnico

**Tabla `chat_files`**:
```sql
CREATE TABLE chat_files (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  conversation_id INT NULL,
  message_id INT NULL,
  original_name VARCHAR(255) NOT NULL,
  stored_name VARCHAR(255) NOT NULL,
  mime_type VARCHAR(100) NOT NULL,
  size_bytes INT NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE SET NULL,
  INDEX idx_expires (expires_at),
  INDEX idx_user_conv (user_id, conversation_id)
);
```

**Carpeta física**: `/storage/chat-files/` (fuera de public, no accesible directamente)

**Endpoints**:
- `POST /api/files/upload.php` - Sube archivo, devuelve file_id y URL de servicio
- `GET /api/files/serve.php?id=X` - Sirve archivo con verificación de permisos

**Limpieza automática**:
- Lazy cleanup al inicio de upload.php: `DELETE FROM chat_files WHERE expires_at < NOW()`
- También borrar archivos físicos correspondientes

### Tareas de implementación

1. [ ] Crear migración SQL para tabla `chat_files`
2. [ ] Crear carpeta `/storage/chat-files/`
3. [ ] Crear `ChatFilesRepo.php` con CRUD básico
4. [ ] Crear `POST /api/files/upload.php`
5. [ ] Crear `GET /api/files/serve.php`
6. [ ] Modificar `chat.php` para asociar file_id al mensaje
7. [ ] Modificar frontend para mostrar archivos en mensajes del historial
8. [ ] Modificar tabla `messages` para guardar file_id
9. [ ] Testing

---

## Feature: Generación de Imágenes con nanobanana 🍌

### Motivación
Añadir capacidad de generación de imágenes al chat principal usando el modelo `google/gemini-3-pro-image-preview` de OpenRouter. Branding interno: "nanobanana".

### Documentación OpenRouter
- Endpoint: mismo `/api/v1/chat/completions`
- Parámetro clave: `modalities: ['image', 'text']`
- Respuesta: `choices[0].message.images[]` con imágenes en base64

### Diseño UX

**1. Toggle de modo imagen en el footer**
- Botón junto al de adjuntar archivo
- Icono: `iconoir-media-image` normal, con glow amarillo/naranja cuando activo
- Color activo: gradiente naranja/amarillo (tema banana)
- Tooltip: "Generar imagen con nanobanana 🍌"

**2. Indicador visual activo**
- Botón con borde/glow naranja pulsante
- Placeholder cambia a "Describe la imagen que quieres crear..."
- Pequeño badge "🍌" junto al input

**3. Comportamiento al enviar**
- Modelo: `google/gemini-3-pro-image-preview`
- Payload incluye `modalities: ['image', 'text']`
- NO compatible con archivos adjuntos (deshabilitar adjuntar en modo imagen)

**4. Renderizado de imágenes**
- Imagen inline en burbuja del asistente (max-width: 100%, rounded)
- Click abre lightbox simple para ver en grande
- Botón de descarga debajo de la imagen
- Texto del asistente se muestra encima/debajo de la imagen

**5. Persistencia**
- Guardar imagen base64 en campo `images` del mensaje en BD
- Al cargar historial, renderizar imágenes guardadas

### Tareas de implementación

1. [ ] **Backend: Modificar OpenRouterClient**
   - Aceptar parámetro `modalities` opcional
   - Añadirlo al payload si está presente
   - Parsear `images` de la respuesta y devolverlas

2. [ ] **Backend: Modificar chat.php**
   - Aceptar parámetro `image_mode` del frontend
   - Si `image_mode=true`: forzar modelo y añadir modalities
   - Devolver `images` en la respuesta

3. [ ] **Frontend: Añadir botón toggle imagen**
   - Variable `imageMode` en JS
   - Botón con estados visual activo/inactivo
   - Al activar: cambiar placeholder, deshabilitar adjuntar

4. [ ] **Frontend: Modificar handleSubmit**
   - Si `imageMode`: enviar `image_mode: true` al backend
   - No enviar archivos en modo imagen

5. [ ] **Frontend: Modificar append para imágenes**
   - Si respuesta tiene `images`: renderizar cada imagen
   - Añadir botón de descarga
   - Click para lightbox

6. [ ] **Lightbox simple**
   - Modal fullscreen con la imagen
   - Click fuera o X para cerrar

7. [ ] **Persistencia de imágenes**
   - Añadir campo `images` JSON a tabla messages
   - Guardar imágenes generadas
   - Cargar y mostrar en historial

8. [ ] **Testing**

---

## Feature: RAG para Lex (Asistente Legal)

### Motivación
La voz Lex necesita acceder a ~20 artículos de convenios laborales (~30 páginas c/u = **~600 páginas totales**). El sistema actual (`VoiceContextBuilder`) concatena todos los `.md` en el system prompt, lo que:
- **Excede límites de contexto** (~150K tokens para Gemini, pero el coste por request sería brutal)
- **Degrada precisión**: El LLM "se pierde" en documentos largos
- **Escala mal**: Añadir más documentos empeora todo

**Objetivo**: Implementar RAG (Retrieval Augmented Generation) para buscar solo los fragmentos relevantes antes de cada respuesta.

### Recursos disponibles
- **VPS**: 4 vCPU, 4GB RAM
- **Carga esperada**: 2-3 usuarios concurrentes (picos)
- **Stack actual**: PHP 8.2, MySQL, OpenRouter para LLM

### Volumen de datos estimado
- 20 artículos × 30 páginas × ~500 palabras/página = **~300K palabras**
- Chunks de ~500 tokens → **~800-1200 chunks**
- Embeddings (1536 dims, float32) → **~5-7 MB** de vectores
- **Conclusión**: Dataset pequeño, cabe en RAM fácilmente

---

### Opciones de implementación

#### Opción A: SQLite + sqlite-vss (embebido)
**Complejidad**: Baja | **RAM adicional**: 0 (embebido en PHP)

```
Arquitectura:
┌─────────────┐     ┌──────────────┐     ┌─────────────┐
│   PHP App   │────▶│ SQLite + vss │────▶│  Embeddings │
│             │     │  (archivo)   │     │   (OpenAI)  │
└─────────────┘     └──────────────┘     └─────────────┘
```

**Pros**:
- Sin proceso adicional (archivo .db)
- PHP puede acceder vía PDO + extensión
- Perfecto para datasets pequeños (<100K chunks)
- Backup = copiar un archivo

**Contras**:
- Requiere compilar extensión sqlite-vss (o usar FFI)
- Rendimiento limitado con millones de vectores (no es nuestro caso)
- Menos maduro que alternativas

**Coste**: Solo embeddings (~$0.0001 por 1K tokens → ~$0.03 total para indexar)

---

#### Opción B: Qdrant (vector DB standalone)
**Complejidad**: Media | **RAM adicional**: ~300-500 MB

```
Arquitectura:
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   PHP App   │────▶│   Qdrant    │────▶│  Embeddings │
│             │     │  (Docker)   │     │   (OpenAI)  │
└─────────────┘     └──────────────┘     └─────────────┘
```

**Pros**:
- Muy eficiente en memoria (Rust)
- API REST simple
- Filtrado por metadatos (ej: tipo de documento, sección)
- Persiste en disco automáticamente
- Producción-ready

**Contras**:
- Requiere Docker o binario
- Proceso adicional corriendo
- Overkill para <10K chunks

**Coste**: Solo embeddings + VPS RAM

---

#### Opción C: PostgreSQL + pgvector
**Complejidad**: Media | **RAM adicional**: Variable (depende de config)

**Pros**:
- Si ya usáis PostgreSQL, sin nueva infra
- SQL estándar para queries híbridas
- Muy maduro

**Contras**:
- **No usamos PostgreSQL** (tenemos MySQL)
- Migrar BD solo por esto no tiene sentido

**Veredicto**: ❌ Descartada (no aplica)

---

#### Opción D: Meilisearch (búsqueda híbrida)
**Complejidad**: Media | **RAM adicional**: ~200-400 MB

**Pros**:
- Búsqueda keyword + semántica
- Muy rápido
- API REST sencilla
- Typo-tolerant (útil para términos legales)

**Contras**:
- No es vector DB puro
- Embeddings integrados (menos control)
- Otro proceso corriendo

---

#### Opción E: Servicio cloud (Pinecone/Weaviate)
**Complejidad**: Baja | **RAM adicional**: 0

**Pros**:
- Sin infraestructura local
- Escalabilidad infinita
- Free tier disponible

**Contras**:
- Latencia de red adicional
- Dependencia externa
- Free tier limitado (Pinecone: 100K vectores)
- Datos salen del VPS

---

#### Opción F: MySQL Full-Text + Embeddings en tabla
**Complejidad**: Baja | **RAM adicional**: 0

```sql
CREATE TABLE lex_chunks (
  id INT PRIMARY KEY,
  document_id VARCHAR(100),
  chunk_text TEXT,
  embedding BLOB,  -- 1536 floats serialized
  FULLTEXT idx_text (chunk_text)
);
```

**Pros**:
- Sin nueva infraestructura
- MySQL ya está
- Full-text para búsqueda keyword
- Embeddings para reranking

**Contras**:
- Sin búsqueda vectorial nativa (hay que calcular similitud en PHP)
- Lento para >10K chunks
- Workaround, no solución elegante

---

### ⭐ Recomendación: Opción B (Qdrant)

**Razones**:
1. **Bajo consumo RAM** (~300MB) - Cabe perfecto en 4GB
2. **Producción-ready** - Usado en empresas serias
3. **API REST** - Fácil integrar desde PHP
4. **Filtros por metadatos** - Útil para filtrar por convenio/sección
5. **Escala si crece** - Si añadís más voces/documentos
6. **Docker** - Un `docker-compose up -d` y listo

**Alternativa si no queréis Docker**: Opción A (SQLite + vss), pero requiere más setup inicial.

---

### Arquitectura propuesta (Qdrant)

```
┌─────────────────────────────────────────────────────────────┐
│                         INGESTA (1 vez)                     │
├─────────────────────────────────────────────────────────────┤
│  PDFs/Markdown → Chunks (~500 tokens) → Embeddings → Qdrant │
└─────────────────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────────────────┐
│                      CONSULTA (cada request)                │
├─────────────────────────────────────────────────────────────┤
│  1. Usuario pregunta: "¿Cuántos días de vacaciones?"        │
│  2. Embedding de la pregunta                                │
│  3. Qdrant: top-5 chunks más similares                      │
│  4. LLM recibe: system prompt + chunks + pregunta           │
│  5. Respuesta con citas: "Según Art. 23 del Convenio..."    │
└─────────────────────────────────────────────────────────────┘
```

### Modelo de embeddings

| Modelo | Dimensiones | Coste | Rendimiento |
|--------|-------------|-------|-------------|
| `text-embedding-3-small` (OpenAI) | 1536 | $0.02/1M tokens | Muy bueno |
| `text-embedding-3-large` (OpenAI) | 3072 | $0.13/1M tokens | Mejor |
| Gemini embedding (via OpenRouter) | 768 | Incluido | Bueno |

**Recomendación**: `text-embedding-3-small` - Balance coste/calidad, ya tenéis OpenRouter.

---

### Tareas de implementación

1. [x] **Preparar documentos**
   - Convertir PDFs a Markdown/texto limpio
   - Estructurar en carpeta `docs/context/voices/lex/convenios/`
   - Success: 20 archivos listos

2. [x] **Configurar Qdrant**
   - docker-compose.yml creado con Qdrant
   - Success: Listo para `docker-compose up -d`

3. [x] **Crear script de ingesta**
   - `scripts/rag/ingest_lex.php` creado
   - Chunking con overlap (~500 tokens)
   - Embeddings via OpenAI text-embedding-3-small
   - Success: Script listo

4. [x] **Crear servicio RAG**
   - `src/Rag/QdrantClient.php` - Cliente HTTP para Qdrant
   - `src/Rag/EmbeddingService.php` - Genera embeddings
   - `src/Rag/LexRetriever.php` - Busca chunks relevantes
   - Success: Servicios creados

5. [x] **Integrar con VoiceContextBuilder**
   - Añadidos métodos `hasRagEnabled()`, `initRetriever()`, `buildSystemPromptWithRag()`
   - Fallback automático a documentos estáticos si RAG no disponible
   - Success: Integración completa

6. [x] **Modificar endpoint voices/chat.php**
   - Usa RAG automáticamente si está configurado
   - Success: Endpoint actualizado

7. [ ] **Testing y ajustes**
   - Probar preguntas típicas
   - Ajustar top-k (5-10 chunks)
   - Verificar citas correctas
   - Success: Respuestas precisas con fuentes

---

## Feature: Editor de Imágenes con Nanobanana 🍌

### Motivación
Nuevo gesto para generar imágenes corporativas usando Nanobanana (Gemini 3 Pro Vision en OpenRouter). Interfaz con selectores visuales para formato, estilo, paleta de color, iluminación y composición. Los prompts se construyen automáticamente combinando las opciones seleccionadas con la descripción del usuario.

### Opciones del selector (adaptadas para contexto corporativo)

**Formato (Ratio)**:
- 1:1 (Cuadrado - redes sociales)
- 3:4 (Vertical - stories)
- 4:3 (Horizontal - presentaciones)
- 16:9 (Panorámico - banners)
- 9:16 (Móvil vertical)

**Estilo**:
- Ninguno
- Fotográfico (realista)
- Ilustración digital
- Corporativo moderno
- Minimalista
- 3D Render
- Flat Design
- Isométrico

**Paleta de Color**:
- Ninguno
- Tonos cálidos
- Tonos fríos
- Colores corporativos (Ebone)
- Monocromático
- Pasteles
- Blanco y negro
- Vibrante

**Iluminación**:
- Ninguno
- Luz natural / Sunlight
- Estudio profesional
- Dramática
- Suave/Difusa
- Contraluz (Backlight)
- Hora dorada (Golden Hour)
- Luz volumétrica

**Composición**:
- Ninguno
- Fondo desenfocado (Bokeh)
- Primer plano (Close up)
- Plano general (Wide angle)
- Vista cenital (From above)
- Contrapicado (From below)
- Macrofotografía
- Espacio negativo

### Construcción de prompts
El sistema construirá prompts estructurados combinando:
1. Descripción del usuario
2. Estilo visual seleccionado
3. Paleta de colores
4. Iluminación
5. Composición
6. Calidad (8K, alta resolución, etc.)

### Tareas de implementación

1. [x] **Crear página del gesto** `/public/gestos/editor-imagenes.php`
   - Estructura: left-tabs + sidebar historial + header unificado
   - Selectores visuales con tabs (Formato, Estilo, Color, Luz, Composición)
   - Campo de descripción principal con textarea
   - Preview de imagen generada + lightbox
   - ✅ Completado

2. [x] **Crear JS del gesto** `/public/assets/js/gesture-image-editor.js`
   - Lógica de selectores con radio buttons y tabs
   - Construcción del prompt profesional con mapas de opciones
   - Llamada a API con modalities=['image', 'text']
   - Renderizado de imagen base64 + descarga
   - Historial funcional
   - ✅ Completado

3. [x] **Crear endpoint** `/public/api/gestures/generate-image.php`
   - Usa OpenRouterClient con modalities=['text', 'image']
   - Modelo: google/gemini-2.0-flash-exp:free
   - Guarda en gesture_executions (output_data con imagen base64)
   - ✅ Completado

4. [x] **Actualizar registros**
   - Añadir gesto a $gesturesList en left-tabs.php
   - Añadir tarjeta en /gestos/index.php
   - Crear migración docs/migrations/008_add_image_editor_gesture.sql
   - ✅ Completado

5. [ ] **Testing** (pendiente de usuario)
   - Ejecutar migración: `php scripts/migrate.php` o aplicar SQL manualmente
   - Verificar acceso al gesto
   - Probar generación de imágenes
   - Verificar historial y descarga

---

## Feature: Chat Streaming con Stop/Regeneración Parcial

### Motivación
Integrar nuevas funcionalidades de chat desde el `implementation_kit/` de otra app similar:
- **Streaming SSE**: Respuestas en tiempo real (en lugar de request→response)
- **Stop Generation**: Botón para cancelar respuestas largas/incorrectas
- **Partial Regeneration**: Seleccionar texto en respuestas del asistente para editar/regenerar solo esa parte
- **Dynamic Highlighting**: Feedback visual con pulsos verdes para ediciones

### Archivos del implementation_kit (en inglés → adaptar a español)

**JavaScript modules** (`implementation_kit/js/`):
- `chat-state.js` - Estado centralizado (streaming, selection)
- `chat-streaming.js` - SSE con AbortController
- `chat-selection.js` - Selección de texto + toolbar flotante + modal edición
- `chat-ui.js` - Renderizado de mensajes (mdToHtml, createMessageElement)
- `chat-api.js` - API calls con CSRF
- `index.js` - Entry point exportando todo

**Backend** (`implementation_kit/api/`):
- `chat-stream.php` - Endpoint SSE para streaming
- `chat-regenerate.php` - Endpoint para regeneración parcial (4 estrategias fuzzy matching)

### Análisis de integración

**Ebonia actualmente**:
- Todo el JS está inline en `index.php` (~1500 líneas)
- Usa `chat.php` con request→response tradicional
- Ya existe `chat-stream.php` vacío (0 bytes)
- Textos en español

**Estrategia de integración**:
1. **Opción A**: Modularizar JS en archivos separados (como implementation_kit)
2. **Opción B**: Integrar lógica inline adaptando al código existente ← **Recomendada** (menos disruptivo)

### Tareas de implementación

1. [x] **Backend: Implementar `chat-stream.php`**
   - ✅ Copiado y adaptado de `implementation_kit/api/chat-stream.php`
   - ✅ Mensajes de error en español
   - ✅ Compatible con ChatFilesRepo, UsageLogRepo, etc.
   - Success: Endpoint SSE funcional

2. [x] **Backend: Implementar `chat-regenerate.php`**
   - ✅ Creado `/public/api/chat-regenerate.php`
   - ✅ Adaptado a español
   - ✅ Añadido método `updateContent()` a MessagesRepo
   - Success: Regeneración parcial funciona

3. [x] **Frontend: Añadir estado de streaming**
   - ✅ Variables: `isGenerating`, `abortController`, `currentStreamingBubble`, `currentStreamingMessageId`
   - ✅ Variables de selección: `selectedText`, `selectedMessageId`
   - Success: Estado gestionado correctamente

4. [x] **Frontend: Implementar streaming SSE**
   - ✅ Función `streamChat()` con AbortController
   - ✅ Parseo de eventos SSE (chunk, meta, images, annotations, error, conversation)
   - ✅ Funciones `updateStreamingMessage()` y `finalizeStreamingMessage()`
   - Success: Respuestas se muestran en tiempo real

5. [x] **Frontend: Botón "Detener generación"**
   - ✅ Botón flotante rojo con `showStopButton()` / `hideStopButton()`
   - ✅ Texto: "Detener generación" (español)
   - ✅ Funcionalidad de cancelación con `stopGeneration()`
   - Success: Usuario puede cancelar respuestas

6. [x] **Frontend: Selección de texto en respuestas**
   - ✅ Toolbar flotante (`#selection-toolbar`) con botones "Editar" / "Regenerar"
   - ✅ Listener `selectionchange` para detectar selección en mensajes del asistente
   - ✅ Posicionamiento dinámico de toolbar
   - Success: Selección detectada, toolbar aparece

7. [x] **Frontend: Modal de edición**
   - ✅ Modal `#selection-edit-modal` con preview del texto seleccionado
   - ✅ Campo para instrucciones con placeholder en español
   - ✅ Botones "Cancelar" / "Aplicar cambios"
   - ✅ Soporte Cmd/Ctrl+Enter para enviar
   - Success: Modal funcional

8. [x] **Frontend: Llamada a regeneración**
   - ✅ Función `submitRegeneration()` conecta con `/api/chat-regenerate.php`
   - ✅ Actualiza mensaje en DOM tras edición
   - ✅ Efecto highlight verde temporal tras edición exitosa
   - Success: Edición parcial funciona end-to-end

9. [ ] **Testing completo** (pendiente de usuario)
   - Probar streaming de respuestas
   - Probar botón de detener
   - Probar selección + regeneración en mensajes del asistente
   - Verificar que no rompe funcionalidad existente (nanobanana, web search, archivos)

---

## Feature: Búsqueda Web en Chat General

### Motivación
Añadir un botón en el chat general que active la búsqueda online de OpenRouter. Cuando está activo, las respuestas de Ebonia se enriquecen con información actualizada de internet. También actualizar el contexto de Ebonia para que sepa que tiene esta capacidad y pueda sugerir al usuario activarla cuando sea apropiado.

### Documentación OpenRouter (Web Search Plugin)
- **Activación simple**: Añadir `plugins: [{ id: 'web' }]` al payload
- **Alternativa**: Usar sufijo `:online` en el modelo (ej: `google/gemini-3-flash-preview:online`)
- **Respuesta**: Incluye `annotations` con citas de URLs
- **Coste**: ~$0.02 por request con Exa (5 resultados por defecto)
- **Opciones**: `max_results` (default 5), `engine` (native/exa), `search_prompt`

### Diseño UX

**1. Botón toggle en el footer del chat**
- Ubicación: Junto al botón de adjuntar archivo y nanobanana
- Icono: `iconoir-globe` o `iconoir-search`
- Estado inactivo: Color slate (como los demás)
- Estado activo: Color azul/cyan con glow (similar a nanobanana pero azul)
- Tooltip: "Buscar en internet"

**2. Indicador visual activo**
- Botón con borde/glow azul
- Pequeño badge "🌐" o indicador junto al input (opcional)

**3. Comportamiento**
- Compatible con archivos adjuntos (a diferencia de nanobanana)
- Compatible con cualquier modelo
- NO compatible con modo imagen (nanobanana) - deshabilitar uno si se activa el otro

### Tareas de implementación

1. [x] **Backend: Modificar OpenRouterClient.php**
   - Añadido parámetro `$webSearch` a `generateWithMessages()`
   - Si `$webSearch=true`, añade `plugins: [{ id: 'web' }]` al payload
   - Parsea `annotations` de la respuesta y las almacena
   - Añadido getter `getLastAnnotations()`
   - ✅ Completado

2. [x] **Backend: Modificar chat.php**
   - Acepta parámetro `web_search` del frontend
   - Pasa a LlmProvider/ChatService
   - Devuelve `annotations` en la respuesta si existen
   - ✅ Completado

3. [x] **Frontend: Añadir botón toggle web search**
   - Variable `webSearchMode` en JS
   - Botón con estados visual activo/inactivo (cyan cuando activo)
   - Exclusión mutua con `imageMode` (nanobanana)
   - Sincronizado entre vista vacía y footer del chat
   - ✅ Completado

4. [x] **Frontend: Modificar handleSubmit**
   - Si `webSearchMode`: envía `web_search: true` al backend
   - ✅ Completado

5. [x] **Frontend: Renderizar citas web**
   - Si respuesta tiene `annotations`: muestra sección "Fuentes" al final
   - Links clicables a las URLs citadas con dominio visible
   - Deduplicación automática de URLs
   - ✅ Completado

6. [x] **Actualizar contexto de Ebonia**
   - Modificado `docs/context/system_prompt.md`
   - Añadida sección sobre búsqueda web con instrucciones de cuándo sugerirla
   - ✅ Completado

7. [ ] **Testing** (pendiente de usuario)
   - Probar búsqueda web con preguntas de actualidad
   - Verificar citas en respuesta
   - Verificar exclusión mutua con nanobanana

---

## Feature: Soporte Excel/CSV en Chat

### Motivación
Permitir a los usuarios adjuntar archivos Excel (.xlsx, .xls) y CSV al chat para que Gemini los analice. Requiere cambios en frontend, backend y lógica de procesamiento.

### Decisiones técnicas
- **CSV**: Se lee directamente como texto y se envía en el prompt
- **Excel**: Se convierte a CSV/texto usando PhpSpreadsheet antes de enviarlo
- **Gemini 3 Flash**: Procesa datos tabulares en texto perfectamente

### Tareas de implementación

1. [x] **Frontend: Actualizar validación de tipos**
   - Añadido `.csv,.xls,.xlsx` al atributo `accept` de inputs
   - Añadidos MIMEs al array `validTypes` en JS
   - Actualizados tooltips e iconos
   - ✅ Completado

2. [x] **Backend: Actualizar upload.php**
   - Añadidos MIMEs de Excel/CSV a `$allowedTypes`
   - ✅ Completado

3. [x] **Backend: Actualizar chat.php**
   - Añadidos MIMEs de Excel/CSV a `$allowedTypes`
   - ✅ Completado

4. [x] **Backend: Crear helper de conversión**
   - Creado `src/Utils/SpreadsheetReader.php`
   - Lee CSV con autodetección de delimitador
   - Lee XLSX con parser nativo (sin dependencias)
   - Fallback a PhpSpreadsheet si disponible para XLS
   - Formatea como tabla Markdown
   - ✅ Completado

5. [x] **Backend: Integrar conversión en chat.php**
   - Si archivo es Excel/CSV, convierte a texto Markdown
   - Añade contenido tabular al mensaje del usuario
   - ✅ Completado

6. [x] **Optimización con PhpSpreadsheet**
   - Aumentados límites: 1000 filas, 100 columnas, 5 hojas
   - Cálculo automático de fórmulas (=SUMA(), etc.)
   - Formateo de fechas (Y-m-d H:i:s)
   - Formateo de números con precisión (10 decimales)
   - Procesamiento de múltiples hojas con separadores
   - Detección automática de cabeceras
   - Resumen de dimensiones al final
   - ✅ Completado

7. [x] **FIX: Autoloader de Composer**
   - Añadido require del vendor/autoload.php en bootstrap.php
   - Esto permite que PhpSpreadsheet se cargue correctamente
   - ✅ Completado

8. [ ] **Testing** (pendiente de usuario)
   - Subir carpeta vendor al servidor vía FTP
   - Probar con CSV simple
   - Probar con Excel básico (.xlsx)
   - Probar con Excel antiguo (.xls)
   - Probar con Excel con múltiples hojas
   - Probar con fórmulas en celdas
   - Verificar respuestas de Gemini

---

## Feature: Rediseño UX Editor de Imágenes

### Motivación
La UX actual del editor de imágenes tiene problemas:
1. **Scroll excesivo**: Hay que desplazar para ver la imagen generada
2. **Sin edición iterativa**: No se pueden solicitar ediciones a la imagen recibida
3. **Controles dispersos**: Formulario largo que dificulta el flujo de trabajo

### Propuesta de diseño (layout 3 columnas)

```
┌─────────────────────────────────────────────────────────────────┐
│                    HEADER (título + botones)                    │
├──────────┬────────────────────────────────┬─────────────────────┤
│          │       CONTROLES SUPERIORES     │                     │
│          │   (prompt + modo + provider)   │                     │
│ HISTORIAL│────────────────────────────────│   CONTROLES DERECHA │
│          │                                │    (estilo, color,  │
│          │       IMAGEN CENTRAL           │     luz, composic.) │
│          │      (max-height, centrada)    │                     │
│          │────────────────────────────────│                     │
│          │     CONTROLES INFERIORES       │                     │
│          │ (acciones: regenerar, editar)  │                     │
└──────────┴────────────────────────────────┴─────────────────────┘
```

### Características clave

1. **Imagen siempre visible**: Centro de la interfaz, sin scroll
2. **Edición iterativa**: Campo de prompt debajo de la imagen para pedir cambios
3. **Controles compactos**: Selectores en columna derecha (colapsables)
4. **Historial accesible**: Columna izquierda fija
5. **Flujo natural**: Generar → Ver → Editar → Regenerar

### UX de edición de imagen generada
- Al generar imagen, se muestra botón "Editar esta imagen"
- Al hacer clic, la imagen generada se convierte en imagen fuente
- El modo cambia automáticamente a "Editar"
- El usuario escribe los cambios deseados y regenera

### Archivos a crear/modificar
- **NUEVO**: `/public/gestos/editor-imagenes-v2.php` - Nueva UI
- **NUEVO**: `/public/assets/js/gesture-image-editor-v2.js` - Lógica nueva
- **RENOMBRAR**: `editor-imagenes.php` → `editor-imagenes-old.php`
- **RENOMBRAR**: `gesture-image-editor.js` → `gesture-image-editor-old.js`

### Tareas de implementación

1. [x] **Crear estructura HTML del nuevo layout**
   - Layout 3 columnas: historial | main | controles
   - Imagen centrada con aspect-ratio preservado
   - Header con título y acciones principales
   - ✅ Completado: `editor-imagenes.php`

2. [x] **Implementar panel de controles derecho**
   - Acordeones para: Formato, Estilo, Color, Luz, Composición
   - Compacto y colapsable
   - ✅ Completado: Panel lateral con 5 acordeones

3. [x] **Implementar zona central con imagen**
   - Placeholder cuando no hay imagen
   - Imagen generada con lightbox
   - Acciones flotantes sobre imagen (editar, regenerar, descargar, fullscreen)
   - ✅ Completado: Imagen centrada sin scroll

4. [x] **Implementar flujo de edición iterativa**
   - Botón "Editar esta imagen" sobre imagen generada
   - Auto-switch a modo edición
   - Imagen generada → imagen fuente
   - ✅ Completado: `editThisImageBtn` implementado

5. [x] **Migrar lógica JS**
   - Reutilizar prompts y llamadas API
   - Añadir lógica de edición iterativa
   - ✅ Completado: `gesture-image-editor.js`

6. [ ] **Testing y ajustes**
   - Responsive (móvil: layout vertical)
   - Verificar flujo completo
   - Pendiente: Usuario debe probar

---

## Feature: Podcast en Background (generación asíncrona)

### Motivación
Actualmente el gesto "Podcast desde artículo" bloquea completamente al usuario durante la generación (1-3 minutos). El objetivo es que el usuario pueda:
1. Iniciar la generación del podcast
2. Navegar por otras secciones de Ebonia
3. Recibir notificación cuando el podcast esté listo
4. Volver a la página del podcast para ver/escuchar el resultado

### Análisis del flujo actual

```
Frontend (gesture-podcast.js)
    │
    ▼ POST /api/gestures/podcast.php (blocking fetch)
    │
    ├─ Paso 1: Extraer contenido (2-5s)
    ├─ Paso 2: Generar guion con LLM (10-30s)
    ├─ Paso 3: Generar audio TTS (30s-2min)
    └─ Paso 4: Guardar en BD + devolver resultado
    │
    ▼ Usuario ve resultado (bloqueado todo este tiempo)
```

### Opciones de implementación

#### Opción A: Jobs en BD con polling desde frontend
**Complejidad**: Media
**Requiere**: Nueva tabla `jobs`, script de procesamiento

```
1. Frontend hace POST → backend crea job en BD con status='pending', devuelve job_id
2. Backend TERMINA inmediatamente (no bloquea)
3. Un cron/worker procesa jobs pendientes en background
4. Frontend hace polling cada 5s: GET /api/jobs/status.php?id=X
5. Cuando status='completed', frontend muestra resultado
```

**Pros**:
- Usuario libre de navegar
- Funciona sin WebSockets
- Fácil de implementar

**Contras**:
- Requiere cron o proceso background
- Polling consume recursos (mitigable con intervalos largos)

#### Opción B: Ejecutar PHP en background (proc_open/exec)
**Complejidad**: Baja
**Requiere**: Permisos de ejecución

```
1. Frontend hace POST → backend lanza proceso PHP secundario con exec()
2. Backend devuelve job_id inmediatamente
3. Proceso PHP secundario genera podcast y actualiza BD
4. Frontend hace polling o recarga página
```

**Pros**:
- No requiere cron externo
- Simple de implementar

**Contras**:
- Menos control sobre errores
- Puede no funcionar en todos los hostings
- Difícil de debuggear

#### Opción C: WebSockets con progreso en tiempo real
**Complejidad**: Alta
**Requiere**: Servidor WebSocket (Ratchet, Swoole)

**Pros**:
- UX más fluida con progreso real
- Sin polling

**Contras**:
- Requiere servidor WebSocket adicional
- Mucho más complejo
- Overkill para el caso de uso

### Recomendación: Opción A (Jobs en BD + Polling)

Es el balance ideal entre complejidad y funcionalidad:
- No requiere infraestructura adicional
- El polling puede ser inteligente (más frecuente al principio, menos después)
- El usuario puede navegar libremente
- Fácil añadir notificaciones toast cuando el job termine

### Diseño técnico propuesto

**Nueva tabla `background_jobs`**:
```sql
CREATE TABLE background_jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  job_type VARCHAR(50) NOT NULL,
  status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
  input_data JSON,
  output_data JSON,
  error_message TEXT,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  started_at DATETIME,
  completed_at DATETIME,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_status (status),
  INDEX idx_user_status (user_id, status)
);
```

**Endpoints**:
- `POST /api/jobs/create.php` - Crea job, devuelve job_id
- `GET /api/jobs/status.php?id=X` - Devuelve status y resultado si completed
- `POST /api/jobs/process.php` - Llamado por cron, procesa 1 job pendiente

**Procesamiento**:
- Cron cada minuto: `php /path/to/api/jobs/process.php`
- O alternativamente: llamar desde el frontend después de crear el job (self-triggering)

**UX Frontend**:
1. Usuario pulsa "Generar Podcast"
2. Muestra toast "Podcast en cola. Puedes seguir navegando."
3. Indicador persistente en header/sidebar mostrando jobs activos
4. Al completar: notificación toast "¡Tu podcast está listo!"
5. Click en notificación → ir a la página del podcast

### Tareas de implementación

1. [ ] **Crear tabla `background_jobs`**
   - Migración SQL
   - Success: Tabla creada

2. [ ] **Crear `BackgroundJobsRepo.php`**
   - create(), findById(), updateStatus(), getPending()
   - Success: CRUD funcional

3. [ ] **Crear `POST /api/jobs/create.php`**
   - Recibe tipo de job + input_data
   - Crea registro en BD
   - Devuelve job_id
   - Success: Job creado correctamente

4. [ ] **Crear `GET /api/jobs/status.php`**
   - Devuelve status, progress_text, output_data si completed
   - Success: Polling funcional

5. [ ] **Crear `POST /api/jobs/process.php`**
   - Busca job pending más antiguo
   - Lo marca como processing
   - Ejecuta lógica según job_type
   - Marca como completed/failed
   - Success: Jobs se procesan correctamente

6. [ ] **Modificar `gesture-podcast.js`**
   - Crear job en lugar de llamar directamente
   - Iniciar polling
   - Mostrar progreso
   - Success: Podcast se genera sin bloquear

7. [ ] **Añadir indicador de jobs activos en UI**
   - Badge en header o sidebar
   - Notificación toast al completar
   - Success: Usuario informado del progreso

8. [ ] **Configurar cron (producción)**
   - `* * * * * php /var/www/ebonia/public/api/jobs/process.php`
   - O usar trigger desde frontend
   - Success: Jobs se procesan automáticamente

# Executor's Feedback or Assistance Requests

- Proveedor LLM: Gemini 1.5 Flash confirmado. API Key recibida (se gestionará vía `.env`, no se registrará en repo ni logs).
- **URGENTE - RBAC no funcional**: Las tablas `user_roles` y `role_permissions` están vacías. El sistema de permisos no funciona. Script de corrección creado en `docs/migrations/004_fix_rbac.sql`. Aplicar para activar el RBAC.
- **Limpieza de migraciones**: Eliminar duplicado de tabla `voices` en `001_init.sql` (líneas 198-225). Eliminar tabla `schema_migrations` si no se usa.
- **FOLDERS IMPLEMENTADOS**: Sistema completo de carpetas privadas por usuario funcionando. Falta aplicar `004_fix_rbac.sql` y probar todo end-to-end.
- **EJECUTOR (2026-04-08) - Tarea implementada**: Drag & drop + paste multimedia en chat general completado.
  - Archivo modificado: `public/index.php`
  - Validación técnica: `php -l public/index.php` ✅
  - Pendiente validación manual del usuario:
    1. Arrastrar PNG/PDF a estado vacío → debe aparecer en preview de adjuntos.
    2. Arrastrar PNG/PDF en chat activo → debe aparecer en preview de adjuntos.
    3. Pegar captura de pantalla (Cmd/Ctrl+V) en ambos textareas → debe adjuntarse archivo.
    4. Pegar texto normal → debe seguir pegando texto (sin adjuntar archivos).
    5. Con `imageMode` activo, intentar adjuntar/arrastrar/pegar archivo → debe mostrarse aviso explicando por qué no se puede.
- **EJECUTOR (2026-04-08) - Ajuste UX conversación**: overlay de drag & drop cambiado a `fixed` para que siempre se vea completo aunque haya scroll en conversación. Añadida ayuda visual bajo ambos composers con tipos soportados y límite: `PDF, PNG, JPG, GIF, WEBP, CSV, XLS, XLSX (30MB)`.

## Feature: Transformador de Contenido (Content Repurposer)

### Motivación
Gesto que transforma contenido de cualquier fuente (URL, texto, PDF) en múltiples formatos de salida para diferentes canales y propósitos.

### Formatos de salida
- **Redes sociales**: Instagram, Facebook, LinkedIn, X (Twitter)
- **Contenido largo**: Blog (SEO), Landing page (HTML/CSS/JS)
- **Comunicación**: Newsletter (email)
- **Soporte**: FAQs

### Arquitectura modular
- **ContentExtractor** (reutilizado de Audio/): Extrae contenido de URL, texto o PDF
- **ContentRepurposer** (nuevo en Content/): Generador con prompts especializados por formato
- **API**: `/api/gestures/repurposer.php`
- **UI**: `/gestos/transformador-contenido.php`
- **JS**: `/assets/js/gesture-repurposer.js`

### Tareas completadas
1. [x] Crear `src/Content/ContentRepurposer.php` - Generador modular con prompts por formato
2. [x] Crear API `/public/api/gestures/repurposer.php`
3. [x] Crear UI `/public/gestos/transformador-contenido.php`
4. [x] Crear JS `/public/assets/js/gesture-repurposer.js`
5. [x] Actualizar `/public/gestos/index.php` con tarjeta
6. [x] Actualizar `/public/includes/left-tabs.php` con entrada en menú
7. [x] Crear migración `009_add_content_repurposer_gesture.sql`

### Pendiente para activar
- Ejecutar migración: `mysql -u usuario -p base_datos < docs/migrations/009_add_content_repurposer_gesture.sql`
- O dar acceso manualmente desde panel admin

---

## Feature: SOP Generator (Generador de Procesos)

### Background y Motivación

Nuevo gesto para generar **SOPs (Standard Operating Procedures)** - procedimientos operativos estándar - a partir de contenido desestructurado. El objetivo es transformar información caótica (grabaciones de reuniones, notas sueltas, capturas de pantalla de procesos) en documentación estructurada y profesional de procesos.

### Entradas soportadas

| Tipo | Estado actual | Implementación |
|------|---------------|----------------|
| **Texto sin estructurar** | ✅ Existe | Reutilizar `ContentExtractor::extractFromText()` |
| **URL** | ✅ Existe | Reutilizar `ContentExtractor::extractFromUrl()` |
| **PDF** | ✅ Existe | Reutilizar `ContentExtractor::extractFromPdf()` |
| **Audio** | ❌ No existe | **NUEVO**: Transcripción con Whisper/Gemini |
| **Imágenes** | ❌ Parcial | **NUEVO**: Descripción con visión multimodal |

### Formatos de salida propuestos

| Formato | Descripción | Dificultad | Valor |
|---------|-------------|------------|-------|
| **Markdown estructurado** | Secciones, pasos numerados, checklists | Baja | Alto |
| **JSON estructurado** | Para integraciones/APIs, importar a otros sistemas | Baja | Medio |
| **Checklist interactivo** | HTML con checkboxes para seguir el proceso | Media | Alto |
| **Diagrama de flujo (Mermaid)** | Visualización del proceso como flowchart | Media | Alto |
| **PDF descargable** | Documento formal para imprimir/compartir | Alta | Alto |
| **DOCX** | Para edición en Word | Alta | Medio |
| **Notion/Confluence** | Export directo a herramientas de documentación | Alta | Medio |

### Recomendación de formatos MVP

1. **Markdown estructurado** - Salida por defecto, fácil de copiar/editar
2. **Checklist interactivo** - Valor inmediato para seguimiento de procesos
3. **Diagrama Mermaid** - Visualización clara del flujo

Los demás formatos (PDF, DOCX, integraciones) pueden añadirse en iteraciones posteriores.

### Arquitectura propuesta (modular)

```
┌─────────────────────────────────────────────────────────────────┐
│                      SOP Generator                               │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌──────────────────┐                                           │
│  │ ContentExtractor │ ← Reutilizado de Audio/ (texto, URL, PDF) │
│  └────────┬─────────┘                                           │
│           │                                                      │
│  ┌────────▼─────────┐                                           │
│  │ AudioTranscriber │ ← NUEVO: Whisper API o Gemini multimodal  │
│  └────────┬─────────┘                                           │
│           │                                                      │
│  ┌────────▼─────────┐                                           │
│  │ ImageDescriber   │ ← NUEVO: Gemini Vision para screenshots   │
│  └────────┬─────────┘                                           │
│           │                                                      │
│           ▼                                                      │
│  ┌──────────────────┐                                           │
│  │  SopGenerator    │ ← NUEVO: Orquestador + prompts por formato│
│  │                  │   - generateMarkdown()                    │
│  │                  │   - generateChecklist()                   │
│  │                  │   - generateMermaid()                     │
│  │                  │   - generateJson()                        │
│  └────────┬─────────┘                                           │
│           │                                                      │
│           ▼                                                      │
│  ┌──────────────────┐                                           │
│  │ OpenRouterClient │ ← Reutilizado                             │
│  └──────────────────┘                                           │
└─────────────────────────────────────────────────────────────────┘
```

### Nuevos archivos a crear

**Backend** (`src/`):
- `Sop/SopGenerator.php` - Orquestador principal con prompts especializados
- `Sop/AudioTranscriber.php` - Transcripción de audio (Whisper/Gemini)
- `Sop/ImageDescriber.php` - Descripción de imágenes para contexto

**API**:
- `public/api/gestures/sop.php` - Endpoint principal

**Frontend**:
- `public/gestos/sop-generator.php` - Página del gesto
- `public/assets/js/gesture-sop.js` - Lógica JS

### Código reutilizable

| Componente | Ubicación | Reutilización |
|------------|-----------|---------------|
| `ContentExtractor` | `src/Audio/` | Mover a `src/Content/` o usar directamente |
| `OpenRouterClient` | `src/Chat/` | Multimodal ya soportado (imágenes, PDF) |
| `GestureExecutionsRepo` | `src/Gestures/` | Historial de ejecuciones |
| `BackgroundJobsRepo` | `src/Jobs/` | Si procesamiento es largo |
| Layout gestos | `public/gestos/*.php` | Copiar estructura de transformador-contenido |

### Opciones para transcripción de audio

| Opción | Pros | Contras |
|--------|------|---------|
| **OpenAI Whisper API** | Muy preciso, multiidioma | Requiere API key adicional |
| **Gemini multimodal** | Ya tenemos OpenRouter | Menos preciso que Whisper |
| **Whisper local** | Sin coste API | Requiere instalación, RAM |
| **OpenRouter + audio** | Unificado | Depende del modelo |

**Recomendación**: Empezar con **Gemini multimodal via OpenRouter** (ya configurado), con opción futura de Whisper para mayor precisión.

### Decisiones pendientes (para usuario)

1. **¿Qué formatos de salida priorizar?** (Markdown, Checklist, Mermaid, JSON, PDF...)
2. **¿Whisper API o Gemini para audio?** (precisión vs simplicidad)
3. **¿Procesamiento en background?** (para archivos grandes de audio)
4. **¿Plantillas de SOP predefinidas?** (IT, RRHH, Operaciones...)

### High-level Task Breakdown

1. [x] **Crear DocumentGenerator en src/Utils/** (reutilizable para chat)
   - Generador de PDF con Dompdf
   - Generador de DOCX con PhpWord
   - Success: ✅ `src/Utils/DocumentGenerator.php` creado

2. [x] **Crear AudioTranscriber**
   - Soporte para mp3, wav, m4a, webm, ogg
   - Usa Gemini multimodal (`google/gemini-3-flash-preview`) via OpenRouter
   - Success: ✅ `src/Sop/AudioTranscriber.php` creado

3. [x] **Crear ImageDescriber**
   - Analiza capturas de pantalla/diagramas
   - Extrae texto, estructura y contexto para SOPs
   - Success: ✅ `src/Sop/ImageDescriber.php` creado

4. [x] **Crear SopGenerator**
   - Orquestador principal
   - Prompts especializados para Markdown y Mermaid
   - Integra ContentExtractor + AudioTranscriber + ImageDescriber
   - Success: ✅ `src/Sop/SopGenerator.php` creado

5. [x] **Crear API /api/gestures/sop.php**
   - Manejo de múltiples tipos de entrada
   - Genera todos los formatos (Markdown, Mermaid, PDF, DOCX)
   - Success: ✅ Endpoint funcional

6. [x] **Crear UI sop-generator.php**
   - Tarjetas de fuentes (texto, URL, audio, imágenes)
   - Preview de Markdown renderizado y diagrama Mermaid
   - Botones de descarga PDF/DOCX
   - Success: ✅ Interfaz completa

7. [x] **Crear gesture-sop.js**
   - Gestión de uploads multimodal
   - Renderizado con marked.js y mermaid.js
   - Historial funcional
   - Success: ✅ JS completo

8. [x] **Añadir a registros de gestos**
   - Migración SQL: `docs/migrations/010_add_sop_generator_gesture.sql`
   - Entrada en `left-tabs.php`
   - Tarjeta en `/gestos/index.php`
   - Success: ✅ Todo registrado

9. [ ] **Testing manual** (pendiente de usuario)
   - Ejecutar migración SQL
   - Probar con texto, audio e imágenes
   - Verificar generación de PDF/DOCX
   - Success: SOPs de calidad profesional

### Archivos creados

**Backend:**
- `src/Utils/DocumentGenerator.php` - Generador PDF/DOCX reutilizable
- `src/Sop/AudioTranscriber.php` - Transcripción con Gemini
- `src/Sop/ImageDescriber.php` - Descripción de imágenes
- `src/Sop/SopGenerator.php` - Orquestador principal
- `public/api/gestures/sop.php` - Endpoint API
- `public/api/files/document.php` - Servidor de documentos

**Frontend:**
- `public/gestos/sop-generator.php` - Página del gesto
- `public/assets/js/gesture-sop.js` - Lógica JS

**Config:**
- `docs/migrations/010_add_sop_generator_gesture.sql` - Migración
- `storage/documents/` - Directorio para documentos generados
- `composer.json` - Añadido phpoffice/phpword y dompdf/dompdf

### Dependencias instaladas
- `phpoffice/phpword: ^1.3` - Generación DOCX
- `dompdf/dompdf: ^3.0` - Generación PDF

---

## Feature: Gesto Transcriptor de Audio

### Motivación
Gesto dedicado para convertir archivos de audio en texto. Reutiliza `AudioTranscriber` existente (usado en SOP Generator) pero con UI especializada y más simple.

### Archivos creados

**Backend:**
- `public/api/gestures/transcribe.php` - Endpoint API que usa `AudioTranscriber`

**Frontend:**
- `public/gestos/transcriptor-audio.php` - Página del gesto con drag & drop, reproductor de audio, historial

**Config:**
- `docs/migrations/011_add_audio_transcriber_gesture.sql` - Migración para registrar el gesto

**Modificados:**
- `public/gestos/index.php` - Añadida tarjeta del gesto + fix de variables $userId/$accessRepo

### Características
- Soporta: MP3, WAV, M4A, WebM, OGG (hasta 25MB)
- Usa Gemini 3 Flash via OpenRouter para transcripción
- UI con drag & drop y reproductor de audio
- Historial de transcripciones guardado
- Botones copiar / descargar TXT
- Iconografía: `iconoir-microphone` con gradiente morado/índigo

### Tareas
- [x] Crear endpoint `/api/gestures/transcribe.php`
- [x] Crear página `/gestos/transcriptor-audio.php`
- [x] Crear migración SQL
- [x] Añadir tarjeta en `/gestos/index.php`
- [ ] Ejecutar migración en producción
- [ ] Testing manual

---

## Feature: Gesto "Creador de Cursos" 🚧 En desarrollo

### Motivación
Gesto para generar material formativo completo a partir de PDFs o texto. Pipeline de 2 fases:
1. **Fase 1**: Generar índice pedagógico editable
2. **Fase 2**: Desarrollar módulos completos desde el índice

### Flujo de usuario (Opción B)
```
[Sube PDF] → [Genera índice] → [Usuario edita índice] → [Desarrollar módulos] → [Descargar Word/PDF por módulo]
```

### Arquitectura técnica

#### Fase 1: Generación de índice
- Analiza documento fuente completo
- Propone índice pedagógico adaptado (diferente al original)
- Output: JSON editable con módulos, lecciones, objetivos
- Usuario puede editar/reordenar antes de continuar

#### Fase 2: Desarrollo de módulos
- Recibe índice (editado o no) + contenido original
- Genera contenido DESARROLLADO por módulo (secuencial)
- Cada módulo: introducción, desarrollo, ejemplos, resumen
- Progreso visible: "Desarrollando módulo 2 de 5..."
- Output: Markdown por módulo → Word/PDF descargable

### Endpoints API
- `POST /api/gestures/course-creator.php` - Fase 1: genera índice
- `POST /api/gestures/course-develop.php` - Fase 2: desarrolla módulos

### Archivos
- `src/Content/CourseGenerator.php` - Servicio con generateOutline() y developModules()
- `public/api/gestures/course-creator.php` - Endpoint fase 1
- `public/api/gestures/course-develop.php` - Endpoint fase 2 (nuevo)
- `public/gestos/creador-cursos.php` - Página del gesto
- `public/assets/js/gesture-course-creator.js` - JS con flujo 2 pasos

### Tareas
- [x] Backend: Servicio CourseGenerator base
- [x] Navegación y permisos
- [ ] Refactorizar Fase 1: generateOutline() con JSON editable
- [ ] Implementar Fase 2: developModules() secuencial
- [ ] API: endpoint course-develop.php
- [ ] Frontend: editor de índice + progreso por módulo
- [ ] Integrar DocumentGenerator para exports
- [ ] Testing

---

# Lessons

- Mantener comandos idempotentes para poder re-ejecutar sin fallos (p.ej. `git remote set-url` si `origin` ya existe).
- Documentar primero: BD y contratos de API, para evitar divergencias futuras.
- **Folders privadas por usuario**: Implementado sistema completo de carpetas jerárquicas con parent_id. Prevención de ciclos en FoldersRepo::move(). Carpetas se eliminan en cascada pero conversaciones quedan sin carpeta (ON DELETE SET NULL). UI incluye filtrado por carpeta "Todas", "Sin carpeta" y carpetas personalizadas.
- **Seguridad**: Siempre verificar autenticación en PHP ANTES de renderizar HTML. La verificación solo en JavaScript es insegura porque el HTML se envía al navegador antes de ejecutarse el script, permitiendo que usuarios no autenticados vean contenido protegido brevemente. Patrón correcto:
  ```php
  Session::start();
  $user = Session::user();
  if (!$user) {
      header('Location: /login.php');
      exit;
  }
  ```
- **Bug: Conversaciones desaparecen (emptyConversationId race condition)**: Cuando un usuario crea una conversación nueva con el botón "Nueva conversación", `emptyConversationId` se setea al ID de esa conversación. Al enviar un mensaje, el servidor NO envía evento `conversation` de vuelta (porque `conversation_id > 0`), así que `emptyConversationId` nunca se limpia. Al cambiar a otra conversación, `cleanupEmptyConversation()` borra la conversación que YA tiene mensajes. **Fix**: Limpiar `emptyConversationId` al inicio de `handleSubmit` cuando `emptyConversationId === currentConversationId`, porque la conversación deja de estar vacía en cuanto se envía un mensaje.
- **Contexto corporativo desacoplado de proveedores**: El conocimiento base (docs/context/*.md) se mantiene independiente del LLM usado. ContextBuilder lo compila una vez y cada proveedor lo inyecta en su formato nativo (systemInstruction para Gemini, mensaje 'system' para OpenAI). Esto permite cambiar de proveedor sin perder el contexto corporativo.
- **System instructions > mensajes de contexto**: Usar systemInstruction (Gemini) o rol 'system' (OpenAI) es más eficiente que insertar el contexto como mensajes normales, porque no cuenta contra el límite de tokens de historial y tiene mayor peso en las respuestas del modelo.
- **OpenRouter rechaza `content: []` con HTTP 400**: En OpenRouterClient, cuando un mensaje del historial tiene contenido vacío (ej: respuesta solo-imagen de nanobanana, contenido NULL en BD), `$content` queda como array vacío `[]`. OpenRouter/OpenAI API lo rechaza con 400. Solución: omitir mensajes sin contenido real del payload. También en streaming, capturar `rawErrorBody` para mostrar el error exacto de OpenRouter en lugar de un genérico "Error HTTP 400".

---

## 🔒 AUDITORÍA DE SEGURIDAD — Ebonia (Feb 2026)

### Resumen ejecutivo

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
