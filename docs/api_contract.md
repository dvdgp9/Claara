# Claara - Contratos de API (MVP)

Autenticación por sesión. Las respuestas son JSON UTF-8. Errores con `{ "error": { "code", "message" } }`.

## Autenticación

### POST /api/auth/login
- Body (JSON):
```json
{ "email": "user@empresa.com", "password": "..." }
```
- Respuestas:
  - 200 OK:
  ```json
  { "user": { "id": 1, "email": "user@...", "first_name": "...", "last_name": "...", "roles": ["user"] }, "csrf_token": "..." }
  ```
  - 401 Unauthorized (credenciales inválidas)
  - 423 Locked (usuario deshabilitado)

### POST /api/auth/logout
- Headers: `X-CSRF-Token: <token>`
- Respuestas: 204 No Content

## Conversaciones y Chat

### GET /api/conversations
- Lista conversaciones del usuario autenticado (paginación mínima `?page` `?limit`).
- Respuesta 200 OK:
```json
{ "items": [ { "id": 10, "title": "...", "updated_at": "..." } ], "page": 1, "total": 12 }
```

### POST /api/conversations
- Crea conversación vacía (opcionalmente con primeros datos).
- Body (JSON):
```json
{ "title": "Opcional", "folder_id": null, "voice_id": null }
```
- Respuesta 201 Created:
```json
{ "id": 11, "title": "..." }
```

### GET /api/conversations/messages?conversation_id=ID
- Lista mensajes (orden ascendente).
- Respuesta 200 OK:
```json
{ "items": [ { "id": 1, "role": "user", "content": "..." }, { "id": 2, "role": "assistant", "content": "..." } ] }
```

### POST /api/chat
- Envía un mensaje y recibe respuesta del asistente. Crea la conversación si no existe.
- Headers: `X-CSRF-Token: <token>`
- Body (JSON):
```json
{
  "conversation_id": 11,
  "message": "texto del usuario",
  "voice_id": null,
  "gesture_id": null,
  "options": { "temperature": 0.7 }
}
```
- Respuesta 200 OK:
```json
{
  "conversation": { "id": 11, "title": "..." },
  "message": { "id": 25, "role": "assistant", "content": "respuesta...", "model": "gemini-1.5-flash" }
}
```
- Errores comunes:
  - 400 Bad Request (parámetros)
  - 401 Unauthorized (sin sesión)
  - 429 Too Many Requests (rate limit futuro)
  - 502 Bad Gateway (error proveedor LLM)

## Notas de seguridad
- CSRF obligatorio en peticiones POST/PUT/DELETE (token por sesión).
- Validación y sanitización server-side.
- Logs sin datos sensibles (ni API keys, ni contraseñas).

## Versionado y compatibilidad
- Todos los endpoints son MVP; pueden ampliarse con `v=1` en query si se requiere en futuro.
