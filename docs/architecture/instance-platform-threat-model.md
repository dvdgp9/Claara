# Claara Instance Platform Threat Model

Status: Planner Phase 0 baseline  
Date: 2026-08-15

## Protected Assets

- Tenant identities, roles, conversations, files, certificates, and Voice knowledge.
- PRL information that may contain personal or special-category data.
- AI, connector, database, storage, deployment, and signing credentials.
- Owner control-plane identities and audit records.
- Release artifacts, backups, and RAG vectors/payloads.

## Trust Boundaries

- Internet to tenant application.
- Internet to owner control plane.
- Tenant application to its database/storage/RAG services.
- Tenant application to AI and connector subprocessors.
- Control plane to deployment worker.
- Deployment worker to Hestia, filesystem, MariaDB, and service manager.
- Release pipeline to production instances.

## Primary Risks and Required Controls

### Cross-instance data access

Risks: host spoofing, wrong database credentials, shared cookie scope, insecure object references, shared storage paths, or querying the wrong RAG collection.

Controls:

- Fail-closed host-to-instance resolution.
- Host-only, instance-specific session and remember cookies.
- Dedicated database/user and storage root per tenant.
- Immutable instance identity passed through every worker/job.
- Collection-scoped RAG credentials or separate RAG service.
- Automated negative tests that attempt cross-instance reads and writes.

### Module entitlement bypass

Risk: a hidden module remains accessible through a direct page/API call.

Controls:

- Central backend module guard.
- Instance entitlement checked before tenant user permission.
- Denied direct-route/API tests for every optional module.
- No client-controlled entitlement fields.

### Control-plane compromise

Risk: an attacker activates modules, changes limits, deploys code, or accesses all tenants.

Controls:

- Separate deployment and database with no tenant content.
- Owner-only accounts, MFA, short sessions, host-only cookies, rate limiting, and audit logs.
- No arbitrary shell or unrestricted sudo from the web process.
- Typed deployment jobs consumed by a restricted worker.
- Two-step confirmation for destructive or cross-tenant actions.

### Deployment worker command injection

Risk: crafted domain, slug, version, or path becomes a shell command or broad filesystem target.

Controls:

- Strict allowlists and canonical identifiers.
- No user-provided shell fragments.
- Fixed command templates and argument arrays.
- Explicit target resolution before mutation.
- Least-privilege service identity and narrow sudo rules.
- Immutable release checksums and signed job payloads.

### Public or unauthenticated infrastructure

Current finding: Qdrant 1.16.3 is reachable from the public Internet on port 6333 without authentication. Qdrant's official guidance states self-hosted deployments are insecure by default unless network binding and authentication are configured.

Controls before any tenant rollout:

- Update the application client to support Qdrant authentication.
- Bind Qdrant to loopback/private networking.
- Enable API-key or granular collection access.
- Validate from an external network that the public port is closed.
- Back up and restore the Qdrant volume/collections.

### Backup loss or mixed restores

Current finding: Hestia's automatic backup lists `dvdgp_iaiapro`, while production Claara uses `iaiapro_db`. No current recurring backup of `iaiapro_db` or the Qdrant Docker volume was found; the latest explicit database backup located is from 2026-06-25.

Controls:

- Per-instance scheduled database, files, manifest, and RAG backups.
- Encryption, retention, checksum, and off-host copy.
- Restore into an isolated target; never overwrite production for testing.
- Quarterly restore drills and audited results.

### Secrets and logs

Risks: credentials in Git, webroot, diagnostics, backups, or support logs.

Controls:

- Secrets outside release artifacts and webroot.
- Per-instance credentials and rotation.
- Redacted structured logs with no document contents by default.
- Encrypted backups and restricted access.
- Never expose secret values in the owner panel.

### AI/subprocessor disclosure

Risks: unnecessary personal data sent to providers, provider retention/training, or international transfer without appropriate terms.

Controls:

- Data minimization and clear tenant configuration.
- Contract/subprocessor review and documented processing terms.
- Provider settings that prohibit model training where available.
- Avoid health data unless strictly necessary.
- Retention/deletion workflow for prompts, documents, and outputs.

### RAG prompt injection and poisoned knowledge

Risks: uploaded documents instruct the model to ignore policy, reveal other information, or create unsafe certificate text.

Controls:

- Treat retrieved text as untrusted reference data.
- Separate system instructions from retrieved content.
- Source citations and human validation for regulated outputs.
- File-type, size, malware, and content validation.
- Audit who uploaded, processed, published, or deleted knowledge.

### Support access

Risk: owner/support staff silently access tenant content.

Controls:

- No default impersonation.
- Time-limited, reasoned, audited support access requiring tenant approval where feasible.
- Owner control plane shows operational metadata without content.

## Phase 1 Security Gate

The instance refactor must not begin until the following are demonstrated:

- External Qdrant access is closed and authenticated internally.
- Current Claara database/files/RAG are backed up and restored successfully into an isolated target.
- Host-only cookies prevent cross-subdomain session reuse.
- Current core smoke tests pass before and after the safety changes.

