# Session Cookie Isolation

## Policy

Claara authentication cookies are scoped to the exact request host:

- Session name: `claara_session_<12-character host hash>`.
- Remember-me name: `claara_remember_<12-character host hash>`.
- No `Domain` attribute is emitted, making both cookies host-only.
- `Path=/`, `Secure` in HTTPS environments, `HttpOnly`, and `SameSite=Lax` remain enforced.
- Invalid `Host` values fall back to the validated host from `APP_URL`.

For example, `claara.tech`, `admin.claara.tech`, and `gpand.claara.tech` receive different cookie names and browsers will not send one host's credentials to another host.

## Legacy Migration

The first request after deployment expires the former `claara_session` and `claara_remember` cookies in both host-only and `Domain=claara.tech` forms. Existing users therefore sign in once again. The old cookie names are never accepted for authentication after this migration.

## Verification

Run the deterministic configuration checks:

```bash
php scripts/test_cookie_scope.php
```

Run the public HTTP characterization suite:

```bash
php scripts/smoke_http_baseline.php --base-url=https://claara.tech
```

The HTTP suite requires a namespaced session cookie, rejects any `Domain` attribute, and verifies `Secure`, `HttpOnly`, and `SameSite=Lax`.

## Production Baseline — 2026-08-15

- Cookie policy tests: 11/11 passed.
- Local two-host HTTP check: `claara.tech` and `gpand.claara.tech` emitted distinct namespaced cookies with no `Domain` attribute.
- Production anonymous HTTP baseline: 21/21 passed.
- Legacy-cookie response check: both legacy names were expired at host and parent-domain scope; only the namespaced session cookie was created.
- Existing permission smoke suites: 17/17 and 14/14 passed in production, including transaction rollback checks.
- No new PHP errors were recorded during deployment verification.
- Rollback archive: `/var/backups/claara/instance-platform/code-pre-session-20260815T083200Z.tar.gz` (root-only).

Authenticated browser verification is required before this task is accepted.
