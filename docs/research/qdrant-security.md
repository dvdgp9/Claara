# Qdrant Security Notes

Date reviewed: 2026-08-15  
Installed production version observed: 1.16.3  
Official documentation:

- https://qdrant.tech/documentation/operations/security/
- https://qdrant.tech/documentation/security/

## Official Guidance Relevant to Claara

- Self-hosted Qdrant is not secure by default.
- Production deployments should use authentication, network binding, and audit controls.
- Qdrant supports admin, read-only, and granular collection access credentials.
- A Docker-published port can be bound to `127.0.0.1` instead of all network interfaces.
- TLS is required when credentials traverse an untrusted network.

## Production Finding

The current Docker container publishes `6333` on `0.0.0.0` and the endpoint returned HTTP 200 from an external probe without authentication. Current application configuration connects through `localhost`.

## Claara Decision

- Close public network access before any tenant rollout.
- Add optional authenticated Qdrant requests to the Claara client before enabling server-side authentication.
- Bind the existing service to loopback/private networking.
- Use immutable tenant-prefixed collections plus collection-scoped credentials.
- If collection-scoped enforcement cannot be reliably implemented in the installed deployment, use a separate Qdrant service/volume for that tenant.
- Add collection/volume snapshots to the per-instance backup and restore procedure.

## Safe Change Sequence for Executor Phase

1. Back up/snapshot the current Qdrant data.
2. Add application support for a Qdrant credential without requiring it yet.
3. Test authenticated client behavior in staging.
4. Configure authentication and loopback/private binding.
5. Restart Qdrant during an announced maintenance window.
6. Run Voice/RAG smoke tests.
7. Confirm external port access fails.
8. Verify restore from the new backup.

