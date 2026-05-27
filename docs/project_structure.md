# Claara - Estructura de Proyecto (Propuesta MVP)

Objetivo: estructura simple y escalable sin dependencias externas (sin Composer en MVP). El front usa Tailwind CDN y JS vanilla. El backend expone endpoints PHP simples en `/api/*` con sesiones.

## Árbol propuesto

```
/ (raíz del repo)
├─ public/                  # Webroot (público). index.php principal del sitio
│  ├─ index.php             # Layout base + UI MVP (chat + sidebar)
│  └─ assets/
│     ├─ css/               # Estilos propios (si los hay)
│     └─ js/                # JS modular para UI/Chat
├─ api/                     # Endpoints HTTP (PHP plano)
│  ├─ auth/
│  │  ├─ login.php
│  │  └─ logout.php
│  ├─ chat.php              # Endpoint de conversación con Gemini
│  └─ conversations/
│     ├─ index.php          # Listar/crear conversaciones
│     └─ messages.php       # Listar/crear mensajes
├─ src/                     # Lógica de dominio/reutilizable (sin framework)
│  ├─ App/                  # Bootstrap, helpers, seguridad
│  │  ├─ Config.php
│  │  ├─ Env.php            # Carga de .env (simple, sin librerías externas)
│  │  ├─ DB.php             # Conexión MySQL (PDO)
│  │  ├─ Session.php        # Gestión de sesión y CSRF
│  │  └─ Response.php       # Helpers JSON y errores
│  ├─ Auth/
│  │  ├─ AuthService.php    # Login, logout, verificación
│  │  └─ Passwords.php      # Hash/verify (Argon2id)
│  ├─ Chat/
│  │  ├─ Provider.php       # Interfaz proveedor LLM (Gemini por defecto)
│  │  ├─ GeminiClient.php   # Cliente Gemini (1.5 Flash)
│  │  └─ ChatService.php    # Orquestación chat + persistencia
│  ├─ Repos/
│  │  ├─ UsersRepo.php
│  │  ├─ ConversationsRepo.php
│  │  └─ MessagesRepo.php
│  └─ Util/
│     └─ Validator.php
├─ config/
│  ├─ app.php               # Ajustes de app (CSP/HSTS flags, etc.)
│  └─ routes.md             # Referencia simple de rutas (documentación)
├─ storage/                 # Futuros uploads y logs (excluir de público)
├─ docs/
│  ├─ db_schema.md          # Esquema de BD (fuente de verdad)
│  ├─ project_structure.md  # Este documento
│  └─ api_contract.md       # Contratos de endpoints
├─ .env.example             # Variables de entorno (plantilla)
├─ .gitignore               # Ignora .env, storage, etc.
└─ README.md
```

Notas:
- El `webroot` será `public/`. El `index.php` actual en raíz se migrará a `public/index.php` durante el scaffolding. Mientras tanto, se mantiene para no romper nada.
- Endpoints PHP simples en `/api/*` para claridad y facilidad de despliegue.

## Convenciones
- PHP 8.2+.
- Estricto con tipos donde aplique.
- Respuestas JSON en API con `Content-Type: application/json; charset=utf-8`.
- Sesión: cookie `HttpOnly`, `Secure`, `SameSite=Lax`, nombre `claara_session`.
- CSRF: token por sesión; header `X-CSRF-Token` en peticiones mutadoras (login, chat, etc.).
- Errores JSON: `{ "error": { "code":"...", "message":"..." } }`.
- Timezone: UTC (convertir en UI si se requiere).

## Variables de entorno (`.env`)
- `APP_ENV=local|production`
- `APP_DEBUG=0|1`
- `APP_URL=https://claara.tech` (o http://localhost:8000 en dev)
- `DB_HOST=localhost`
- `DB_PORT=3306`
- `DB_NAME=claara`
- `DB_USER=...`
- `DB_PASS=...`
- `GEMINI_API_KEY=...` (no se versiona)
- `GEMINI_MODEL=gemini-1.5-flash`

## Seguridad HTTP (dominio claara.tech)
- HTTPS obligatorio (HSTS). 
- CSP básica (ajustar según CDN):
  - `default-src 'self'; script-src 'self' https://cdn.tailwindcss.com; style-src 'self' 'unsafe-inline'; connect-src 'self'; img-src 'self' data:`
- Ajustar CSP si se añaden más orígenes (p. ej., fonts).

## Frontend (UI MVP)
- Tailwind via CDN para rapidez (migrable a build más adelante).
- JS modular (ESM) sin framework para chat y sidebar:
  - `assets/js/chat.js`: envía/recibe mensajes, renderiza lista.
  - `assets/js/sidebar.js`: CRUD de conversaciones y folders (MVP básico).

## Backend (MVP)
- `ChatService`: guarda mensajes en BD y llama a `GeminiClient`.
- `GeminiClient`: request→response (sin streaming) al modelo configurado.
- Repositorios con PDO y consultas preparadas.

## Despliegue
- `.env` no se versiona.
- `storage/` fuera de público (o protegido por servidor web).
- Logs rotados (si se añaden).
