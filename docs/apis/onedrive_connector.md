# OneDrive Connector

Verified against Microsoft Graph docs on 2026-07-09. Shares the whole connector pipeline with Google Drive (see `google_drive_connector.md`): same tables, crypto, import targets and UX pattern.

## Differences vs Google Drive

- **Scope**: delegated `Files.Read` (+ `openid profile email offline_access`) — Microsoft has no per-file scope. Authority `/common` (personal + work accounts). Azure app registered in the Ebone tenant, multi-tenant + personal accounts.
- **Claara-native picker** (`public/assets/js/onedrive-picker.js`, `.cfp-*` styles): modal with folder navigation/breadcrumb backed by the server-side `browse.php` — the Microsoft token NEVER reaches the browser.
- **Conversion**: Graph `/content?format=pdf` (302 → preauthenticated URL, cURL drops the auth header cross-host, which is expected). Word/PowerPoint/RTF/ODT/EML/TIFF → PDF for both targets; Excel → native for chat, PDF for voice.
- **Disconnect**: Microsoft has no OAuth revocation endpoint — tokens are deleted locally; users can also revoke Claara in their Microsoft account app permissions.
- Refresh tokens rotate on every refresh; `ConnectorTokensRepo::saveForAccount` COALESCEs so the newest one is always stored.

## Env vars

- `MS_OAUTH_CLIENT_ID` / `MS_OAUTH_CLIENT_SECRET` — Azure app registration (redirect URI `https://claara.tech/api/connectors/onedrive/callback.php`, platform Web). Secret expires 2028 — re-issue in Azure Portal → Certificates & secrets before then.

## Endpoints

| Endpoint | Method | Purpose |
| --- | --- | --- |
| `/api/connectors/onedrive/start.php` | GET | Begins OAuth |
| `/api/connectors/onedrive/callback.php` | GET | Exchange + verify Files.Read + store account/tokens |
| `/api/connectors/onedrive/disconnect.php` | POST (CSRF) | Local token deletion + mark disconnected |
| `/api/connectors/onedrive/browse.php` | GET | Folder listing for the picker (`?item_id=` optional; 409 `not_connected`) |
| `/api/connectors/onedrive/import-to-chat.php` | POST (CSRF) | `{item_id, conversation_id?}` → chat attachment |
| `/api/admin/voices/documents/import-onedrive.php` | POST (CSRF) | `?slug=` + `{item_id, folder_id?, description?}` → voice document |

## Server classes

- `MicrosoftOneDriveProvider` — OAuth v2.0 (`login.microsoftonline.com/common`), profile via Graph `/me`.
- `ConnectorTokenService` — generic (provider-injected) token lifecycle; `GoogleTokenService` is now a thin wrapper.
- `OneDriveImporter::fetchToTemp(accountId, itemId, target)` — extension-based allowlists + PDF conversion + 30MB cap.
