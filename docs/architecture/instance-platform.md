# Claara Instance Platform Architecture

Status: Proposed in Planner Phase 0  
Date: 2026-08-15

## Objective

Operate Claara as one maintainable product with independently isolated client instances, beginning with GP Andalucia, while retaining owner-only control over modules, limits, releases, and instance lifecycle.

## Current Production Baseline

- HestiaCP 1.10.2 on Ubuntu 22.04.
- Nginx proxying to Apache and a dedicated PHP 8.3 FPM socket.
- MariaDB 11.4 bound to localhost.
- Self-hosted Qdrant 1.16.3 in Docker.
- Current application is a Git checkout inside the Hestia webroot.
- Application database is `iaiapro_db`, outside Hestia's registered `dvdgp_iaiapro` database.
- Persistent application storage currently lives inside the checkout.
- Current production database is approximately 78 MB; application storage is approximately 17 MB.
- Server has 3.7 GiB RAM, no swap, and approximately 22 GB free disk.

## Target Topology

### Control plane

`admin.claara.tech` is a separate owner-only deployment with:

- Dedicated owner identities and MFA.
- A control database containing instance metadata only.
- Instance registry, domains, branding, locales, modules, limits, release state, deployment jobs, and audit events.
- No tenant conversations, documents, certificate data, Voice knowledge, or user content.

The web process must never have unrestricted `root` or arbitrary shell execution. Infrastructure actions are submitted as validated jobs to a restricted deployment worker. The worker accepts typed, allow-listed operations and rejects arbitrary commands, paths, domains, and identifiers.

### Tenant instances

Each tenant is a separate deployment of the same immutable Claara release artifact. Recommended isolation per tenant:

- Separate Hestia/system user or equivalently isolated Unix identity.
- Separate PHP-FPM pool/process.
- Separate MariaDB database and least-privilege database user.
- Separate persistent storage root.
- Separate session namespace and host-only cookies.
- Separate secrets and connector credentials.
- Separate backup set and restore target.
- Separate RAG collection namespace and collection-scoped credentials; use a separate Qdrant service if collection-scoped enforcement cannot be guaranteed.

GP Andalucia will use `gpand.claara.tech`, Spanish by default, a 20-user limit, and only its contracted modules.

## Instance Resolution

Every HTTP request resolves an immutable `InstanceContext` before authentication or application routing:

1. Normalize and validate the request host against trusted proxy/server configuration.
2. Match the host to a locally cached, signed instance manifest.
3. Reject unknown, suspended, malformed, or mismatched hosts before loading tenant data.
4. Expose only non-secret settings: instance id, slug, branding, locale policy, enabled modules, limits, release id, and support metadata.
5. Load database/storage/RAG secrets exclusively from the instance's local secret environment.

Tenant applications do not receive direct credentials to the control database. They use an instance-scoped control API token to refresh a signed non-secret manifest, retaining the last valid manifest if the control plane is temporarily unavailable.

## Configuration Ownership

### Owner-controlled

- Instance lifecycle and domain.
- Contracted modules and dependencies.
- User/storage/AI limits.
- Default locale and allowed locales.
- Release channel and deployed version.
- AI providers and infrastructure-level settings.
- Support access policy.

### Client-admin controlled

- Tenant users and internal permissions within the contracted limit.
- Voice knowledge documents.
- Certificate templates and operational content.
- Allowed branding/content fields.
- Operational histories and exports.

## Module Architecture

Each module has a stable slug and code manifest containing:

- Name and description translation keys.
- Default state (`disabled` for optional/client-specific modules).
- Dependencies.
- Backend route/API guards.
- Navigation metadata.
- Permissions/capabilities.
- Configuration schema.
- Health checks.

There are two separate authorization layers:

1. **Instance entitlement:** whether the module is enabled for the tenant.
2. **Tenant user permission:** whether the current user may use or administer it.

Both must pass. Disabled modules are rejected by backend guards even if a URL or API endpoint is called directly.

All additive schema migrations are applied consistently to every tenant database, even when an optional module is disabled. This keeps release state uniform and makes later activation predictable. Disabled modules contain no tenant content until configured.

## Internationalization

Use application-owned keyed dictionaries instead of operating-system-dependent gettext catalogs:

- `resources/lang/en/*.php`
- `resources/lang/es/*.php`
- Stable keys such as `instances.modules.activate` rather than English source strings.
- Safe interpolation and pluralization helpers.
- Scoped JSON dictionaries for browser JavaScript.
- Stable API error codes with localized display messages.
- English fallback plus missing-key diagnostics.

Locale precedence:

1. Supported user preference.
2. Instance default locale.
3. English fallback.

Use PHP `intl` for locale-aware dates, numbers, and formatting. AI system prompts can remain internally authored in English, but must explicitly require output in the resolved user/instance language. Document/template language is stored separately from UI language.

## Release and Deployment Model

Replace direct `git pull` in live webroots with versioned artifacts:

```text
/home/<tenant>/claara/
  releases/<release-id>/
  current -> releases/<release-id>
  shared/.env
  shared/storage/
  shared/instance-manifest.json
```

Hestia uses a copied custom template/document root targeting the active release's `public/` directory. Default Hestia templates are never edited because Hestia rebuilds overwrite them.

Release process:

1. Build once from a clean, tested Git commit.
2. Produce a checksumed immutable artifact.
3. Select one target instance.
4. Validate capacity, compatibility, secrets, and migration plan.
5. Back up its database, files, manifest, and RAG data.
6. Extract the artifact into a new release directory.
7. Run database migrations with explicit tracking.
8. Switch `current` atomically.
9. Run health and tenant-isolation smoke tests.
10. Record the result and release id in the control plane.
11. Roll back the symlink and restore data if post-deploy validation fails.

Do not automatically update all customers simultaneously. Promote through staging, default Claara, then selected tenant instances.

## Migration Strategy for Existing Claara

The historical migration registry has drift and duplicate numeric prefixes. Before creating a tenant database:

- Generate and validate a canonical fresh-instance schema baseline.
- Record that baseline on the existing Claara database without replaying historical migrations.
- Use unique, monotonic migration ids after the baseline.
- Test both a clean installation and upgrade from current production schema.

Current Claara becomes the `default` instance first. Its visible behavior remains unchanged while configuration, storage, cookies, and deployment layout move behind the new contracts.

## Background Jobs

Do not use public HTTP endpoints as infrastructure cron targets for new instances. Run queued work through CLI workers or systemd services with:

- Explicit instance identity.
- Per-instance database and storage credentials.
- Timeouts and concurrency limits.
- Structured diagnostic output.
- Retry/dead-letter behavior.

Certificate batch generation should be asynchronous above a small recipient threshold so the user receives visible progress without holding a web request open.

## Initial Capacity Position

The current server has enough measured headroom for the control plane, one test instance, and the initial 20-user GPAND workload if services remain efficient. Before production onboarding:

- Secure Qdrant and measure batch certificate/Voice workloads.
- Add resource monitoring and disk alerts.
- Define a capacity threshold for moving RAG or tenant workloads to another host.
- Consider swap or a server upgrade only after measuring peak memory; do not use it as a substitute for worker limits.

## Explicit Non-goals for the First Client

- Kubernetes or multi-region orchestration.
- A separate Git branch or fork per client.
- Shared tenant tables distinguished only by `tenant_id`.
- Client-controlled infrastructure settings.
- Automatic simultaneous rollout to every instance.
- A full Word/Canva-style document designer.

