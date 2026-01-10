# Ebonia - Esquema de Base de Datos (MVP)

Este documento es la fuente de verdad del modelo de datos. Cualquier cambio debe reflejarse aquí antes de aplicarse a la BD.

## Convenciones
- Nombres: `snake_case` para tablas y columnas.
- PK: `BIGINT UNSIGNED AUTO_INCREMENT` con nombre `id`.
- FK: `*_id` con índices.
- Fechas: `DATETIME` (UTC) con `created_at`, `updated_at`.
- Texto largo: `TEXT` o `LONGTEXT` (mensajes).
- JSON: tipo `JSON` en MySQL 8.0+.
- Soft delete: no incluido en MVP (se puede añadir `deleted_at` más tarde si se requiere).

## Diagrama lógico (alto nivel)
- Autenticación y RBAC: `users`, `roles`, `permissions`, `user_roles`, `role_permissions`.
- Organización: `companies`, `departments`.
- Conversaciones: `folders`, `conversations`, `messages`.
- Catálogos IA: `voices` (asistentes), `gestures` (quick actions).
- Futuro (V2 RAG): `documents` (y posibles tablas auxiliares de indexado/embeddings).

---

## Tabla: `companies`
Empresas del grupo, preparado para multi-empresa futuro.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `name` VARCHAR(150) NOT NULL
- `slug` VARCHAR(150) NOT NULL UNIQUE
- `active` TINYINT(1) NOT NULL DEFAULT 1
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `UNIQUE KEY companies_slug_uq (slug)`

Seeds sugeridos (MVP):
- Grupo Ebone, Ebone Servicios, Uniges-3, CUBOFIT

## Tabla: `departments`
Departamentos corporativos.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `company_id` BIGINT UNSIGNED NULL -- opcional (MVP puede dejarlo NULL si es corporativo)
- `name` VARCHAR(120) NOT NULL
- `slug` VARCHAR(120) NOT NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY departments_company_id_idx (company_id)`
- `UNIQUE KEY departments_slug_uq (slug)`

Seeds sugeridos (MVP): Marketing, Contabilidad, Laboral, Proyectos, Operaciones

## Tabla: `users`
Usuarios internos con login propio.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `company_id` BIGINT UNSIGNED NULL
- `department_id` BIGINT UNSIGNED NULL
- `email` VARCHAR(190) NOT NULL UNIQUE
- `password_hash` VARCHAR(255) NOT NULL -- Argon2id recomendado
- `first_name` VARCHAR(80) NOT NULL
- `last_name` VARCHAR(120) NOT NULL
- `is_superadmin` TINYINT(1) NOT NULL DEFAULT 0 -- atajo de administración global
- `status` ENUM('active','disabled') NOT NULL DEFAULT 'active'
- `last_login_at` DATETIME NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `UNIQUE KEY users_email_uq (email)`
- `KEY users_company_id_idx (company_id)`
- `KEY users_department_id_idx (department_id)`

## Tablas RBAC
### `roles`
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `name` VARCHAR(80) NOT NULL
- `slug` VARCHAR(80) NOT NULL UNIQUE -- p.ej. 'admin', 'user'
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

### `permissions`
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `name` VARCHAR(120) NOT NULL
- `slug` VARCHAR(120) NOT NULL UNIQUE -- p.ej. 'chat.use', 'voices.manage', 'gestures.run'
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

### `user_roles`
- `user_id` BIGINT UNSIGNED NOT NULL
- `role_id` BIGINT UNSIGNED NOT NULL
- `created_at` DATETIME NOT NULL

PK compuesta: (`user_id`, `role_id`)

Índices:
- `KEY user_roles_role_id_idx (role_id)`

### `role_permissions`
- `role_id` BIGINT UNSIGNED NOT NULL
- `permission_id` BIGINT UNSIGNED NOT NULL
- `created_at` DATETIME NOT NULL

PK compuesta: (`role_id`, `permission_id`)

Índices:
- `KEY role_permissions_permission_id_idx (permission_id)`

## Tabla: `folders`
Carpetas personales por usuario para organizar conversaciones.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED NOT NULL
- `name` VARCHAR(150) NOT NULL
- `parent_id` BIGINT UNSIGNED NULL -- jerarquía
- `sort_order` INT NOT NULL DEFAULT 0
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY folders_user_id_idx (user_id)`
- `KEY folders_parent_id_idx (parent_id)`

## Tabla: `conversations`
Conversación de un usuario con un asistente (voz) y/o gesto.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `user_id` BIGINT UNSIGNED NOT NULL
- `folder_id` BIGINT UNSIGNED NULL
- `voice_id` BIGINT UNSIGNED NULL -- asistente usado (si aplica)
- `company_id` BIGINT UNSIGNED NULL -- para filtrar contexto futuro
- `title` VARCHAR(200) NOT NULL
- `status` ENUM('active','archived') NOT NULL DEFAULT 'active'
- `is_favorite` TINYINT(1) NOT NULL DEFAULT 0 -- marcada como favorita por el usuario
- `metadata` JSON NULL -- datos auxiliares (p.ej., tags)
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY conversations_user_id_idx (user_id)`
- `KEY conversations_folder_id_idx (folder_id)`
- `KEY conversations_voice_id_idx (voice_id)`
- `KEY conversations_user_favorite_idx (user_id, is_favorite)`

## Tabla: `messages`
Mensajes dentro de una conversación.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `conversation_id` BIGINT UNSIGNED NOT NULL
- `user_id` BIGINT UNSIGNED NULL -- autor si role='user'
- `role` ENUM('user','assistant','system') NOT NULL
- `content` LONGTEXT NOT NULL -- texto plano/markdown
- `model` VARCHAR(120) NULL -- p.ej., 'gemini-1.5-flash'
- `input_tokens` INT NULL
- `output_tokens` INT NULL
- `metadata` JSON NULL -- tool_calls, function_args, etc.
- `created_at` DATETIME NOT NULL

Índices:
- `KEY messages_conversation_id_idx (conversation_id)`
- `KEY messages_user_id_idx (user_id)`

## Tabla: `voices`
Asistentes preconfigurados por superadmin.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `name` VARCHAR(120) NOT NULL
- `description` VARCHAR(300) NULL
- `provider` ENUM('gemini','openai','qwen','other') NOT NULL DEFAULT 'gemini'
- `model` VARCHAR(120) NOT NULL -- p.ej., 'gemini-1.5-flash'
- `system_prompt` TEXT NULL
- `visibility` ENUM('global','company','department','user') NOT NULL DEFAULT 'global'
- `scope_company_id` BIGINT UNSIGNED NULL
- `scope_department_id` BIGINT UNSIGNED NULL
- `scope_user_id` BIGINT UNSIGNED NULL
- `temperature` DECIMAL(3,2) NULL
- `top_p` DECIMAL(3,2) NULL
- `max_output_tokens` INT NULL
- `metadata` JSON NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY voices_scope_company_id_idx (scope_company_id)`
- `KEY voices_scope_department_id_idx (scope_department_id)`
- `KEY voices_scope_user_id_idx (scope_user_id)`

## Tabla: `gestures`
Acciones rápidas preconfiguradas por superadmin.

- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `name` VARCHAR(120) NOT NULL
- `description` VARCHAR(300) NULL
- `prompt_template` TEXT NOT NULL -- plantilla base
- `config` JSON NULL -- parámetros (p.ej., nivel de "fantasía": 1-3)
- `provider` ENUM('gemini','openai','qwen','other') NOT NULL DEFAULT 'gemini'
- `model` VARCHAR(120) NOT NULL
- `visibility` ENUM('global','company','department','user') NOT NULL DEFAULT 'global'
- `scope_company_id` BIGINT UNSIGNED NULL
- `scope_department_id` BIGINT UNSIGNED NULL
- `scope_user_id` BIGINT UNSIGNED NULL
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY gestures_scope_company_id_idx (scope_company_id)`
- `KEY gestures_scope_department_id_idx (scope_department_id)`
- `KEY gestures_scope_user_id_idx (scope_user_id)`

---

## Relaciones (FK) sugeridas
- `departments.company_id` → `companies.id`
- `users.company_id` → `companies.id`
- `users.department_id` → `departments.id`
- `user_roles.user_id` → `users.id`
- `user_roles.role_id` → `roles.id`
- `role_permissions.role_id` → `roles.id`
- `role_permissions.permission_id` → `permissions.id`
- `folders.user_id` → `users.id`
- `folders.parent_id` → `folders.id`
- `conversations.user_id` → `users.id`
- `conversations.folder_id` → `folders.id`
- `conversations.voice_id` → `voices.id`
- `conversations.company_id` → `companies.id`
- `messages.conversation_id` → `conversations.id`
- `messages.user_id` → `users.id`
- `voices.scope_company_id` → `companies.id`
- `voices.scope_department_id` → `departments.id`
- `voices.scope_user_id` → `users.id`
- `gestures.scope_company_id` → `companies.id`
- `gestures.scope_department_id` → `departments.id`
- `gestures.scope_user_id` → `users.id`

Notas:
- En MySQL, añadir FKs con `ON DELETE SET NULL` donde aplica a `scope_*` y `parent_id`.

---

## Índices recomendados adicionales
- `conversations (user_id, updated_at DESC)` para listar recientes.
- `messages (conversation_id, id ASC)` para carga secuencial.
- `folders (user_id, parent_id, sort_order)` para listados jerárquicos.
- `users (email)` UNIQUE para login.

---

## Semillas (MVP)
- `roles`: `admin`, `user`.
- `permissions` (ejemplo mínimo):
  - `chat.use`, `conversations.manage_own`
  - `voices.view`, `voices.manage`
  - `gestures.view`, `gestures.run`, `gestures.manage`
  - `users.manage` (solo admin)
- `companies`: Grupo Ebone, Ebone Servicios, Uniges-3, CUBOFIT
- `departments`: Marketing, Contabilidad, Laboral, Proyectos, Operaciones

---

## Futuro (V2 RAG)
### `documents`
- `id` BIGINT UNSIGNED PK AUTO_INCREMENT
- `owner_type` ENUM('company','department','user') NOT NULL
- `owner_id` BIGINT UNSIGNED NOT NULL
- `title` VARCHAR(200) NOT NULL
- `file_path` VARCHAR(500) NOT NULL -- almacenamiento local o S3
- `mime_type` VARCHAR(120) NOT NULL
- `size_bytes` BIGINT UNSIGNED NOT NULL
- `metadata` JSON NULL -- p.ej., procesado/estado
- `created_at` DATETIME NOT NULL
- `updated_at` DATETIME NOT NULL

Índices:
- `KEY documents_owner_idx (owner_type, owner_id)`

Notas RAG:
- El indexado/embeddings no se almacena en MySQL en MVP; se evaluará `pgvector`, `qdrant`, `chroma` o equivalente en V2.

---

## Consideraciones de seguridad
- No almacenar claves API en la BD. Usar `.env` para configuraciones sensibles.
- `password_hash`: Argon2id con `password_hash()` de PHP.
- Sesiones PHP con cookies `HttpOnly`, `Secure`, `SameSite=Lax|Strict` y tiempo razonable.
- Auditar intentos de login y bloquear tras N fallos (MVP opcional, recomendable).

---

## Notas de migración
- Empezar con `utf8mb4` y `utf8mb4_unicode_ci`.
- Establecer `sql_mode` para impedir valores inválidos.
- Preparar script de migraciones SQL (más adelante) o utilizar un simple versionado incremental (`docs/migrations/`).
