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

- [ ] Planner: mejorar el módulo de organización integrando usuarios/departamentos/voces con puesto de usuario, responsables many-to-many y visibilidad de accesos/responsabilidades.
- [x] Claara integration step 1: internal per-user capability catalog for accessible voices and gestures.
- [x] Claara integration step 2: inject capability catalog into general chat context for recommendations only, without automatic execution.
- [x] Claara integration follow-up: move gesture routing metadata into `available_features` so the chat capability catalog is database-driven.
- [x] Claara chat UX: render recommended voice/gesture routes as integrated action buttons in assistant messages.
- [x] Claara chat voice execution: voice recommendation buttons can run the voice RAG query inside the current conversation.
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

---

## Feature: Organization Management integrado (users, departments, voice responsibility)

### Background and Motivation
Claara ya tiene usuarios, departamentos, permisos por feature y voces RAG dinámicas. El siguiente paso es convertir esos datos en una capa de organización útil para admins y para el propio chat general: saber qué puesto tiene cada persona, qué departamentos coordina, qué voces puede usar y de qué voces es responsable. El usuario corrigió el modelo inicial de “un responsable por departamento”: la solución correcta debe ser many-to-many, porque una persona puede responsabilizarse de varios departamentos y un departamento puede tener varios responsables.

### Key Challenges and Analysis
- **Responsibility is not the same as access.** Un usuario puede tener acceso a una voz sin ser responsable de ella. Si alguien se marca como responsable de una voz, conviene garantizarle acceso automáticamente, pero al quitarle la responsabilidad no deberíamos quitar el acceso sin confirmación porque podría tenerlo por otro motivo.
- **Many-to-many desde el principio.** Evita bloqueos futuros y encaja mejor con equipos B2B reales. Tablas propuestas: `department_responsibles` y `voice_responsibles`.
- **User position as profile data.** Añadir `users.job_title` como campo simple y visible en listados/detalle. No crear una tabla de puestos hasta que haya necesidad real de reporting estructurado.
- **Integrated admin UX.** No conviene mantener “Users” y “Departments” como pantallas aisladas. La experiencia debería sentirse como un módulo “Organization” con tabs compactas, detalle lateral y edición contextual.
- **Voice ownership belongs in Voice Studio too.** Los responsables de voces deben poder verse/asignarse desde el editor de voces, pero también verse desde el perfil de usuario.
- **Claara chat context.** Más adelante, el catálogo de capacidades debe poder incluir `job_title`, departamento, responsabilidades de departamento y responsabilidades de voces para recomendaciones más precisas.
- **Diseño:** UI de herramienta B2B, en inglés, densa pero legible. Evitar cards grandes por fila; usar tablas compactas, chips, drawers y estados inline. CSS nuevo en `public/assets/css/styles.css`, no inline.

### Proposed Data Model
- `users.job_title VARCHAR(120) NULL`
- `department_responsibles`
  - `department_id BIGINT UNSIGNED NOT NULL`
  - `user_id BIGINT UNSIGNED NOT NULL`
  - `created_at TIMESTAMP`
  - unique key `(department_id, user_id)`
- `voice_responsibles`
  - `voice_slug VARCHAR(80) NOT NULL`
  - `user_id BIGINT UNSIGNED NOT NULL`
  - `created_at TIMESTAMP`
  - unique key `(voice_slug, user_id)`

> Nota: usar `voice_slug` es pragmático porque Claara ya usa slugs en rutas, permisos (`user_feature_access`) y catálogo. Si antes de implementar vemos que la tabla `voices` tiene garantías fuertes de `id` en producción, se puede usar `voice_id`, pero el contrato frontend/API debería seguir hablando en `slug`.

### UX Proposal
- Convertir el admin actual en un módulo **Organization** con dos tabs principales: **Users** y **Departments**.
- **Users tab:** tabla compacta con `Name`, `Job title`, `Department`, `Responsibilities`, `Voice access`, `Status`, acciones. Al abrir un usuario, drawer lateral con:
  - Profile: nombre, email, job title, status, superadmin.
  - Department: departamento actual.
  - Responsibilities: departamentos donde es responsable y voces donde es responsable.
  - Voice access: voces accesibles, diferenciando “access” vs “responsible”.
- **Departments tab:** tabla compacta con `Department`, `Members`, `Responsible users`, acciones. Al abrir un departamento, drawer con miembros y selector de responsables.
- **Voice Studio:** añadir selector de **Responsible users** en el editor de voz. Mostrar chips compactos y nota funcional: “Responsible users keep access to this voice.”
- Mantener todo el copy en inglés: `Job title`, `Responsible for`, `Voice access`, `Responsible users`, `No responsible users yet`, `Add responsible user`.

### High-level Task Breakdown

1. [ ] **Inspect current schema and create migration plan**
   - Revisar columnas reales de `users`, `departments`, `voices`, `user_feature_access` en local/producción antes de tocar BD.
   - Crear migración SQL versionada con `users.job_title`, `department_responsibles`, `voice_responsibles`, índices y tipos compatibles con `users.id`.
   - Success: migración es idempotente o segura de ejecutar una vez; no rompe usuarios/departamentos/voces existentes.

2. [ ] **Repository layer**
   - Extender `UsersRepo` para leer/escribir `job_title`.
   - Extender `DepartmentsRepo` o crear repo específico para responsables.
   - Crear métodos `setDepartmentResponsibles`, `getDepartmentResponsibles`, `setVoiceResponsibles`, `getVoiceResponsibles`, `getUserResponsibilitySummary`.
   - Success: repositorios devuelven datos normalizados sin lógica SQL duplicada en endpoints.

3. [ ] **Admin APIs**
   - Actualizar create/update/list de usuarios para incluir `job_title`, responsabilidades y voces accesibles.
   - Actualizar list/update de departamentos para incluir responsables.
   - Añadir o ampliar APIs de voces para guardar/listar responsables.
   - Al guardar responsables de voz, asegurar acceso en `user_feature_access` para esas voces sin revocar accesos al quitar responsabilidad.
   - Revisar bug existente en `public/api/admin/users/update.php`: usa `$currentUser['id']` sin definir.
   - Success: CRUD completo vía API, con errores claros y validación de permisos admin.

4. [ ] **Organization UI**
   - Reorganizar `public/admin/users.php` como hub de Organization con tabs `Users` y `Departments`, o crear `public/admin/organization.php` y dejar enlaces antiguos apuntando ahí.
   - Implementar tabla compacta, filtros, chips de responsabilidad/acceso y drawer de edición.
   - Reutilizar endpoints existentes cuando sea posible.
   - Success: un admin puede editar puesto, departamento, responsables y accesos sin saltar entre tres pantallas desconectadas.

5. [ ] **Voice Studio integration**
   - Añadir campo `Responsible users` en `public/admin/voices.php` y `public/assets/js/admin-voices.js`.
   - Mostrar responsables en el listado compacto de voces si cabe sin ruido.
   - Success: al crear/editar una voz se pueden asignar responsables y estos aparecen también en el perfil del usuario.

6. [ ] **User-facing visibility**
   - En el panel/perfil de usuario, mostrar `Job title`, departamento, si es responsable de departamentos y voces accesibles.
   - Success: un usuario entiende qué rol ocupa en Claara y a qué voces tiene acceso.

7. [ ] **Claara context integration**
   - Extender el contexto interno/catálogo de capacidades con job title, departamento, responsabilidades y voces accesibles/responsables.
   - Success: el chat general puede usar esta información para recomendar voces de forma más contextual, sin exponer datos técnicos al usuario final.

8. [ ] **QA, migration and deploy**
   - `php -l` en PHP tocados, `node --check` en JS tocado.
   - Probar migración en servidor con cuidado, sin `git reset --hard`.
   - Verificar flujos: crear usuario con job title, asignar responsable de departamento, asignar responsable de voz, confirmar acceso automático a voz, revisar panel de usuario.
   - Success: producción lista para pruebas reales sin regresión en permisos ni Voice Studio.

### Project Status Board — Organization Management
- [ ] Planner: validar enfoque de Organization hub + relaciones many-to-many.
- [ ] Executor: inspeccionar schema real y preparar migración.
- [ ] Executor: implementar repositorios y APIs.
- [ ] Executor: implementar UI integrada de usuarios/departamentos.
- [ ] Executor: integrar responsables de voces en Voice Studio.
- [ ] Executor: exponer datos en panel de usuario y contexto de Claara.
- [ ] Executor: QA, push y pull en servidor.

### Current Status / Progress Tracking — Organization Management
- 2026-06-08 (Planner): Plan inicial documentado. Se propone una implementación incremental y pragmática: `job_title` simple en usuarios, responsabilidad many-to-many para departamentos y voces, UX integrada en un módulo Organization y sincronización de acceso cuando alguien es responsable de una voz.
- 2026-06-08 (Executor): Primer corte funcional implementado. Añadida migración `021_organization_responsibilities.sql`; aplicada en producción antes del deploy con `users.job_title`, `department_responsibles` y `voice_responsibles`. Backend actualizado para usuarios, departamentos, voces, sesión/auth-me y catálogo interno de Claara. UI actualizada en User Management, Departments, Voice Studio y My Account para mostrar/editar puesto, responsables, acceso a voces y responsabilidades.
- 2026-06-08 (Executor): Ajuste UX de integración Users/Departments. Ambas rutas admin comparten ahora cabecera `Organization`, tabs `Users`/`Departments` con estado activo claro y copy común. `Departments` deja de verse como entidad aislada: se elimina el aside explicativo y se presenta como una sección del mismo módulo.

---

## Feature: Landing pública en `claara.tech` + acceso a app

### Background and Motivation
Actualmente `claara.tech/` carga directamente el workspace autenticado (`public/index.php`) y redirige a `/login.php` cuando no hay sesión. El usuario propone que `claara.tech` sea una landing pública que represente bien Claara, y que el acceso a la plataforma se haga desde un botón del menú. La dirección es razonable: separa marketing/explicación del producto de la experiencia de uso, mejora la primera impresión para visitantes externos y mantiene la app como espacio autenticado.

### Key Challenges and Analysis
- Arquitectura actual: PHP + Tailwind CDN + JS vanilla. No hay `package.json`, así que no se deben introducir librerías de React/Framer/Phosphor sin instalar y reestructurar el proyecto. Para este cambio conviene usar PHP/HTML/CSS/JS existente.
- Ruta crítica: `/` está ocupado por la app principal. La opción más limpia es mover el workspace a una ruta explícita como `/app/` y dejar `/` para la landing.
- Compatibilidad: muchas rutas internas apuntan a `/` como "Conversations/Home" y `login.php` redirige a `/`. Habrá que actualizar esas referencias a `/app/` para evitar que usuarios autenticados vuelvan a la landing al pulsar inicio.
- Sesión: la landing debe ser pública. Si el usuario ya está logueado, el CTA principal puede apuntar directamente a `/app/`; si no, a `/login.php` o a `/login.php?next=/app/` si se implementa redirect seguro.
- Diseño: aplicar `design-taste-frontend` sin caer en patrones genéricos. Landing asimétrica, left-aligned, con señal visual real de producto en primer viewport. Evitar hero centrado, paleta lila/azul AI, emojis, cards anidadas, inline CSS y glows excesivos.
- Assets: existen logos Claara y símbolo en `public/assets/images/`. No se han identificado capturas reales de producto; se puede construir un mock visual fiel con HTML/CSS o generar/guardar una imagen si el Planner lo aprueba.
- Documentación externa: antes de desarrollar, pedir al usuario que permita/taguee `@web` si hace falta consultar documentación actualizada. Para una landing estática sobre el stack existente no parece necesario usar APIs externas.

### High-level Task Breakdown

1. [ ] Definir routing objetivo y redirecciones.
   - Propuesta: conservar `public/index.php` como landing pública y mover/copiar el workspace actual a `public/app/index.php` o `public/app.php`.
   - Actualizar login post-success de `/` a `/app/`.
   - Success: visitante no logueado ve landing en `/`; usuario logueado puede abrir `/app/` y usar el chat como antes.

2. [ ] Actualizar navegación interna a la nueva ruta de app.
   - Cambiar enlaces "Conversations/Home" de `/` a `/app/` en desktop/mobile nav.
   - Revisar redirecciones JS/PHP que asumen `/` como workspace.
   - Success: desde cualquier pantalla autenticada, volver al chat principal lleva a `/app/`, no a la landing.

3. [ ] Crear landing pública de Claara en `/`.
   - Estructura recomendada:
     - Header sobrio con logo, secciones, CTA "Entrar".
     - Hero asimétrico con Claara como primer foco, copy concreto sobre chat, voces, gestos y contexto corporativo.
     - Visual de producto no genérico: panel/mock de workspace con chat, gestos y Lex.
     - Bloques de capacidades en layout asimétrico, no una fila genérica de 3 tarjetas.
     - Cierre con CTA a login/app.
   - Success: `/` comunica qué es Claara en menos de 5 segundos y el botón principal conduce al acceso.

4. [ ] Añadir estilos en `public/assets/css/styles.css`.
   - Cumplir regla del usuario: CSS en `styles.css`, no inline.
   - Usar `Outfit` ya existente, paleta controlada, `min-h-[100dvh]`, responsive estricto y motion CSS solo con `transform`/`opacity`.
   - Success: no hay CSS nuevo inline; móvil no tiene scroll horizontal ni solapes.

5. [ ] Estados y accesibilidad mínimos.
   - CTA con estado activo/focus visible.
   - Header responsive.
   - Alt text correcto para logos; sin emojis; navegación por teclado razonable.
   - Success: flujo usable en desktop y móvil, con textos legibles y botones claros.

6. [ ] QA manual y técnica.
   - `php -l` de archivos PHP tocados.
   - Revisar con navegador `/`, `/login.php`, `/app/`, y rutas de gestos/voces.
   - Verificar usuario no autenticado: landing pública y `/app/` redirige a login.
   - Verificar usuario autenticado: CTA abre app y login no queda atrapado en bucles.
   - Success: no hay regresiones de acceso ni navegación principal.

### Project Status Board — Landing pública Claara
- [ ] Planner: validar enfoque `/` landing + `/app/` workspace con el usuario.
- [ ] Executor: implementar routing `/app/` sin cambiar comportamiento funcional del chat.
- [ ] Executor: construir landing pública en `/`.
- [ ] Executor: actualizar navegación/login/redirecciones.
- [ ] Executor: QA responsive y flujo auth.

### Current Status / Progress Tracking — Landing pública Claara
- 2026-06-04 (Executor): Primer hito implementado localmente, pendiente de validación manual del usuario. `/` ahora renderiza una landing pública inicial; `/app/` carga el workspace anterior mediante `public/app.php`; login y navegación principal apuntan a `/app/`; redirecciones de admin no autorizado y accesos secundarios se han actualizado para no volver a `/`.
- Verificación técnica realizada: `php -l` OK en archivos PHP tocados; `curl` local confirma `/` = 200 con landing, `/app/` = 302 a `/login.php` sin sesión, `/login.php` = 200. Playwright no está instalado en este entorno, así que queda pendiente QA visual manual en navegador.
- 2026-06-04 (Executor): Landing ampliada tras revisar funcionalidad real de la app. Se añadió posicionamiento B2B para empresas, explicación de chat con archivos/web/PDF-DOCX, voces especializadas (Lex con RAG/citas/source match/conflictos), gestos operativos, conectores, permisos, usuarios/departamentos, admin de contexto, modelos y uso. También se corrigieron `headerBackUrl` restantes hacia `/app/` en páginas internas.
- Verificación técnica adicional: `php -l` OK en `public/index.php`, `public/gestos/index.php`, `public/voices/index.php`, `public/connectors.php`, `public/voices/lex.php` y `public/admin/models.php`; búsqueda sin referencias obvias a `/` como ruta de chat; navegador integrado confirma contenido de landing y `scrollWidth === clientWidth` en móvil (390px) en hero y sección de biblioteca de gestos.

---

## Feature: Voces RAG administrables desde frontend

### Background and Motivation
Las voces deben convertirse en una capacidad B2B central de Claara: cada empresa o área puede necesitar asistentes especializados para RRHH, legal, operaciones, comercial, prevención, IT interno, licitaciones, soporte, etc. El usuario aclara que, en Claara, una voz significa siempre un asistente RAG con base documental propia; otros comportamientos no documentales se resolverán mediante gestos o chat general.

Hoy las voces están parcialmente hardcodeadas en `src/Voices/VoiceContextBuilder.php` (`lex`, `cubo`, `uniges`) y la UI pública de voces solo muestra Lex real + placeholders. El gestor de contexto ya gestiona documentos e indexación RAG, pero está orientado a targets fijos (`lex`, `eboniato`, `ebonia`). Para escalar voces de empresa hay que hacerlas dinámicas, administrables y visibles para el chat general.

### Key Challenges and Analysis
- **Modelo actual hardcodeado:** `VoiceContextBuilder::$voices` impide crear voces desde frontend sin tocar código.
- **RAG como contrato obligatorio:** cada voz debe tener colección Qdrant propia, documentos asociados, estado de indexación y prueba antes de publicarse.
- **Permisos:** ya existe `available_features` + `user_feature_access` para `voice:{slug}`. Hay que reutilizarlo para publicar voces y permitir acceso por usuario/departamento. El acceso a edición de voces debe ser solo para superadmin o usuarios con un permiso explícito tipo `feature:voice-editor`.
- **Gestor documental existente:** `context_documents.target` usa ENUM fijo; conviene migrarlo a un identificador flexible (`voice:{slug}` o `voice_slug`) para no crear migraciones por cada voz.
- **UX admin:** debe ser muy simple: crear voz, añadir conocimiento, procesar índice, probar, publicar. No exponer Qdrant/embeddings como conceptos principales; mostrar estados humanos: “Sin documentos”, “Indexando”, “Lista para probar”, “Publicada”, “Error”.
- **Integración futura con chat general:** toda voz publicada debe tener un manifiesto/catálogo legible por Claara: nombre, descripción, cuándo usarla, permisos, estado RAG, y endpoint de invocación.
- **Diseño:** aplicar `design-taste-frontend` dentro del stack actual (PHP + Tailwind CDN + JS vanilla). CSS nuevo en `styles.css`, no inline. UI de administración densa pero tranquila: tablas/listas limpias, panel lateral de edición, estados inline, botones con iconos, nada de landing/marketing dentro del admin.

### Concepto de producto
Una voz es:
- Un asistente especializado.
- Siempre RAG.
- Con instrucciones propias.
- Con documentos indexados propios.
- Con permisos de uso.
- Con estado de publicación.
- Invocable por su página específica y, en una fase posterior, por el chat general.

### High-level Task Breakdown

**Fase 1 — Base de datos y repositorio dinámico**
1. [ ] Crear migración para tabla `voices`.
   - Campos mínimos: `id`, `slug`, `name`, `role`, `description`, `instructions`, `trigger_guidance`, `status` (`draft|published|archived`), `rag_collection`, `icon`, `color`, `created_by`, timestamps.
   - Success: se puede guardar Lex como registro inicial sin perder compatibilidad.
2. [ ] Crear migración para documentos por voz.
   - Opción simple recomendada: cambiar/acompañar `context_documents` para soportar `target_type='voice'` + `target_slug`, manteniendo compatibilidad temporal con `target='lex'`.
   - Success: una voz nueva puede tener documentos sin modificar ENUMs cada vez.
3. [ ] Crear `VoicesRepo`.
   - Métodos: `list`, `findBySlug`, `create`, `update`, `archive`, `publish`, `getPublishedForUser`, `getEditableForUser`.
   - Success: no depende de `VoiceContextBuilder::$voices`.
4. [ ] Refactor mínimo de `VoiceContextBuilder`.
   - Leer configuración desde BD; fallback temporal a Lex hardcodeado hasta completar migración.
   - Colección RAG por voz: `voice_{slug}` o campo `rag_collection`.
   - Success: Lex sigue respondiendo; una voz BD puede construir prompt y retrieval.

**Fase 2 — Permiso de edición y seguridad**
5. [ ] Añadir permiso `feature:voice-editor`.
   - Superadmins siempre pueden editar; usuarios no-superadmin solo si tienen este permiso.
   - Success: `/admin/voices.php` y APIs rechazan usuarios sin permiso.
6. [ ] Integrar voces dinámicas con `available_features`.
   - Al publicar una voz, crear/actualizar `available_features(feature_type='voice', feature_slug=slug)`.
   - Al archivar, desactivar feature sin borrar historial.
   - Success: el panel de permisos puede asignar voces nuevas sin tocar código.

**Fase 3 — API admin de voces**
7. [ ] Crear endpoints CRUD:
   - `GET /api/admin/voices/list.php`
   - `POST /api/admin/voices/create.php`
   - `POST /api/admin/voices/update.php`
   - `POST /api/admin/voices/archive.php`
   - `POST /api/admin/voices/publish.php`
   - Success: operaciones cubiertas con CSRF, validación y errores claros.
8. [ ] Crear endpoints documentales por voz reutilizando contexto:
   - Listar/subir/eliminar/procesar documentos de una voz.
   - Success: una voz muestra documentos, chunks, estado RAG y errores.
9. [ ] Crear endpoint de prueba:
   - `POST /api/admin/voices/test.php` con una pregunta, devuelve respuesta RAG + fuentes + source match + conflictos.
   - Success: admin puede comprobar calidad antes de publicar.

**Fase 4 — Panel admin UX**
10. [ ] Crear `/admin/voices.php`.
   - Vista recomendada:
     - Columna/lista de voces con estado.
     - Panel detalle con tabs: “Profile”, “Knowledge”, “Test”, “Access”.
     - CTA principal contextual: `Create voice`, `Process documents`, `Publish`.
   - Success: un admin puede entender en 10 segundos qué voces existen y qué falta para publicarlas.
11. [ ] Flujo “Crear voz” con wizard simple.
   - Paso 1: identidad (nombre, área, descripción).
   - Paso 2: instrucciones y “cuándo usar esta voz”.
   - Paso 3: documentos.
   - Paso 4: probar y publicar.
   - Success: no se publica una voz sin nombre, instrucciones y al menos un documento procesado.
12. [ ] Estados UX completos.
   - Loading skeletons, empty state (“Create the first voice”), error inline, indexación en progreso, éxito de publicación.
   - Success: no hay spinners genéricos ni estados muertos; todo indica siguiente acción.

**Fase 5 — Frontend de voces dinámicas**
13. [ ] Actualizar `/voices/`.
   - Listar voces publicadas y accesibles para el usuario.
   - Success: voces nuevas aparecen automáticamente.
14. [ ] Crear página genérica `/voices/view.php?voice={slug}` o ruta equivalente.
   - Reutilizar UX de Lex pero con datos dinámicos.
   - Success: una voz nueva tiene chat RAG, historial y documentos sin crear un PHP nuevo.

**Fase 6 — Catálogo para chat general**
15. [ ] Crear `CapabilityCatalogService`.
   - Devuelve usuario + voces publicadas accesibles + gestos accesibles, con descripciones breves y `trigger_guidance`.
   - Success: el chat principal puede recibir el catálogo sin hardcodear voces.
16. [ ] Añadir voces dinámicas al contexto del chat principal.
   - Solo como conocimiento de plataforma en esta fase, sin invocación automática todavía.
   - Success: Claara puede decir “Para esto existe la voz X” con datos reales.

### Success Criteria global
- Un usuario con permiso `feature:voice-editor` puede crear una voz RAG desde frontend sin tocar código.
- Una voz solo puede publicarse cuando tiene documentos procesados correctamente.
- Una voz publicada aparece en `/voices/` y puede asignarse desde permisos.
- Lex sigue funcionando durante y después de la migración.
- La base queda preparada para que el chat general sugiera/invoque voces dinámicas en la siguiente feature.
- UI admin clara, responsive, sin CSS inline nuevo, sin emojis y con estados completos.

### Project Status Board — Voces RAG administrables
- [x] Planner: validar alcance MVP con el usuario.
- [x] Executor: migraciones `voices` + documentos por voz.
- [x] Executor: `VoicesRepo` + refactor mínimo de `VoiceContextBuilder`.
- [x] Executor: permiso `feature:voice-editor`.
- [x] Executor: APIs admin de voces.
- [x] Executor: `/admin/voices.php` con editor, estados y prueba en vivo.
- [x] Executor: `/voices/` dinámico + página genérica de voz.
- [ ] Executor: catálogo inicial para chat general.

### Current Status / Progress Tracking — Voces RAG administrables
- 2026-06-04 (Executor): Hito de migraciones preparado en `docs/migrations/019_dynamic_rag_voices.sql`. La migración extiende `voices` de forma aditiva (`slug`, `role`, `instructions`, `trigger_guidance`, `status`, `rag_collection`, `icon`, `color`, `created_by`, `published_at`), añade target flexible a `context_documents` (`target_type`, `target_slug`, `voice_id`, `indexed_at`), siembra Lex como voz publicada, registra `feature:voice-editor` y mantiene `voice:lex` en `available_features`. No se ha aplicado a producción ni a la BD local.
- Verificación realizada: revisión estática, `git diff --check` OK. No se pudo validar en base temporal local porque MySQL local rechaza acceso root sin credenciales (`Access denied for user 'root'@'localhost'`). Antes de ejecutar en producción, conviene aplicarla primero en entorno controlado o confirmar credenciales locales.
- 2026-06-04 (Executor): Migración `019_dynamic_rag_voices.sql` desplegada a `main`, pull en servidor y aplicada manualmente en producción con script PHP específico para evitar ejecutar migraciones históricas pendientes en `schema_migrations`. Backup previo creado en servidor: `storage/db-backups/pre_019_dynamic_rag_voices_20260604_163256.sql`.
- Verificación producción: `schema_migrations` registra `019_dynamic_rag_voices.sql`; `voices` tiene columnas nuevas; `context_documents` tiene `target_type`, `target_slug`, `voice_id`, `indexed_at`; existe voz `lex` publicada con `rag_collection=lex_knowledge_base`; existen `available_features` para `voice:lex` y `feature:voice-editor`.
- 2026-06-04 (Executor): `VoicesRepo` implementado en `src/Repos/VoicesRepo.php` con lectura dinámica por slug, listado, creación, actualización, publicación, archivado y sincronización con `available_features`. `VoiceContextBuilder` ahora intenta cargar la voz desde BD primero y mantiene fallback legacy si el schema no está disponible o la voz no existe en BD. También incluye `instructions` de la voz dinámica en el prompt RAG y no-RAG.
- Verificación local: `php -l` OK en `src/Repos/VoicesRepo.php`, `src/Voices/VoiceContextBuilder.php` y `src/App/bootstrap.php`; `class_exists('Repos\\VoicesRepo')` OK; `git diff --check` OK. Pendiente probar en producción tras despliegue con Lex (`/voices/lex.php`) para confirmar que sigue respondiendo igual leyendo desde BD.
- 2026-06-04 (Executor): Permiso de edición de voces implementado con `Auth\VoiceEditorGuard`: permite superadmin o usuarios con `feature:voice-editor`. Añadida API admin base en `public/api/admin/voices/` para listar, crear, actualizar, publicar y archivar voces. Los endpoints usan autenticación, CSRF en escritura, validación de slug/campos y sincronizan `available_features` al actualizar/publicar/archivar.
- Verificación local y producción: `php -l` OK en `src/Auth/VoiceEditorGuard.php` y todos los endpoints `public/api/admin/voices/*.php`; `curl` sin sesión a `/api/admin/voices/list.php` devuelve 401 JSON esperado; producción lista `voices=1` con `lex:published` desde `VoicesRepo`. Pendiente UI `/admin/voices.php` para consumir estos endpoints.
- 2026-06-04 (Executor): Primera versión usable de `/admin/voices.php` implementada como Voice Studio. Permite listar voces, crear borradores, editar identidad/instrucciones/guía de activación, publicar, archivar y probar la voz seleccionada en vivo contra `/api/voices/chat.php`. Añadido enlace en el menú de perfil para superadmins y usuarios con `feature:voice-editor`. CSS nuevo ubicado en `public/assets/css/styles.css`; JS en `public/assets/js/admin-voices.js`.
- Verificación local: `php -l` OK en `public/admin/voices.php` y `public/includes/header-unified.php`; `node --check` OK en `public/assets/js/admin-voices.js`; `git diff --check` OK. La BD local no conecta (`db_connection_failed`), por lo que la prueba de datos queda para producción tras pull.
- 2026-06-04 (Executor): `/voices/` convertido a catálogo dinámico desde `VoicesRepo`, filtrado por permisos `voice:{slug}`. Añadida página genérica `/voices/view.php?voice={slug}` que reutiliza el cliente de chat de voces mediante `window.CLAARA_VOICE`. El menú lateral de voces también se alimenta de voces publicadas. Las APIs públicas de voces ahora verifican permiso de acceso por slug.
- 2026-06-04 (Executor): Voice Studio integra conocimiento por voz: endpoints `public/api/admin/voices/documents/` para listar, subir y procesar PDF/TXT/MD en `docs/context/voices/{slug}/knowledge-base`, indexando en la `rag_collection` de la voz. La publicación queda bloqueada si no hay al menos un documento procesado. `ContextDocsRepo` soporta `target_type='voice'`/`target_slug` sin mezclar documentos de voces nuevas en el target legacy de Lex.
- Verificación local: `php -l` OK en PHP tocado; `node --check` OK en `public/assets/js/admin-voices.js` y `public/assets/js/voice-lex.js`; `git diff --check` OK; llamadas HTTP sin sesión a endpoints nuevos devuelven 401/302 esperado sin fatals. Pendiente validación en producción tras deploy con sesión real.
- 2026-06-05 (Executor): Ajuste UX de voces tras prueba real: Voice Studio elimina el panel de Live Preview para dar más espacio al perfil y conocimiento, añade botón `Process all` para procesar todos los documentos pendientes de una voz, `/voices/` se compacta, el sidebar de voces pasa a cargar catálogo dinámico desde `/api/voices/catalog.php` sin cache de voces, y Context Manager deja de mostrar Lex como base gestionable legacy.
- Verificación local: `php -l` OK en `public/admin/voices.php`, `public/admin/context-manager.php`, `public/api/voices/catalog.php`; `node --check` OK en `public/assets/js/admin-voices.js` y `public/assets/js/sidebar-hover.js`; no quedan referencias de UI al live preview eliminado ni pestaña visible de Lex en Context Manager.

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

- 2026-05-28: **CHAT UX/UI Fase 1** implementada (pendiente verificación en navegador del usuario):
  - F1.1 Barra de acciones por mensaje en respuestas del asistente: botón **Copiar** (con feedback ✓) y **Regenerar** respuesta completa. Función única `buildMessageActions()` reusada en carga de historial (`append`), streaming (`finalizeStreamingMessage`) y tras regenerar. Visible al hover en desktop, semivisible en táctil.
  - Nuevo endpoint `public/api/chat-regenerate-full.php`: regenera la respuesta completa reusando el historial previo (solo texto; no reintenta archivos/imágenes/web del turno original). Reusa `OpenRouterClient::generateWithMessages()`, `ContextBuilder`, ownership + CSRF como `chat-regenerate.php`.
  - F1.2 Soporte de **fenced code blocks** (```) en `mdToHtml()` (antes se rompían) + botón **copiar código** por bloque vía `enhanceCodeBlocks()`.
  - CSS nuevo en `public/includes/head.php` (`.code-block`, `.code-copy-btn`, `.msg-actions`, `.msg-action-btn`).
- 2026-05-28: **CHAT UX/UI Fase 2** implementada (pendiente verificación en navegador del usuario):
  - F2.3 Agrupado de mensajes consecutivos del mismo rol: avatar repetido oculto (espaciador invisible) y menor separación (`mt-1` vs `mt-6` entre turnos). Se trackea con `wrap.dataset.role`.
  - F2.4 Timestamps con revelado al hover (`.msg-time` + `.group:hover`); alineados bajo la burbuja.
  - F2.5 Botón flotante "bajar al final" (`#scroll-to-bottom`): aparece al alejarse >120px del fondo en modo chat, scroll suave al pulsarlo.
  - F2.6 Consistencia de render: los mensajes del asistente cargados del historial ahora usan `.markdown-content .prose` (antes solo los de streaming); junto al estilo de `code-block` de Fase 1.
  - F2.7 "Fuentes" → "Sources" en el bloque de citas web.
  - Nota: `grep` trata `public/index.php` como binario (contiene emojis 🍌/🌐); usar `grep -a`.
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

## Feature: External Connectors Governance

### Background and Motivation
iaiaPRO quiere añadir conectores para Google Drive, OneDrive, Slack y Microsoft Teams para importar contenido al contexto. La estimación técnica inicial no debe tratar OAuth y consentimientos como simples prerequisitos ya resueltos: Google y Microsoft tienen procesos de verificación/aprobación que pueden bloquear lanzamiento aunque el código esté listo.

### Key Challenges and Analysis

1. **Google OAuth verification can be the critical path**
   - Google Drive scopes amplios como `drive.readonly` o `drive` pueden activar verificación de scopes restringidos y security assessment si los datos pasan por servidores de la app.
   - Google recomienda scopes mínimos. Para Drive, el camino preferente del MVP debe ser `drive.file` + Google Picker, donde el usuario selecciona explícitamente los archivos que comparte con iaiaPRO.
   - Decisión de producto: aceptar el modelo "user-selected files only" en v1 para evitar una revisión CASA larga/costosa, o asumir el proceso burocrático si se necesita indexar Drive completo.

2. **Microsoft Graph admin consent is a commercial onboarding task**
   - OneDrive y Teams vía Microsoft Graph pueden requerir admin consent en el tenant del cliente, especialmente en entornos empresariales con consentimiento de usuario restringido.
   - Esto no es solo implementación: cada cliente B2B puede necesitar que su IT admin apruebe la app, revise permisos y acepte el flujo de consent.
   - Debemos preparar documentación, pantalla de estado y guía para admins antes de venderlo como feature empresarial.

3. **Scope minimization changes the MVP**
   - Google Drive MVP recomendado: importar archivos seleccionados por el usuario, no sincronizar todo Drive.
   - OneDrive MVP recomendado: usar delegated permissions de menor alcance posible y probar en un tenant real con políticas de consentimiento restrictivas.
   - Teams MVP debe ir después de OneDrive porque añade más fricción de permisos, canales, archivos y tenant governance.

### High-level Task Breakdown

#### Task 0: OAuth/compliance discovery before coding
- Definir scopes exactos por conector.
- Clasificar cada scope: non-sensitive/sensitive/restricted para Google; delegated/application y admin-consent-required para Microsoft.
- Success criteria: tabla aprobada con scope, motivo, alternativa de menor privilegio y bloqueo comercial/técnico.

#### Task 1: Google Drive launch path decision
- Evaluar dos rutas:
  - Fast path: `drive.file` + Google Picker + importación de archivos seleccionados.
  - Full Drive path: scopes amplios + verificación OAuth/restricted scopes + posible CASA/security assessment.
- Success criteria: decisión documentada antes de diseñar la BD y UX del conector.
- Estimated process time:
  - Fast path: 2-5 días de setup OAuth/branding/políticas si no hay incidencias.
  - Full Drive path: varias semanas y posible security assessment anual si se usan restricted scopes con datos pasando por servidor.

#### Task 2: Google OAuth readiness package
- Preparar dominio verificado, OAuth consent screen, homepage pública, privacy policy, terms, data deletion/help contact y demo video si Google lo requiere.
- Success criteria: checklist listo antes de mover el OAuth client a producción.

#### Task 3: Microsoft tenant admin consent package
- Definir permisos Microsoft Graph mínimos para OneDrive y Teams.
- Preparar admin consent URL, explicación de permisos, guía de instalación para IT admin, guía de revocación y troubleshooting.
- Success criteria: un admin de tenant externo puede aprobar la app sin intervención técnica directa.
- Estimated process time:
  - Internal/test tenant: 1-3 días.
  - Cliente B2B nuevo: variable, normalmente dependiente del ciclo de aprobación de su IT/security.

#### Task 4: Shared connector foundation
- Implementar framework común de connectors, tokens, accounts, sync jobs, connector items y estado de sincronización.
- Success criteria: Google Drive fast path puede conectarse, listar/seleccionar/importar y desconectarse sin tocar lógica específica de otros proveedores.

#### Task 5: Connector implementation order
- Google Drive fast path first.
- OneDrive second.
- Slack third.
- Teams last.
- Success criteria: no empezar Teams hasta tener framework + OneDrive validado en tenant Microsoft real.

### Technical Implementation Plan

Implementation principle: build the connector foundation for multiple providers, but ship only Google Drive fast path first. Avoid broad Drive/Graph scopes until the governance tasks explicitly approve them.

#### Phase A: Data model and configuration
- Add migration `017_connectors.sql`.
- Tables:
  - `connector_providers`: static catalog (`google_drive`, `onedrive`, `slack`, `teams`), enabled flag, display metadata.
  - `connector_accounts`: one row per connected external account/workspace per iaiaPRO user; stores provider, user id, external account id/email/name, connection status, last sync/error fields.
  - `connector_tokens`: encrypted OAuth tokens linked to `connector_accounts`; stores access token, refresh token, expiry, scopes and token metadata.
  - `connector_items`: normalized external resources selected/imported by users; provider item id, item type, name, mime type, source URL, checksum/version, sync status.
  - `connector_imports`: import attempts from connector items into iaiaPRO context; status, context target, document id, error message.
- Env/config:
  - `CONNECTOR_TOKEN_ENCRYPTION_KEY`
  - `GOOGLE_CLIENT_ID`
  - `GOOGLE_CLIENT_SECRET`
  - `GOOGLE_REDIRECT_URI`
  - later: Microsoft/Slack equivalents.
- Success criteria:
  - Migration runs cleanly on existing DB.
  - Foreign keys use compatible `BIGINT UNSIGNED` types.
  - Tokens are never stored in plaintext.

#### Phase B: Connector domain layer
- Add namespace `src/Connectors/`.
- Core interfaces/classes:
  - `ConnectorProviderInterface`: provider key, OAuth URLs, token exchange/refresh, account profile fetch.
  - `ConnectorItemImporterInterface`: import selected external item into normalized content.
  - `ConnectorTokenCrypto`: encrypt/decrypt OAuth tokens using `CONNECTOR_TOKEN_ENCRYPTION_KEY`.
  - `ConnectorAccountsRepo`
  - `ConnectorTokensRepo`
  - `ConnectorItemsRepo`
  - `ConnectorImportsRepo`
- Success criteria:
  - Unit-style CLI smoke test can create a fake account/item/import without touching provider APIs.
  - Token crypto round trip works and plaintext token is not visible in DB fields.

#### Phase C: Admin/user connector UI shell
- Add page `public/admin/connectors.php` or `public/connectors.php` depending product decision.
- MVP UI:
  - provider cards with status: `Not connected`, `Connected`, `Error`, `Needs attention`.
  - connect/disconnect buttons.
  - last sync/import/error summary.
  - selected/imported item list.
- Success criteria:
  - Existing sidebar/admin layout remains consistent.
  - No provider-specific UX leaks into the shared shell except provider name/icon/status.

#### Phase D: Shared connector API endpoints
- Add endpoints under `public/api/connectors/`:
  - `providers.php`: list enabled providers and connection status.
  - `connect.php`: start OAuth for provider.
  - `callback.php`: receive OAuth callback, exchange code, create/update account.
  - `disconnect.php`: revoke/delete local connection.
  - `items.php`: list selected/imported connector items.
  - `import.php`: queue import of selected items.
  - `sync.php`: manual sync/import trigger.
- Apply session auth, ownership checks and CSRF for mutable routes.
- Success criteria:
  - Unauthenticated requests rejected.
  - Users cannot see/disconnect/import another user's connector accounts.
  - OAuth `state` is signed/session-bound to prevent CSRF.

#### Phase E: Background jobs
- Add job types in `public/api/jobs/process.php`:
  - `connector-import`
  - later `connector-sync`
- `connector-import` flow:
  - load connector item/account/token.
  - refresh token if needed.
  - fetch/download external content.
  - extract text or file content using existing ingestion utilities where possible.
  - create/update `context_documents` and downstream RAG/indexing using existing context pipeline.
  - update `connector_imports` status and item sync status.
- Success criteria:
  - A selected Google Drive file can be imported without blocking the HTTP request.
  - Failed imports preserve a useful error for UI/admin debugging.

#### Phase F: Google Drive fast path provider
- Scope strategy: `https://www.googleapis.com/auth/drive.file` only.
- UX strategy: Google Picker chooses files explicitly shared with iaiaPRO.
- Provider files:
  - `src/Connectors/Google/GoogleDriveConnector.php`
  - `src/Connectors/Google/GoogleDriveClient.php`
  - `public/assets/js/google-drive-picker.js`
- Flow:
  - user connects Google OAuth.
  - UI opens Google Picker with app client id and OAuth token.
  - selected files are saved as `connector_items`.
  - user clicks import, creating `connector-import` jobs.
- Supported file types for v1:
  - Google Docs exported as plain text or docx/pdf, depending existing extractor support.
  - PDFs, text files, docx if already supported by ingestion.
- Success criteria:
  - No `drive.readonly` or broad Drive scope is requested.
  - User can select one file via Picker, import it, and see it in Context Manager.
  - Revoking/disconnecting removes local tokens and disables future imports.

#### Phase G: OneDrive provider
- Use Microsoft Graph delegated permissions with least privilege.
- Candidate scopes to validate in Task 0:
  - `offline_access`
  - `Files.Read.Selected` if product flow supports selected files.
  - fallback `Files.Read` only if selected-file flow is insufficient.
- Add:
  - `src/Connectors/Microsoft/OneDriveConnector.php`
  - `src/Connectors/Microsoft/GraphClient.php`
- Success criteria:
  - Works in a Microsoft test tenant.
  - Documented whether admin consent is required under restrictive tenant settings.

#### Phase H: Slack provider
- Use Slack OAuth with minimum channel/file scopes.
- Candidate scopes to validate:
  - read selected channels/messages only where possible.
  - avoid broad workspace history ingestion as MVP default.
- MVP:
  - connect workspace.
  - select channel.
  - import recent messages or pinned/files into context.
- Success criteria:
  - Workspace admin approval requirements documented.
  - Imported messages include channel/source metadata and timestamps.

#### Phase I: Teams provider
- Defer until Microsoft framework and OneDrive are stable.
- Use Microsoft Graph with explicit tenant admin consent flow.
- MVP decision needed:
  - Teams channel messages?
  - Teams files via SharePoint/OneDrive?
  - Meeting transcripts?
- Success criteria:
  - Admin consent package tested with a real tenant admin.
  - Permissions are documented in customer-facing language.

#### Phase J: QA, security and release gates
- Test matrix:
  - connect/disconnect/reconnect each provider.
  - token refresh and expired token recovery.
  - import success/failure paths.
  - ownership checks.
  - context document visibility.
  - provider revocation outside iaiaPRO.
- Security gates:
  - encrypted token storage verified.
  - no token leakage in logs/errors.
  - OAuth state validation.
  - least-privilege scopes confirmed.
  - deletion/revocation path documented.
- Release gate:
  - do not expose connector to production users until provider-specific OAuth/admin consent checklist is complete.

### Project Status Board: External Connectors

- [ ] Task 0: OAuth/compliance discovery before coding.
- [x] Task 1: Google Drive launch path decision.
- [ ] Task 2: Google OAuth readiness package.
- [ ] Task 3: Microsoft tenant admin consent package.
- [ ] Task 4: Shared connector foundation.
- [x] Task 5: Connector implementation order.
- [x] Phase A: Data model and configuration.
- [x] Phase B: Connector domain layer.
- [x] Phase C: Admin/user connector UI shell.
- [ ] Phase D: Shared connector API endpoints.
- [ ] Phase E: Background jobs.
- [ ] Phase F: Google Drive fast path provider.
- [ ] Phase G: OneDrive provider.
- [ ] Phase H: Slack provider.
- [ ] Phase I: Teams provider.
- [ ] Phase J: QA, security and release gates.

### Planner Notes

Recommendation as of 2026-05-15: choose Google Drive `drive.file` + Picker for v1 unless the business explicitly needs full Drive crawling. Treat Google restricted scope verification/CASA and Microsoft tenant admin consent as launch blockers with their own timeline, not as normal engineering tasks.

Recommended build order:
1. Finish Tasks 0-2 for Google Drive fast path in parallel with Phase A.
2. Build Phases A-E as shared foundation.
3. Build Phase F and ship to a limited internal/test account.
4. Only then start Microsoft/Slack providers.

2026-05-15 Executor progress:
- Phase A started.
- Created migration `docs/migrations/017_connectors.sql` with connector provider catalog, accounts, encrypted token storage, selected items and import tracking.
- Migration was executed and marked in production on 2026-05-15.
- Phase B started.
- Added connector contracts, token crypto and repos under `src/Connectors/`; no UX/API endpoints yet.
- UX decision: use a user-owned `Connectors` page first, with an admin global overview for provider health/status.
- Phase C started.
- Added read-only connector shell pages (`/connectors.php`, `/admin/connectors.php`), navigation entry, CSS/JS assets and read-only status endpoints. OAuth actions remain disabled until Phase D/F.

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
- [x] Task 5: API endpoints.
- [x] Task 6: Main gesture UI.
- [x] Task 7: Register gesture in navigation.
- [x] Task 8: Export.
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

2026-05-14 Task 5 findings:
- Creados endpoints en `public/api/gestures/lead-finder/`:
  - `search.php`: crea run + background job `lead-finder` y vincula `job_id`.
  - `get.php`: devuelve run + resultados.
  - `history.php`: lista runs del usuario.
  - `update-result.php`: edición de fila y estado (`pending|validated|rejected`).
  - `export.php`: export CSV por descarga directa.
  - `delete.php`: borra run (cascade de resultados).
- Todos aplican sesión, validación de acceso al gesture (`lead-finder`) y CSRF en endpoints mutables.
- Validación de sintaxis completada con `php -l` en los seis endpoints.

2026-05-14 Task 6-8 findings:
- Creada pantalla principal `public/gestos/lead-finder.php` con sidebar/historial del patrón de gestures, input mínimo, selector compacto de resultados, chips de ejemplo, estados empty/loading/error/result y tabla editable.
- Añadido `public/assets/js/gesture-lead-finder.js` para crear búsquedas, despertar worker, pollear estado, cargar historial, editar/validar/rechazar filas, borrar runs y descargar CSV.
- Añadido CSS nuevo en `public/assets/css/styles.css` y enlazado desde `public/includes/head.php`, evitando CSS inline.
- Registrado Lead Finder en `public/gestos/index.php` y acceso por defecto en `UserFeatureAccessRepo`.
- Export CSV queda implementado y conectado desde UI. XLSX queda diferido hasta decidir dependencia/formato; el MVP usa CSV porque Excel lo abre correctamente y no añade librerías nuevas.
- Validación local completada con `php -l` en PHP modificado/endpoints y `node --check` en el JS.

---

# Current Status / Progress Tracking

- 2026-05-27 (Executor): Iniciada migración de marca/repositorio de iaiaPRO a Claara (`claara.tech`) en modo inventario, sin cambiar todavía remotos ni producción.
- 2026-05-27 (Executor): Inventario local: repo en `/Users/dvdgp/Documents/Codeapps/iaiapro`, rama `codex/iaiapro-full`, `origin` todavía apunta a `https://github.com/dvdgp9/iaia.git`; el árbol local ya tiene cambios previos no relacionados: borrados en `docs/translations/pages/public__aplicaciones__index.php.md` y `docs/translations/pages/public__connectors.php.md`.
- 2026-05-27 (Executor): Inventario servidor: SSH alias `iaiapro` funciona; host reporta `mail.claara.tech`, usuario `codex`; producción actual en `/home/dvdgp/web/iaiapro.com/public_html`, rama `main`, remoto `git@github.com:dvdgp9/iaia.git`.
- 2026-05-27 (Executor): Inventario repo nuevo: `git ls-remote --heads https://github.com/dvdgp9/Claara.git` responde sin ramas, por lo que parece ser un repositorio GitHub vacío listo para recibir el historial.
- 2026-05-27 (Executor): Repo nuevo inicializado: push local de `HEAD` a `https://github.com/dvdgp9/Claara.git` como rama `main` completado. El servidor no pudo hacer push porque su clave GitHub es read-only.
- 2026-05-27 (Executor): Desplegada copia de producción actual desde `/home/dvdgp/web/iaiapro.com/public_html` a `/home/dvdgp/web/claara.tech/public_html`; el placeholder original de Hestia quedó guardado en `/home/dvdgp/web/claara.tech/public_html.hestia-placeholder-20260527-165627`.
- 2026-05-27 (Executor): Ajustado `APP_URL=https://claara.tech` en la copia de producción de Claara y cambiado el remoto `origin` de esa copia a `git@github.com:dvdgp9/Claara.git`; `git fetch origin` funciona y el checkout queda limpio en `main...origin/main`.
- 2026-05-27 (Executor): Hestia actualizado para que `claara.tech` use document root `/home/dvdgp/web/claara.tech/public_html/public`, igual que iaiaPRO usa su subcarpeta `public`.
- 2026-05-27 (Executor): Verificación HTTP desde servidor con `Host: claara.tech`: `/` devuelve redirect a `/login.php`; `/login.php` devuelve 200 y HTML de login, con cookie de sesión en dominio `claara.tech`. La resolución DNS desde el sandbox local fue intermitente durante la verificación.
- 2026-05-27 (Executor): Repo local actualizado para apuntar `origin` a `https://github.com/dvdgp9/Claara.git`; rama local `codex/iaiapro-full` ahora trackea `origin/main`. Se preservan cambios locales previos no relacionados.
- 2026-05-27 (Executor): Rebranding visual Claara iniciado. Generados assets desde `/Users/dvdgp/Downloads/Claara-logo.png` (`public/assets/images/logo.png`, `claara-logo.png`, `isotipo.png` y PWA icons), actualizados nombres visibles principales de iaiaPRO/Nana a Claara, paleta base a `#B7C9F2`, `#E3ECFB`, `#FF8B73`, `#FFF6F1`, `#F3F6FA`, `#2F3440`, y headers/títulos de proveedor a Claara. Se mantienen claves internas `ebonia`/`eboniato` donde son identificadores técnicos de contexto/API para no romper compatibilidad.
- 2026-05-27 (Executor): Validación rebranding local: `php -l` en PHP modificado sin errores, `node --check` en JS modificado sin errores, y QA visual del login con servidor local `php -S 127.0.0.1:8788 -t public` correcto.
- 2026-05-27 (Executor): Reemplazados assets definitivos de Claara desde `logo-claara.png` e `isotipo-claara.png`. Actualizados `logo.png`, `claara-logo.png`, `logo-claara.png`, `isotipo.png`, `isotipo-claara.png`, `ebonia-logo.png`, `favicon.ico` e iconos PWA para que cualquier ruta antigua muestre ya Claara. Actualizados prompts y documentación visible restante de iaiaPRO/Ebonia/Nana a Claara, preservando identificadores internos `ebonia`/`eboniato` donde son claves técnicas.
- 2026-05-27 (Executor): Carpeta local renombrada de `/Users/dvdgp/Documents/Codeapps/iaiapro` a `/Users/dvdgp/Documents/Codeapps/Claara`. El remoto local sigue apuntando a `https://github.com/dvdgp9/Claara.git`.
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
- 2026-05-14 (Executor): Lead Finder Task 5 completada. Endpoints API listos para search/get/history/update/export/delete con auth/csrf.
- 2026-05-14 (Executor): Lead Finder Tasks 6-8 completadas. UI principal, registro del gesture, edición/validación de resultados e export CSV implementados con provider mock.
- 2026-05-14 (Executor): Lead Finder Task 9 iniciada y casi completada. Integrado provider real de Apify con selección por `.env`, manteniendo mock como fallback.
- 2026-05-14 (Executor): Lead Finder Apify ajustado para `compass/crawler-google-places`: parsing de query libre a `searchStringsArray` + `locationQuery`, con cap global algo mayor para mejorar cobertura sin multiplicar runs.
- 2026-05-18 (Executor): Implementado `scripts/extract_page_texts.php` para extracción de textos de traducción y generación de pack en `docs/translations/` (CSV + JSON + un `.md` por página pública).
- 2026-05-18 (Executor): Ajustado extractor para que por defecto excluya `/public/admin` (páginas públicas reales) y añadir flag `--include-admin` para generar también admin cuando se necesite.

## Feature: Voice Answer Trust & Source Conflicts (hybrid)

### Background and Motivation
Petición del compañero: cuando una voz responde, debe mostrar (1) un porcentaje de confianza sobre la información encontrada y (2) avisar de posibles fuentes en conflicto. Contexto: las voces actuales (`lex`, `cubo`, `uniges`) van a desaparecer; el objetivo es que los admins creen voces nuevas desde la app y TODAS sean RAG. Por tanto el diseño asume voz = RAG (Qdrant) y no hace casos especiales para voces estáticas.

### Key Challenges and Analysis (Voice Trust)
- **Naturaleza del "trust %"**: dos señales muy distintas. (a) Objetiva: similitud de los chunks recuperados de Qdrant (`score`), determinista, mide cobertura documental, NO veracidad. (b) Subjetiva: autoevaluación del modelo, mal calibrada. Decisión: NO fusionarlas en un único número engañoso. Se muestran como elementos separados y etiquetados con honestidad ("Source match", no "Accuracy").
- **Conflictos entre fuentes**: solo el modelo puede leer dos fragmentos y detectar contradicción → se obtiene vía salida estructurada del LLM, no de los scores.
- **Salida estructurada**: las voces NO usan streaming (`voices/chat.php` usa `generateWithMessages`), así que se puede pedir al modelo un JSON `{ answer_markdown, sources[], conflicts[] }` y parsearlo. Riesgo: JSON malformado → fallback: tratar el texto crudo como respuesta y ocultar metadatos (sin romper el chat).
- **Persistencia sin migración**: `voice_executions.input_data` es JSON libre. Se embebe `meta` en el mensaje assistant del history → el restore de historial vuelve a mostrar badges. No se toca el esquema.
- **Score de Qdrant**: cosine (~0..1). Umbrales propuestos (a validar/tunear): High ≥ 0.75, Medium 0.50–0.75, Low < 0.50. Representante = media de los top-3 chunks recuperados. Marcado como ASUNCIÓN a confirmar con datos reales.
- **Etiquetado (requisito explícito del usuario)**: badge "Source match: 82% · High" + tooltip: "Indica cuánto coinciden los documentos de apoyo con tu pregunta. No es una garantía de exactitud factual."

### High-level Task Breakdown (Voice Trust)
1. **Backend: exponer score de recuperación.** En `voices/chat.php`, cuando `useRag`, capturar los chunks recuperados (vía `LexRetriever::retrieve`) y calcular `source_match = {percent, band}` con la media de top-3 scores.
   - Success: la respuesta JSON del endpoint incluye `source_match` cuando hay RAG; ausente/`null` cuando no.
2. **Backend: salida estructurada del LLM.** Ajustar el system prompt (en `VoiceContextBuilder`) para exigir un objeto JSON `{ answer_markdown, sources: string[], conflicts: [{topic, sources[], note}] }`. Parsear el reply en `voices/chat.php`; si falla el parseo, fallback a texto plano sin metadatos.
   - Success: con una pregunta cubierta por la KB, el endpoint devuelve `sources` no vacío; con un caso de contradicción preparado, devuelve `conflicts` no vacío; con JSON malformado simulado, el chat sigue respondiendo (texto plano).
3. **Backend: persistir meta en historial.** Guardar `meta = { source_match, sources, conflicts }` junto al mensaje assistant dentro de `input_data.history`.
   - Success: tras recargar una ejecución vía `voices/get.php`, el `meta` viaja en el history.
4. **Frontend: render de badge + fuentes + conflicto.** En `voice-lex.js` (`appendMessage` / `sendMessage` / restore loop): añadir bajo la burbuja del assistant un badge "Source match" con color por banda, chips de `sources`, y aviso "⚠️ Sources disagree" expandible cuando hay `conflicts`. Tooltip de etiquetado honesto.
   - Success: QA visual: badge con color correcto por banda; chips de fuentes visibles; aviso de conflicto solo cuando procede; el restore desde historial muestra lo mismo.
5. **QA end-to-end + tuning de umbrales.** Probar preguntas de alta/baja cobertura y un caso de conflicto real en la KB de `lex`; ajustar umbrales si los % no se sienten representativos. Documentar valores finales en Lessons.
   - Success: validación manual del usuario (Planner confirma cierre).

> Nota de diseño: el actual `voice-lex.js` es por-voz. Si se generaliza a voces creadas por admin, el render de trust debería vivir en un JS de voz genérico; se mantiene el cambio en `voice-lex.js` por ahora y se anota la deuda para la futura refactor de "voces dinámicas".

### Current Status / Progress Tracking (Voice Trust)
- 2026-05-27 (Executor): Tareas 1-3 (backend) completadas. `VoiceContextBuilder`: captura `lastChunks` en RAG, nuevo `computeSourceMatch()` (media top-3 scores → percent + band high/medium/low) y contrato de salida estructurada JSON `{answer_markdown, sources[], conflicts[]}` añadido al system prompt RAG. `voices/chat.php`: nueva `parseVoiceReply()` con fallback a texto plano si el JSON viene mal, calcula `meta`, persiste `meta` en el mensaje assistant de `input_data.history` (sin migración) y devuelve `reply` (answer limpio) + `meta`. `php -l` OK; parser probado en aislado (dedupe de sources, strip de fences, fallback).
- 2026-05-27 (Executor): Tarea 4 (frontend) completada. `voice-lex.js`: nuevo `renderMeta()` que pinta badge "Source match" coloreado por banda (con tooltip honesto), chips de `sources` y aviso "Sources disagree" solo cuando hay `conflicts`. `appendMessage()` acepta `meta`; `sendMessage()` y el restore de historial lo propagan. `node --check` OK.

# Executor's Feedback or Assistance Requests

- Voces RAG administrables: hito `VoicesRepo` + refactor mínimo implementado localmente. Solicito validación antes de pasar a APIs admin. Para validar en producción, desplegar el commit y probar Lex desde `/voices/lex.php`; si responde con fuentes/source match como antes, el siguiente paso será crear el guard de permiso `feature:voice-editor` y endpoints admin de voces.

- Landing pública Claara: primer hito listo para validación manual. Solicito revisar:
  1. Abrir `/` y confirmar que la landing se ve bien en desktop y móvil.
  2. Pulsar "Log in" desde la landing y confirmar que abre `/login.php`.
  3. Iniciar sesión y confirmar que entra a `/app/`.
  4. Desde la app, pulsar el tab "Chat" en desktop/móvil y confirmar que vuelve a `/app/`.
  5. Si un usuario sin sesión abre `/app/`, debe redirigir a `/login.php`.
  Cuando el usuario confirme este hito, el Planner puede marcar como completadas las tareas de routing/landing inicial y autorizar el siguiente refinamiento visual si hace falta.

- Voice Trust: Tareas 1-4 implementadas (backend + frontend) y validadas a nivel de sintaxis. No puedo verificar end-to-end desde aquí porque requiere sesión, OpenRouter, BD y Qdrant en marcha. Solicito QA manual del usuario (Tarea 5) en la voz `lex`:
  1. Pregunta bien cubierta por la KB → debe aparecer badge "Source match" (idealmente High/Medium) y chips de fuentes.
  2. Pregunta fuera de cobertura → badge en Low (verificar que el % se siente representativo; si no, ajustamos umbrales).
  3. Pregunta sobre un punto donde dos convenios se contradigan → debe salir el aviso "Sources disagree".
  4. Recargar una consulta desde el historial lateral → badge/fuentes/conflictos deben re-renderizarse igual.
  Tras tu feedback ajusto umbrales (High 0.75 / Medium 0.50 son una asunción inicial) y, si todo va bien, el Planner cierra la feature.

- Migración Claara: hito de inventario completado. Siguiente paso propuesto para validar manualmente: cambiar el remoto local a `dvdgp9/Claara.git` y empujar la rama actual como `main`, preservando los dos borrados locales no relacionados sin revertirlos. Después se podrá actualizar el remoto del checkout de producción y revisar si Hestia ya tiene configurado el dominio `claara.tech` apuntando al nuevo docroot.
- Migración Claara: despliegue base completado. Pendiente de validación manual del usuario: abrir `https://claara.tech/login.php`, iniciar sesión y confirmar que la app funciona igual que en `iaiapro.com`. Pendiente opcional para una fase posterior: rebranding visible de textos/logos `iaiaPRO` a `Claara`.
- Rebranding Claara: primer corte local validado y listo para desplegar. Pendiente tras despliegue: revisar visualmente `https://claara.tech/login.php` y hacer login manual para confirmar header/sidebar dentro de la app.
- Rebranding Claara assets finales: commit/push/deploy completados y carpeta local renombrada a `/Users/dvdgp/Documents/Codeapps/Claara`. Pendiente de validación manual del usuario: abrir `https://claara.tech/login.php`, comprobar login e inicio con los logos definitivos.
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
- Lead Finder: Task 5 completada. Siguiente paso sugerido: Task 6, construir `public/gestos/lead-finder.php` + `public/assets/js/gesture-lead-finder.js` con UX completa.
- Lead Finder: Tasks 6-8 completadas. Siguiente paso sugerido: Task 10, QA manual en producción con provider mock; Task 9 queda bloqueada hasta elegir API real y revisar documentación actualizada.
- Lead Finder: Task 9 (Apify) implementada en código (`ApifyLeadSearchProvider` + `buildLeadSearchProvider()`), pendiente validación end-to-end con una búsqueda real desde UI y revisión del actor elegido.
- Translation extraction milestone: paquete listo para traductores en `docs/translations/`. Solicitud al usuario/planner: validar si en esta fase se exporta solo páginas públicas (`php scripts/extract_page_texts.php`) o también admin (`php scripts/extract_page_texts.php --include-admin`).

---

## Feature: Mejora UX/UI del Chat (estado vacío + conversación)

### Background and Motivation
El chat principal (`public/index.php`) funciona, pero la UX/UI tiene carencias frente a chats modernos. Objetivo: hacerlo mejor sin rediseño disruptivo. Decisiones acordadas con el usuario (2026-05-28):
- **Mantener estilo de burbujas** (puliendo), NO migrar a estilo plano tipo ChatGPT.
- Priorizar: (1) acciones por mensaje, (2) pulido del estado vacío, (3) detalles de conversación.
- Fuera de alcance por ahora: prompts sugeridos en estado vacío.
- Modo de trabajo: documentar plan y, tras OK del usuario, ejecutar por fases con verificación.

### Key Challenges and Analysis
- Todo el JS del chat es inline en `public/index.php` (~3228 líneas). Hay un renderizador `append()` (no-streaming) y `finalizeStreamingMessage()` (streaming) con **lógica duplicada** (imágenes, citas, botones de descarga). Cualquier acción por mensaje debe añadirse en ambos caminos o unificarse.
- Estilos: clases utilitarias Tailwind (CDN) + bloque `<style>` en `public/includes/head.php` + `public/assets/css/styles.css`. CSS nuevo va en head.php o styles.css (regla del usuario: nada inline).
- Ya existe barra de selección para editar/regenerar **por selección de texto** (`#selection-toolbar` desktop / `#selection-bar-mobile`). Las nuevas acciones por mensaje (copiar/regenerar completo) deben convivir sin colisionar con esa selección.
- Funciones existentes reutilizables: `mdToHtml()`, `escapeHtml()`, `api()`, endpoint `/api/chat-regenerate.php` (ya usado en regeneración por selección).
- Riesgo: el avatar+timestamp en cada mensaje se genera en `append()`. Agrupar mensajes consecutivos requiere comprobar el rol del último `wrap` insertado.

### High-level Task Breakdown

**Fase 1 — Acciones por mensaje (mayor impacto funcional)**
1. [ ] Añadir barra de acciones al pie de cada burbuja del asistente (visible al hover en desktop, siempre en móvil):
   - **Copiar** respuesta (texto plano del markdown).
   - **Regenerar** respuesta completa (reusar flujo de `/api/chat-regenerate.php`).
   - Implementar como función única `buildMessageActions(bubble, content, messageId)` y llamarla desde `append()` y `finalizeStreamingMessage()` (evitar duplicar).
   - Success: en una respuesta del asistente aparecen los botones; "Copiar" copia y muestra feedback ("Copiado"); "Regenerar" relanza y sustituye la respuesta.
2. [ ] Botón **"Copiar"** en bloques de código (`<pre>`) dentro del markdown renderizado.
   - Success: cada bloque de código muestra botón al hover que copia su contenido.

**Fase 2 — Detalles de conversación (pulido visual/UX)**
3. [ ] **Agrupar mensajes consecutivos** del mismo rol: ocultar avatar repetido y reducir separación cuando el mensaje anterior es del mismo rol.
   - Success: dos mensajes seguidos del asistente no repiten avatar; el espaciado es menor entre ellos que entre turnos.
4. [ ] **Timestamps menos intrusivos**: mostrar la hora al hover del mensaje en vez de siempre visible (mantener accesible).
   - Success: la hora aparece al pasar el ratón; en móvil se mantiene discreta.
5. [ ] **Botón "bajar al final"** flotante que aparece cuando el usuario ha hecho scroll hacia arriba (útil durante streaming largo).
   - Success: aparece al alejarse del fondo, desaparece al volver, y baja suavemente al pulsarlo.
6. [ ] **Estilo de markdown/código** en burbuja del asistente: mejorar `pre`/`code`, listas, tablas y citas para mejor legibilidad (CSS en head.php/styles.css).
   - Success: bloques de código con fondo y monoespaciado claros; listas y tablas legibles dentro de la burbuja.
7. [ ] **Consistencia de idioma**: cambiar "Fuentes" (citas web) a inglés "Sources" para alinear con el resto de la UI.
   - Success: no quedan textos mezclados ES/EN en la conversación.

**Fase 3 — Pulido del estado vacío**
8. [ ] **Saludo según la hora** ("Good morning/afternoon/evening, {nombre}") en vez de "Hi" estático.
   - Success: el saludo cambia según la franja horaria del cliente.
9. [ ] **Jerarquía visual**: dar protagonismo al input (foco automático, refinar tarjetas Voices/Gestures para que no compitan) y revisar espaciados/responsive.
   - Success: al cargar el estado vacío el cursor está en el input; en móvil el input es lo primero visible sin scroll.

### Success Criteria (global)
- Sin regresiones en streaming, adjuntos, imágenes generadas, citas web, descargas y selección/edición existente.
- Verificación manual en navegador (desktop + móvil) al cierre de cada fase.
- CSS nuevo en `head.php`/`styles.css`, nunca inline.

### Project Status Board — Chat UX/UI
- [x] F1.1 Acciones por mensaje (copiar / regenerar) — implementado, pendiente verificación usuario
- [x] F1.2 Copiar bloque de código (+ render de fenced code) — implementado, pendiente verificación usuario
- [x] F2.3 Agrupar mensajes consecutivos — implementado, pendiente verificación usuario
- [x] F2.4 Timestamps al hover — implementado, pendiente verificación usuario
- [x] F2.5 Botón bajar al final — implementado, pendiente verificación usuario
- [x] F2.6 Estilo markdown/código (+ .prose en historial) — implementado, pendiente verificación usuario
- [x] F2.7 Consistencia de idioma (Sources) — implementado, pendiente verificación usuario
- [ ] F3.8 Saludo según hora
- [ ] F3.9 Jerarquía/foco estado vacío

# Lessons

- Para cambios de configuración editable por superadmin, conviene desacoplar la lista hardcodeada del frontend y moverla a una tabla + API admin, manteniendo un endpoint de solo lectura para UI (`/api/models/list.php`).
- En jobs largos de audio, no usar una ventana fija corta de `resetStuckJobs()`. Debe ser mayor que `BACKGROUND_JOB_MAX_SECONDS`, porque si no se reinician jobs legítimos en mitad de la transcripción y pueden lanzarse workers duplicados desde el polling del frontend.
- Los comandos externos (`ffprobe`, `ffmpeg`) deben ejecutarse con timeout explícito. `exec()` sin timeout puede dejar un job indefinidamente en la misma fase si un contenedor de audio bloquea el análisis.
- `.gitignore` ignoraba `migrations/`, lo que también ocultaba SQL nuevos bajo `docs/migrations/`. Para nuevas migraciones versionadas, mantener la excepción `!docs/migrations/` y `!docs/migrations/*.sql`; si no, `git add .` no las sube.
- Para providers externos de scraping, dejar selección por variable de entorno y fallback mock reduce riesgo durante despliegue: permite activar/desactivar proveedor real sin tocar frontend ni schema.
- En migraciones con foreign keys, confirmar que los tipos coinciden exactamente con la tabla referenciada. `users.id` usa `BIGINT UNSIGNED`; usar `INT` en tablas nuevas provoca MySQL errno 150.
- Evitar foreign keys no esenciales contra tablas antiguas con historial de tipos inconsistente. Para `lead_finder_runs.job_id`, basta índice normal y validación en aplicación.
- En exportación de textos para traducción, separar por defecto páginas públicas de admin evita ruido para proveedores externos; mantener `--include-admin` como modo explícito.
