# Ephemeral Staging Verification

## Purpose

This workflow verifies the current Claara code against isolated, synthetic data before instance refactoring. It is loopback-only and leaves no persistent staging resources.

The verifier:

1. Creates a uniquely named MariaDB database and least-privilege credentials.
2. Copies only the production database structure with `mariadb-dump --no-data`; no rows, triggers, routines, or events are copied.
3. Proves that users, messages, and context documents are empty.
4. Inserts one generated `example.invalid` user.
5. Copies code to `/var/tmp`, creates empty storage, and disables AI and Qdrant access.
6. Starts PHP on a random `127.0.0.1:18xxx` port and runs the authenticated HTTP smoke suite.
7. Proves an unknown host returns 421 without creating a cookie and that session files exist only below the staging storage root.
8. Verifies the production table/user/message/document fingerprint is unchanged, then removes the temporary database, database users, HTTP process, sessions, files, and credentials through an exit trap.

## Command

Run on the production server as root:

```bash
bash /home/dvdgp/web/claara.tech/public_html/scripts/ops/verify_ephemeral_staging.sh
```

The script prints diagnostics from the isolated PHP server if a test fails. Generated database and login credentials are never printed.

## Verified Baseline — 2026-08-15

- Run: `20260815T084340Z_3092942`.
- Empty-data gate: zero users, messages, and context documents before synthetic seed.
- Anonymous and authenticated HTTP baseline: 38/38 passed with one expected observation (non-superadmin checks skipped).
- Production fingerprint before and after: `41:3:187:4`.
- Independent cleanup audit: zero temporary databases, users, directories, and listeners.
- Production HTTP baseline after cleanup: 21/21 passed.

## Boundary

This Phase 1 staging path verifies application, session, database, and empty-storage behavior. It deliberately cannot contact production Qdrant or AI providers. Phase 2 isolation tests will add a synthetic, instance-specific RAG resource after the instance configuration contract exists.

The schema is currently derived from production structure because the historical migration registry has drift. Creating a canonical schema baseline remains a prerequisite for automated provisioning of permanent instances.
