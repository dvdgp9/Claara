# Qdrant Network Security

## Production Policy

Qdrant's REST port is published only on the server loopback interface:

```yaml
ports:
  - "127.0.0.1:6333:6333"
```

Claara connects using `QDRANT_HOST=localhost`, so application RAG access remains available while remote clients cannot reach port 6333. The container may listen on all interfaces inside its isolated Docker network; the security boundary verified here is the host port publication on `127.0.0.1`.

## Verification

Run on the production server as root:

```bash
bash scripts/ops/verify_qdrant_network_scope.sh
```

The script verifies Docker port configuration, the host listener, local health, and collection availability. An external probe must independently fail:

```bash
curl --connect-timeout 4 http://91.98.155.109:6333/
```

## Verified Baseline — 2026-08-15

- Container: `ebonia_qdrant`, Qdrant 1.16.3.
- Host binding: `127.0.0.1:6333` only.
- External probe: blocked.
- Application client health: passed through configured `localhost` endpoint.
- Collections preserved: `lex_knowledge_base` has 92 points and `voice_conveniex` has 295 points.
- Existing Docker volume preserved: `public_html_qdrant_data`.
- Claara anonymous HTTP baseline: 21/21 passed after recreation.
- Container startup logs: both collections recovered fully, with no startup errors.
- Rollback archive: `/var/backups/claara/instance-platform/compose-pre-qdrant-loopback-20260815T083900Z.tar.gz`.
- Full database/file/Qdrant recovery baseline remains available in backup `claara-default-20260815T082139Z`.

## Next Security Layer

Loopback binding closes the immediate public exposure. Phase 2 added application support for an instance-scoped `QDRANT_API_KEY` header and immutable collection prefixes. Enabling the server-side key, updating operational snapshot tooling to send it, and choosing collection credentials versus separate services/volumes remain required before provisioning a permanent customer instance.
