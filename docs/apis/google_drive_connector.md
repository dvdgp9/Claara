# Google Drive Connector

Verified against Google docs on 2026-07-09 (Picker overview, Drive scopes guide).

## Architecture

- **Scope**: `drive.file` only (+ `openid email profile` for account identity). Non-sensitive → no Google verification needed. Claara can only read files the user explicitly picks through the Google Picker with our client id.
- **Selected-files model**: no full-drive sync. Import happens at pick time.
- **Pickers live at the point of use**: chat composer (attachment) and Voice Studio documents panel (voice knowledge). `/connectors.php` only manages the account (connect/disconnect/reconnect + counters).
- Refresh tokens are AES-256-GCM encrypted at rest (`connector_tokens`, key `CONNECTOR_TOKEN_ENCRYPTION_KEY`) and never reach the browser; the Picker gets a short-lived access token.

## Env vars (prod .env has real values; local has placeholders)

- `GOOGLE_OAUTH_CLIENT_ID` / `GOOGLE_OAUTH_CLIENT_SECRET` — OAuth Web client. Redirect URI registered in GCP: `https://claara.tech/api/connectors/google/callback.php`.
- `GOOGLE_PICKER_API_KEY` — browser key, restricted to `https://claara.tech/*` + Picker API only.
- `CONNECTOR_TOKEN_ENCRYPTION_KEY` — base64 32-byte key (different per environment).

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/connectors/google/start.php` | GET | Begins OAuth (session `state`, `access_type=offline&prompt=consent`) |
| `/api/connectors/google/callback.php` | GET | Exchanges code, verifies granted scopes include `drive.file`, upserts account + tokens, redirects to `/connectors.php?connect=...` |
| `/api/connectors/google/disconnect.php` | POST (CSRF) | Revokes at Google (best effort), deletes tokens, marks disconnected |
| `/api/connectors/google/picker-token.php` | GET | `{access_token, expires_in, api_key, app_id}` for the Picker; 409 `not_connected` if no account |
| `/api/connectors/google/import-to-chat.php` | POST (CSRF) | `{drive_file_id, conversation_id?}` → chat attachment (mirrors `files/upload.php` response) |
| `/api/admin/voices/documents/import-drive.php` | POST (CSRF) | `?slug=<voice>` + `{drive_file_id, folder_id?, description?}` → voice document (mirrors `documents/upload.php`) |

## Server classes (`src/Connectors/`, registered in bootstrap.php — no autoloader)

- `GoogleDriveProvider` — auth URL / code exchange / refresh / userinfo / revoke.
- `GoogleTokenService::freshAccessToken(accountId)` — refreshes when <5 min left; `invalid_grant` → `connector_accounts.status='error'` (UI then offers Reconnect).
- `GoogleDriveImporter::fetchToTemp(accountId, fileId, target)` — target `'chat'|'voice'`; downloads (`files.get?alt=media`) or exports Google-native formats; enforces per-target mime allowlists + 30MB cap (aborts mid-download).
- `ConnectorImportException` — UI-safe error code+message.

## Format rules

| Source | Chat target | Voice target |
| --- | --- | --- |
| Google Doc / Slides | export PDF | export PDF |
| Google Sheet | export XLSX | export PDF |
| PDF, images, CSV, XLS(X) | direct | PDF only |
| TXT / MD | not allowed | direct |

## Frontend

- `public/assets/js/drive-picker.js` — shared `ClaaraDrivePicker.open({mimeTypes, title, onPicked})`. Handles token fetch, "Connect Google Drive" prompt (auto-resumes the picker when the user returns from connecting in another tab), lazy `gapi` load.
- Chat: attach button opens a source menu (device/Drive); Drive files import at pick time with chip states importing/ready/error; send awaits in-flight imports (`app.php` + `.attach-menu` styles).
- Voice Studio: "Google Drive" button next to Upload; imports into the currently selected folder; document still needs the normal Process step for RAG.

## Gotchas

- Google's consent screen shows `drive.file` as an **optional checkbox** — callback verifies the granted scope and the UI tells the user to tick it and retry.
- The Picker `app_id` is the GCP project number = numeric prefix of the OAuth client id.
- Any page including `includes/head.php` must set `$csrfToken` or POSTs fail (see Lessons).
