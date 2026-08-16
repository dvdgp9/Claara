# Claara HTTP Baseline Smoke Test

## Purpose

`scripts/smoke_http_baseline.php` captures the current externally visible behavior that must survive the instance, session, localization, and module refactors.

Anonymous mode checks:

- Login page rendering and security headers.
- Session cookie issuance and records its current scope as an observation.
- Redirect from the protected app to login.
- Unauthorized API behavior for identity, conversations, and Voices.

Authenticated mode additionally checks:

- Login payload and CSRF token.
- Current-user endpoint.
- Main chat shell.
- Voice catalog.
- Owned/shared conversation response shape.
- Gestures and Account pages.
- Superadmin Users page and feature catalog when the supplied account is a superadmin.

It does not send prompts, invoke AI, create conversations, generate documents, mutate permissions, or log out existing sessions. A successful login updates `last_login_at`, which is the only intended persistent application-data change.

## Commands

Anonymous, read-only:

```bash
php scripts/smoke_http_baseline.php --base-url=https://claara.tech
```

Authenticated production read baseline:

```bash
php scripts/smoke_http_baseline.php \
  --base-url=https://claara.tech \
  --authenticated \
  --allow-production-auth
```

Credentials are read from `SMOKE_EMAIL` and `SMOKE_PASSWORD`, falling back to `ADMIN_EMAIL` and `ADMIN_PASSWORD` in the local `.env`. Secret values are never printed.

For an ephemeral server-side run, `--env-file=/absolute/path/to/.env` can select the environment file without printing or copying its values.

## Safety Guards

- Production authentication requires the explicit `--allow-production-auth` flag.
- Credentials are refused over non-HTTPS except on localhost.
- Redirects are not followed implicitly, so authorization redirects remain testable.
- The temporary cookie jar is removed after the process exits.
- Failure diagnostics report status, content type, size, error code, and JSON keys without dumping private response content.

## Expected Use

Run this suite:

1. Before each refactor milestone.
2. Against staging after deployment.
3. Against production after a controlled release.
4. Together with dedicated negative isolation tests once multiple instances exist.
