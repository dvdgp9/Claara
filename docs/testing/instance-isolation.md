# Cross-Instance Isolation Verification

## Coverage

`scripts/ops/verify_instance_isolation.sh` creates two temporary synthetic instances and attempts to cross their boundaries. It verifies:

- Separate MariaDB databases and least-privilege users.
- Alpha credentials cannot select beta and beta credentials cannot select alpha.
- Separate manifests, environment prefixes, storage roots, and session directories.
- Separate host-only cookie names and identities.
- An authenticated alpha cookie receives 401 from beta.
- Qdrant logical collection names receive different immutable instance prefixes.
- Both synthetic RAG collections contain only their own point.
- Both instances run the same release while Alpha enables Lead Finder and Beta does not.
- Both databases retain the same normal-user Lead Finder grant, proving instance entitlement wins over stored user permissions.
- Anonymous requests receive the same authentication boundary without disclosing module state.
- Alpha normal-user and superadmin page/API access succeeds; its mock-provider job completes without external AI.
- Beta hides Lead Finder and denies its page, history/search APIs, normal user, superadmin, and directly queued worker execution with `feature_unavailable`.
- Production database and production RAG collection set are unchanged.
- Exit-trap cleanup removes every temporary process, database, user, directory, session, and collection.

No customer rows, files, prompts, or AI provider calls are used.

## Command

```bash
sudo bash /home/dvdgp/web/claara.tech/public_html/scripts/ops/verify_instance_isolation.sh
```

## Verified Production Baseline — 2026-08-16

- Runtime contract tests: 30/30 passed.
- Cookie policy tests: 11/11 passed.
- Ephemeral authenticated staging: 38/38 passed.
- Cross-instance module contract: 15/15 passed.
- Cross-instance drill: passed for database grants, storage, RAG prefixes, cookies, sessions, identities, module discovery, direct pages, APIs, role boundaries, and queued work.
- Module registry, entitlement, core-route, Gesture-route, and state-UI suites: 48/48, 22/22, 42/42, 44/44, and 45/45 passed.
- Production HTTP baseline: 21/21 passed.
- Default instance resolved as `default` on `claara.tech`.
- Default RAG collection set remained unchanged.
- Independent cleanup audit found zero temporary databases, users, collections, directories, or listeners.

This drill uses schema-only synthetic databases, `.invalid` identities, mock Lead Finder results, isolated storage/sessions, and uniquely prefixed temporary Qdrant collections. It does not copy customer data or call an AI provider.
