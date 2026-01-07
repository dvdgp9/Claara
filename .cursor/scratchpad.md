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

7. [ ] **Testing** (pendiente de usuario)
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
- **Contexto corporativo desacoplado de proveedores**: El conocimiento base (docs/context/*.md) se mantiene independiente del LLM usado. ContextBuilder lo compila una vez y cada proveedor lo inyecta en su formato nativo (systemInstruction para Gemini, mensaje 'system' para OpenAI). Esto permite cambiar de proveedor sin perder el contexto corporativo.
- **System instructions > mensajes de contexto**: Usar systemInstruction (Gemini) o rol 'system' (OpenAI) es más eficiente que insertar el contexto como mensajes normales, porque no cuenta contra el límite de tokens de historial y tiene mayor peso en las respuestas del modelo.
