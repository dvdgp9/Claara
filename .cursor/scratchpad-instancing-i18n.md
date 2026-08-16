# Claara Multi-instance & Internationalization Scratchpad

## Background and Motivation

Claara needs to support dedicated client platforms without creating divergent copies of the application. The first planned client instance is GP Andalucia, expected at `gpand.claara.tech`, with Spanish as its default language, up to 20 users, a specialized PRL Voice, and a certificate/document-generation Gesture.

The product owner must retain exclusive control over instance creation, enabled modules, limits, versions, and infrastructure-level configuration. Client administrators should only manage their own users, knowledge, templates, and operational content.

The target product model is:

- One maintained Claara codebase and versioned release artifact.
- Multiple independently deployed and isolated client instances.
- A separate owner-only control plane at `admin.claara.tech`.
- Runtime English and Spanish localization, configurable per instance and optionally per user.
- Client-specific functionality implemented as reusable modules, disabled by default and enabled only for selected instances.

The user has confirmed that Codex will perform the implementation, server changes, deployments, backups, migrations, and verification throughout this initiative. The user should not need to run server commands. Any missing credential, irreversible business decision, or scope-changing choice must be raised before the affected action.

## Key Challenges and Analysis

### Control plane versus client administration

`admin.claara.tech` is the owner control plane. Only the Claara owner can create/suspend instances, assign domains, enable modules, set quotas, select release versions, and review owner-level audit logs.

Each client instance has its own administration area for users, roles, Voice documents, templates, branding fields permitted by the owner, and operational history. Client admins cannot activate modules, alter infrastructure, change providers, or increase contracted limits.

### Isolation model

Sharing the code artifact must not mean sharing customer data. Each client instance should have its own:

- Application process or container.
- Database and database credentials.
- File/document storage namespace and credentials.
- Vector/RAG store or strongly isolated collection namespace.
- Session namespace and host-only cookies.
- Secrets, backups, logs, and restore process.

The control-plane database stores tenant metadata, feature assignments, limits, release state, and audit events; it does not store client documents or conversation content.

### Module model

Features are registered modules such as `chat`, `voices`, `voice_prl`, `certificate_editor`, and `shared_conversations`. They are disabled by default and enabled per instance by the owner.

Every enabled-feature check must be enforced in both UI discovery and backend authorization. Hiding navigation alone is not security. Modules should register their routes, permissions, configuration schema, migrations, and navigation metadata without hard-coded `if client == gpand` branches.

### Internationalization

The current translation extraction pack is an inventory, not a runtime translation system. The target is a translator service with keyed English/Spanish dictionaries, dynamic HTML locale, localized PHP/JavaScript/API messages, instance default locale, optional user preference, and prompts that follow the resolved locale.

Migration should begin with shared shell/auth/account/chat/Voice surfaces and the GP Andalucia modules, then progressively cover the remaining Gestures and administration pages. Missing translations must fall back safely to English and be detectable in tests or diagnostics.

### Updates and releases

All instances run immutable, tagged Claara releases from the same repository. A release is tested once and promoted per instance. Updating an instance requires a backup, compatible database migration, deployment, health checks, and rollback support. Client customization belongs in configuration/data/modules, never a permanent client branch.

### Security findings to address before subdomains

Current session handling derives the base domain and can issue cookies for `claara.tech`, which makes them available to subdomains. Dedicated instances require host-only cookies and instance-specific session/remember-cookie names so authentication cannot cross instance boundaries.

The implementation must also cover owner MFA, least-privilege database/storage credentials, audit logging, encrypted transport/backups, retention/deletion rules, provider/subprocessor review, and automated cross-instance isolation tests.

### Simplicity boundary

Do not introduce Kubernetes or a distributed control system for the first client. A versioned application image or release directory, per-instance environment configuration, independent data services, and a small owner control plane are sufficient. Preserve a path to automation without building infrastructure for hypothetical scale.

### Phase 0 production inventory findings

- Production is HestiaCP 1.10.2 on Ubuntu 22.04, using Nginx → Apache → PHP 8.3 FPM, MariaDB 11.4, and Qdrant 1.16.3 in Docker.
- Claara is currently a Git checkout inside the live webroot; persistent storage is also inside that checkout. Target releases must move runtime data outside immutable code artifacts.
- Production uses database `iaiapro_db` (~78 MB), but Hestia registers/backs up `dvdgp_iaiapro`. The automatic Hestia backup therefore cannot be treated as a verified backup of Claara's live database.
- No recurring backup of `iaiapro_db` or the Qdrant Docker volume was found. The last explicit `iaiapro_db` backup found is dated 2026-06-25.
- Qdrant is published on `0.0.0.0:6333` and returned HTTP 200 to an unauthenticated external probe. This must be remediated at the Phase 1 gate before tenant work.
- Session cookies currently use the base `claara.tech` domain and a common name. They must become host-only and instance-specific before adding subdomains.
- Production has 3.7 GiB RAM, no swap, ~22 GB free disk; current Qdrant usage is low (~26 MB), so one test instance and initial GPAND workload are feasible subject to monitored load tests.
- Historical database migrations have registry drift and duplicate numeric prefixes. New instances require a tested canonical schema baseline and monotonic migration tracking.
- The existing root-owned/live-checkout permission pattern makes direct `git pull` fragile. Use checksumed release artifacts and atomic target-specific deployment instead.
- Current cron processing references an HTTP job endpoint under `iaiapro.com`; new instance workers must use explicit CLI/systemd execution with instance identity.

### UX and interaction standard

The owner control plane and every client-facing surface must feel like one modern Claara product, not a collection of technical administration forms. Continue with the existing PHP/vanilla-JS architecture unless a separate migration is explicitly approved; componentize shared UI behavior and keep CSS in `public/assets/css/styles.css`.

Required UX principles:

- Use progressive disclosure: instance list first, focused instance workspace second, advanced infrastructure details only when needed.
- Prefer compact tables, status chips, drawers, tabs, inline validation, and contextual actions over repeated generic cards.
- Every asynchronous surface needs purpose-shaped loading skeletons, clear empty states, recoverable inline errors, and truthful success feedback.
- Destructive or high-impact actions such as suspension, deletion, version rollback, or resource reset require explicit confirmation and an audit entry.
- Module activation must explain impact, dependencies, and current rollout state; never present a switch that silently fails or enables only the UI.
- Keep one restrained accent color, consistent neutral surfaces, high-quality sans-serif typography, responsive single-column mobile fallbacks, and accessible focus/keyboard states.
- Motion should clarify state changes and hierarchy using transform/opacity; avoid decorative animation that slows daily administrative work.
- Spanish copy must be natural product copy, not a literal machine translation of English labels.

## Delivery Phases and Approval Gates

### Phase 0 — Architecture, inventory, and UX definition

- Inspect local and production topology without mutations: web server, PHP/runtime, databases, storage, Qdrant, cron/jobs, backups, certificates, and deployment method.
- Record architecture decisions for instance resolution, control-plane metadata, isolation, module registration, locale resolution, versioning, backup, and rollback.
- Define the information architecture and low-fidelity flows for the owner panel and client admin boundaries.
- Produce an initial threat model and data-flow inventory, including AI providers and possible PRL personal data.
- Gate: owner approves the architecture, owner-panel flows, and first implementation milestone before code changes begin.

### Phase 1 — Safety baseline and staging

- Add characterization/smoke tests for current authentication, chat, Voices, permissions, and navigation.
- Create a staging path that does not use production customer data.
- Verify automated backup and restore on staging.
- Correct cross-subdomain session behavior with host-only, instance-specific cookies.
- Gate: existing Claara still passes smoke tests; session-isolation and restore tests pass before instance refactoring.

### Phase 2 — Instance foundation

- Add a validated instance context resolved from the request host.
- Centralize instance branding, locale, module assignments, limits, and resource references.
- Add independent database, storage, RAG, session, secret, and backup configuration per instance.
- Convert current Claara into the `default` instance without changing its visible behavior.
- Create an isolated internal test instance.
- Gate: automated tests prove that data, sessions, files, and RAG results cannot cross instances.

### Phase 3 — Runtime internationalization

- Implement locale resolution, translation dictionaries, safe fallback, and missing-key diagnostics.
- Localize the shared shell, authentication, account, chat, Voices, errors, JavaScript states, and AI prompt-language behavior.
- Add Spanish and preserve English as a complete supported locale.
- Add instance default locale and optional user preference.
- Gate: default Claara works in English and the test instance works in Spanish across desktop and mobile without mixed-language critical flows.

### Phase 4 — Modules and feature enforcement

- Add a central module registry with metadata, dependencies, permissions, navigation, and configuration schema.
- Enforce module state in backend routes/APIs/jobs as well as navigation.
- Add module-state and dependency tests, with disabled-by-default behavior.
- Gate: a test module can be activated for one instance and is inaccessible from every other instance, including direct URL/API attempts.

### Phase 5 — Owner control plane

- Create `admin.claara.tech` with owner-only authentication and MFA.
- Build instance list, instance workspace, domain/locale/branding controls, module activation, quotas, version state, health, and audit history.
- Provide loading, empty, error, pending-deployment, success, and rollback states for every operational action.
- Gate: the owner can create/configure a test instance and activate a module without direct database or server editing; the client admin cannot access control-plane operations.

### Phase 6 — Versioned deployment and update automation

- Produce one versioned release artifact from the shared repository.
- Automate per-instance backup, migration preflight, deployment, health checks, version recording, and rollback.
- Support staged promotion rather than updating every production instance simultaneously.
- Gate: the same release is promoted to default and test instances independently, and a deliberate failed health check rolls the test instance back safely.

### Phase 7 — GP Andalucia provisioning and contracted modules

- Provision `gpand.claara.tech` with independent resources, Spanish default, GP Andalucia branding, and a 20-user limit.
- Implement and enable only the contracted PRL Voice and certificate/document editor modules.
- Configure client-admin boundaries, templates, knowledge, support, quotas, and operational history.
- Gate: agreed real-world Voice and certificate-generation acceptance cases pass with GP Andalucia data, and no GPAND-only capability is reachable elsewhere.

### Phase 8 — Production hardening and handover

- Complete security, privacy, accessibility, responsive UX, performance, audit, retention/deletion, backup/restore, and incident checks.
- Document architecture, deployment, module creation, localization, support, and recovery procedures.
- Establish monitoring and a safe update cadence.
- Gate: owner accepts final UX and operations; production checks pass; recovery and rollback have been demonstrated.

## High-level Task Breakdown

1. [ ] Write architecture decision records and acceptance criteria.
   - Define control-plane/client responsibilities, isolation boundary, module lifecycle, locale resolution, release promotion, backup, and rollback.
   - Success: decisions are explicit enough that implementation does not require client-specific branches or shared customer data.

2. [x] Add automated characterization tests around the current Claara installation.
   - Cover authentication/session behavior, core navigation, chat, Voice access, feature access, and migrations before refactoring.
   - Success: the existing default instance has a repeatable smoke suite that catches regressions.
   - Completed and owner-verified 2026-08-15.

3. [ ] Introduce an instance context and configuration contract.
   - Resolve host to instance identity; expose branding, default locale, enabled modules, limits, storage namespace, and release metadata.
   - Success: every request has exactly one validated instance context and fails closed for unknown/suspended domains.

4. [ ] Isolate sessions and instance resources.
   - Use host-only, instance-specific cookies and separate database/storage/RAG/secrets configuration.
   - Success: sessions and data created in one test instance cannot be read or used in another.

5. [ ] Implement the English/Spanish runtime localization foundation.
   - Add translator service, dictionaries, locale resolver, JS/API translation mechanism, dynamic HTML locale, and prompt-language rules.
   - Success: the same core surfaces render correctly in English or Spanish from instance configuration, with predictable fallback behavior.

6. [ ] Implement the module registry and backend feature gate.
   - Register modules centrally and enforce instance enablement in navigation, pages, APIs, and jobs.
   - Success: a disabled module is invisible and returns a denied/not-found response even when its URL is called directly.

7. [ ] Build the owner-only control plane at `admin.claara.tech`.
   - Manage instances, domains, locales, branding, modules, quotas, versions, status, and audit history.
   - Success: only the owner can create an instance or change contracted/system capabilities; every change is audited.

8. [ ] Convert the current Claara deployment into the `default` instance.
   - Move environment-specific behavior into instance configuration without changing current user-visible functionality.
   - Success: existing Claara behavior and data remain intact under the new architecture.

9. [ ] Create and validate an isolated test instance.
   - Use separate data resources, Spanish locale, alternate branding, and one test-only module assignment.
   - Success: isolation, localization, module gating, update, backup, restore, and rollback tests pass end to end.

10. [ ] Automate versioned per-instance deployment.
    - Build/test release, back up target, run compatible migrations, deploy, smoke test, record version, and rollback on failure.
    - Success: the same release can be promoted independently to default and test instances without manual code divergence.

11. [ ] Provision GP Andalucia.
    - Create `gpand.claara.tech`, independent resources, Spanish default, 20-user limit, GP Andalucia branding, and client-admin permissions.
    - Success: GP Andalucia is isolated and only sees its contracted core functionality.

12. [ ] Implement and enable the GP Andalucia modules.
    - Specialized PRL Voice and the basic certificate/document editor with reusable templates and batch generation.
    - Success: modules are enabled only for GP Andalucia from the owner control plane and pass real-client acceptance cases.

### Phase 4 — Detailed Executor Plan: Module Registry and Enforcement

Planning decision: instance entitlement and tenant-user permission are separate mandatory barriers. Instance entitlement is evaluated first and cannot be bypassed by a tenant superadministrator. Existing `user_feature_access` remains the second barrier; it is not replaced by the module system. During Phase 4, the version-controlled instance manifest remains the entitlement source. The owner control plane in Phase 5 will later produce and deploy the same validated manifest contract rather than introducing a second authorization model.

Use the existing Lead Finder Gesture as the first optional-module acceptance case. This proves real page/API/job isolation without creating a disposable production feature. The current `default` instance will explicitly enable every existing module required to preserve its visible behavior. Unknown modules, duplicate definitions, dependency cycles, and missing dependencies fail closed during instance boot.

1. [x] Phase 4.1 — Add the immutable module catalog and dependency validator.
   - Define typed module metadata: stable slug, localized name/description keys, default state, dependencies, capability mappings, navigation metadata, configuration schema, and optional health-check identifier.
   - Register current core modules and each existing Gesture module; map dynamic Voices to `core.voices`, image generation to its own entitlement, and current administration/connectors to their core modules.
   - Validate uniqueness, known module slugs, dependency completeness, and cycles before an instance becomes active.
   - Add an architecture contract and TDD coverage before implementation.
   - Success: valid manifests resolve deterministically; unknown, incomplete, or cyclic module sets fail closed; no production route behavior changes yet.

2. [x] Phase 4.2 — Compose instance entitlement with existing user permissions.
   - Add one authorization service that resolves `feature_type + feature_slug -> required module` and evaluates `module enabled AND user permitted`.
   - Refactor `UserFeatureAccessRepo` to use it without changing its public calling convention where practical.
   - Ensure superadministrators bypass only the per-user permission check, never the instance-entitlement check.
   - Filter `getAccessibleGestures`, `getAccessibleVoices`, new-user defaults, and bulk permission administration so disabled modules cannot be listed or granted.
   - Expand the default manifest explicitly so existing Claara users retain current access.
   - Success: matrix tests cover enabled/disabled × granted/denied × normal/superadmin, with the current default instance unchanged.

3. [x] Phase 4.3 — Enforce Gesture modules on every page, API, history operation, and queued job.
   - Guard all Gesture pages and specific generation APIs.
   - For generic history/get/delete/update/export endpoints, load or validate the Gesture type before returning or mutating data, then enforce its module and user permission.
   - Allow-list dynamic `gesture_type` values; never trust a client-provided slug as authorization.
   - Capture required module in queued jobs and re-check entitlement when a worker starts, so disabling a module prevents pending work from executing.
   - Return stable localized error codes/messages without revealing unavailable modules.
   - Success: disabling `gesture.lead-finder` blocks its catalog entry, direct page, all APIs, export, history, and worker execution, including for a superadmin.

4. [ ] Phase 4.4 — Enforce core Chat, Voices, image generation, connectors, and administration modules.
   - Add shared guards at the narrowest common boundaries and retain existing Voice/user-level access rules as the second layer.
   - Cover navigation discovery and direct URL/API calls; hiding a tab is never treated as enforcement.
   - Add a source-level route coverage inventory so new public entry points fail CI if no module ownership is declared.
   - Success: each current public capability has exactly one declared module owner and disabled modules are inaccessible through UI and direct requests.

5. [x] Phase 4.5 — Add truthful module-state UX primitives for the later owner panel.
   - Provide localized module metadata and reusable states: inactive, active, dependency required, applying, needs attention, and unavailable.
   - Keep Phase 4 activation manifest-driven; do not build a temporary tenant-admin switch or allow clients to change entitlements.
   - Use compact rows/drawers, clear impact/dependency copy, keyboard focus, reduced motion, and mobile single-column behavior in any diagnostic view.
   - Success: the module catalog can be rendered without infrastructure jargon and never displays `Active` until the effective instance context confirms it.

6. [x] Phase 4.6 — Prove cross-instance module isolation and regression safety.
   - Extend ephemeral isolation tests with two synthetic instances using the same code and separate manifests: Lead Finder enabled in one and disabled in the other.
   - Test anonymous, normal-user, and superadmin direct requests plus API/job attempts.
   - Run the complete English/Spanish, authentication, cookie, database/storage/RAG isolation, and production HTTP regression suites.
   - Deploy with backup, installed-code verification, checksum comparison, Spanish ephemeral staging, cleanup audit, and rollback path.
   - Success: the enabled instance works normally, the disabled instance fails closed at every boundary, and default production remains behaviorally and data-wise unchanged.

## Project Status Board

- [x] Reviewed the existing main scratchpad and identified the need for a dedicated initiative file.
- [x] Confirmed SSH access to the production host alias `iaiapro` as user `codex` and located the Claara application directory; no server changes made.
- [x] Established the target product direction in planning conversation.
- [x] Confirmed Codex will own implementation, server operations, deployment, and verification.
- [x] Defined phased delivery and UX quality gates.
- [x] Complete Phase 0 analysis: production inventory, architecture decisions, threat model, UX flows, and official technical references.
- [x] Obtain owner approval at the Phase 0 gate.
- [x] Owner approved Phase 0 and explicitly switched to Executor mode.
- [x] Phase 1 / Task 1: owner manually verified normal login and main workspace after automated baseline passed.
- [x] Phase 1 / Task 2: complete backup and isolated restore verified and approved by owner.
- [x] Phase 1 / Task 3: host-only, instance-specific session and remember cookies deployed, verified, and approved by owner.
- [x] Phase 1 / Task 4: public Qdrant exposure closed, local RAG access verified, and approved by owner.
- [x] Phase 1 / Task 5: ephemeral staging with synthetic data verified and Phase 1 gate approved by owner.
- [x] Phase 2: default instance context and cross-instance resource isolation deployed, verified, and accepted by owner.
- [x] Phase 3 / Task 1: implement and verify the shared English/Spanish localization foundation (resolver, dictionaries, fallback, interpolation, diagnostics, and dynamic locale helpers).
- [x] Phase 3 / Task 2: localize authentication and shared application shell.
- [x] Phase 3 / Task 3a: localize Account and its API/JavaScript states.
- [x] Phase 3 / Task 3b: localize Chat and its API/JavaScript states.
- [x] Phase 3 / Task 3c: localize Voices and enforce resolved-language behavior in AI prompts. (Owner accepted the deployed localization batch on 2026-08-16.)
- [x] Phase 3 / Task 4: add optional per-user locale preference and complete responsive English/Spanish acceptance checks. (Owner accepted the deployed localization batch on 2026-08-16.)
- [ ] Phase 3 / remaining Gestures: localize every enabled workflow and its generated/error/history states. (Catalog, Write Content, Social media, Content transformer, Lead Finder, Process generator, Course creator, Audio transcriber, Project analysis, Podcast, and the active Image editor are deployed and accepted for the current milestone; final residual-copy audit remains deferred.)
- [x] Phase 4 planning: module registry, two-layer authorization, route/job enforcement, UX primitives, and cross-instance acceptance criteria defined.
- [x] Phase 4 / Task 4.1: immutable module catalog and dependency validator. (Deployed, verified, and accepted by advancing to Task 4.2.)
- [x] Phase 4 / Task 4.2: compose instance entitlement with existing user permissions. (Deployed, verified, and owner-accepted on 2026-08-16.)
- [x] Phase 4 / Task 4.3: enforce Gesture modules across pages, APIs, history, downloads, and queued jobs. (Deployed, automatically verified, and owner-accepted through successful Podcast processing on 2026-08-16.)
- [x] Phase 4 / Task 4.4: enforce core Chat, Voices, Gestures, Connectors, and Administration ownership across navigation, pages, and APIs. (Deployed, automatically verified, and owner-accepted on 2026-08-16.)
- [x] Phase 4 / Task 4.5: reusable truthful module-state UX primitives for the owner control plane. (Deployed, visually and automatically verified, and owner-accepted on 2026-08-16.)
- [x] Phase 4 / Task 4.6: final two-instance module-isolation and complete regression gate. (Deployed, automatically verified, and owner-accepted on 2026-08-16.)
- [x] Phase 4 gate: central module registry, two-layer permissions, route/API/job enforcement, truthful owner UX primitives, and cross-instance isolation are complete and owner-accepted.

## Current Status / Progress Tracking

- 2026-08-15 Planner: Created this dedicated scratchpad and linked it from the main Claara scratchpad. Recorded the agreed owner control plane, isolated-instance model, module gating, English/Spanish localization, update strategy, GP Andalucia target, and an incremental implementation plan.
- 2026-08-15 Planner: Verified read-only SSH connectivity through alias `iaiapro` to `mail.claara.tech` as `codex`; `/home/dvdgp/web/claara.tech/public_html` exists. No production files, services, databases, or configuration were changed.
- 2026-08-15 Planner: User confirmed Codex will perform all implementation and production operations. Added an eight-phase delivery plan with explicit approval gates and a UX standard covering responsive behavior, progressive disclosure, interaction states, accessibility, module activation clarity, and Spanish product copy.
- 2026-08-15 Planner: Completed the read-only production inventory and documented the proposed architecture, threat model, owner/client UX flows, Hestia provisioning notes, Qdrant security guidance, and PHP internationalization approach under `docs/architecture/` and `docs/research/`. Phase 0 analysis is complete pending owner approval. No production changes were made.
- 2026-08-15 Executor: Implemented `scripts/smoke_http_baseline.php` plus `docs/testing/http-baseline.md`. The suite has explicit production-auth and HTTPS guards, never prints credentials/private bodies, and avoids AI/content mutations. Verified: anonymous HTTP baseline 17/17; authenticated production baseline with the existing restricted test account 34/34; existing production access smoke suites 17/17 and 14/14 (transactional writes rolled back). Recorded current base-domain cookie scope as an observation for the later session-isolation task. No production files/configuration were changed; authenticated test only updated the technical account's normal `last_login_at`. Task awaits owner manual verification before moving to backup/restore.
- 2026-08-15 Executor: Owner manually confirmed login, main workspace, Voices, Gestures, and Account work normally. Characterization Task 1 is complete; Task 2 (complete backup and isolated restore) started.
- 2026-08-15 Executor: Implemented root-only operational scripts `scripts/ops/backup_instance.sh` and `scripts/ops/verify_restore_instance.sh`, with the recovery procedure documented in `docs/operations/instance-backup-restore.md`. Created backup `claara-default-20260815T082139Z` at `/var/backups/claara/instance-platform/` (93 MB, mode 0700) and verified every SHA-256 checksum. An isolated restore passed for MariaDB (41 tables; key counts 3 users, 187 messages, 4 context documents), persistent storage, protected configuration, and Qdrant (`lex_knowledge_base`: 92 points; `voice_conveniex`: 295 points; both vector size 4096). Independent post-restore checks found zero temporary databases, Qdrant collections, or directories; production counts were unchanged, both production collections remained green, and the anonymous HTTP baseline passed 17/17. No production data or configuration was overwritten. Task 2 awaits owner approval before session work begins.
- 2026-08-15 Executor: Owner approved the backup-and-restore milestone. Phase 1 / Task 2 is complete; Task 3 session-cookie isolation started.
- 2026-08-15 Executor: Implemented `App\CookieScope`, centralizing validated host resolution, HTTPS detection, host-derived session/remember names, and host-only cookie options. Refactored sessions and remember tokens to use that policy and added one-time removal of legacy `Domain=claara.tech` cookies. TDD/configuration checks passed 11/11; a local two-host HTTP check proved distinct names for `claara.tech` and `gpand.claara.tech`. Deployed the four scoped PHP files to production after linting, with root-only rollback archive `code-pre-session-20260815T083200Z.tar.gz`. Production verification passed: HTTP baseline 21/21, legacy-cookie deletion at both former scopes, permission smoke suites 17/17 and 14/14 with rollbacks intact, and no new PHP errors. Existing browser sessions are intentionally invalidated once. Task awaits owner login/browser verification before completion.
- 2026-08-15 Executor: At the owner's request, identified the owner's active superadmin account as `david@wthefox.com`, reset only that account's password using Argon2id, and revoked its 13 existing remember-me tokens. The generated credential was returned directly to the owner and was not recorded in project files. Browser login verification remains pending.
- 2026-08-15 Executor: Owner successfully signed in with the reset credential and confirmed normal operation. Phase 1 / Task 3 is complete and owner-approved. Task 4 started to close the publicly exposed unauthenticated Qdrant endpoint without disrupting local RAG/Voice access.
- 2026-08-15 Executor: Changed the effective and mirrored Docker Compose configurations from public `0.0.0.0:6333` publication to host-loopback `127.0.0.1:6333`, preserving the existing image and `public_html_qdrant_data` volume. Added `scripts/ops/verify_qdrant_network_scope.sh`; it failed against the old public binding and passed after deployment. External access to `91.98.155.109:6333` is now blocked, while local/application health passed and collection counts remain 92 (`lex_knowledge_base`) and 295 (`voice_conveniex`). Container logs show both collections fully recovered with no startup errors; the Claara HTTP baseline passed 21/21. Root-only Compose rollback is `compose-pre-qdrant-loopback-20260815T083900Z.tar.gz`; the previously verified full recovery backup remains available. Task awaits owner verification of one knowledge-backed Voice query.
- 2026-08-15 Executor: Owner confirmed a knowledge-backed Voice works normally after the Qdrant network change. Phase 1 / Task 4 is complete and owner-approved.
- 2026-08-15 Executor: Added and deployed `scripts/ops/verify_ephemeral_staging.sh`. It creates a schema-only temporary database with unique least-privilege credentials, one synthetic `example.invalid` user, an empty-storage code workspace, disabled AI/Qdrant access, and a loopback-only PHP server. Its authenticated baseline passed 38/38; production remained at fingerprint `41:3:187:4`. An independent cleanup audit found zero staging databases, database users, directories, or listeners, and the production HTTP baseline passed 21/21 afterward. Task 5 is automatically complete and awaits owner approval of the Phase 1 gate before instance-context work begins.
- 2026-08-15 Executor: Owner approved the Phase 1 gate and authorized several Phase 2 milestones while away. Implemented a versioned, validated instance manifest and immutable runtime context. Host resolution now fails closed before sessions/database access; suspended, unknown, and invalid configurations have stable responses. The current Claara deployment is represented by `config/instances/default.json` without changing its branding, domains, database names, or Qdrant collection names.
- 2026-08-15 Executor: Routed database credentials, application file paths, PHP session files, Qdrant endpoint/API key, and RAG collection prefix through the active instance resource contract. All previously direct application storage paths now use `App\Storage`, which confines paths to the instance root and rejects traversal. The default cookie namespace remains `2dfa7c360aec`, avoiding another cookie-name change; session files are now physically stored in the default instance storage root.
- 2026-08-15 Executor: Added `scripts/ops/verify_instance_isolation.sh`. Two real synthetic instances proved mutually denied database grants, separate storage/session roots, separate user identities and cookies, and separate prefixed Qdrant collections. The alpha cookie returned 401 at beta. Candidate and installed-code staging passed 38/38; context tests passed 28/28; cookie tests 11/11; access suites 17/17 and 14/14; public HTTP 21/21. Production remained `41:3:187:4`, Qdrant remained green at 92/295 points, and independent cleanup found zero temporary resources. Root-only rollback archive: `code-pre-phase2-context-20260815T090400Z.tar.gz`. Phase 2 awaits owner browser acceptance.
- 2026-08-15 Executor: Owner confirmed login, navigation, Voice/RAG, and existing flows work correctly after the Phase 2 deployment. Phase 2 is complete and owner-accepted. The next planned delivery phase is runtime English/Spanish internationalization.
- 2026-08-15 Executor: Owner authorized Phase 3 in Executor mode. Split runtime localization into four independently verifiable milestones and started Task 1, limited to the shared localization foundation before any broad UI translation.
- 2026-08-15 Executor: Implemented and deployed the Phase 3 localization foundation: deterministic locale resolution (`user > instance > English`), keyed PHP dictionaries, English fallback, named-placeholder validation, de-duplicated missing-key diagnostics, HTML locale helper, and a safely encoded JavaScript catalog. The default production instance remains English. Verification passed 20/20 i18n tests, 28/28 instance-context tests, 11/11 cookie tests, 21/21 public HTTP checks, and 38/38 authenticated isolated-staging checks; production fingerprint remained `41:3:189:4`. No database change was made. Root-only rollback copy: `/var/backups/claara/instance-platform/code-pre-i18n-20260815T093008Z/bootstrap.php`. Task 1 awaits owner confirmation that the existing English login/workspace still look and behave normally.
- 2026-08-15 Executor: Owner confirmed the existing English login and workspace remain correct. Phase 3 / Task 1 is complete and Task 2 started for authentication and the shared application shell.
- 2026-08-15 Executor: Implemented and deployed Phase 3 / Task 2 for localized authentication and shared shell. Login copy and errors, dynamic HTML locale, shared browser catalog, desktop/mobile navigation, conversation sidebar, shared header/profile actions, drawers, Voice/Gesture quick navigation, and English/Spanish metadata now use keyed dictionaries. Dictionary parity and referenced-key coverage passed 226/226; foundation passed 20/20; instance/cookie regression tests passed 28/28 and 11/11. Both English and Spanish isolated instances passed 38/38 authenticated HTTP checks and localized-shell assertions, with production unchanged at fingerprint `41:3:189:4`; production public HTTP passed 21/21. Spanish login visual QA passed on desktop and a 390×844 mobile viewport with no horizontal overflow. No database change was made. Root-only rollback archive: `/var/backups/claara/instance-platform/code-pre-i18n-task2-20260815T094108Z.tar.gz`. Task 2 awaits owner browser acceptance before chat/account/Voices localization begins.
- 2026-08-15 Executor: Owner confirmed the production English experience remains correct after Task 2. Task 2 is complete. Split the next broad localization milestone into Account, Chat, and Voices/AI-prompt tasks; started Task 3a for Account.
- 2026-08-15 Executor: Implemented and deployed Phase 3 / Task 3a for Account. Localized the full Account page, profile/security/activity copy, empty/default values, password modal, JavaScript loading/success/error states, account API validation errors, shared unauthorized and CSRF errors, and the current-year footer. Moved the page's legacy inline CSS into scoped rules in `public/assets/css/styles.css` without changing its visual treatment. Account coverage passed 227/227; shared dictionary coverage 277/277; foundation 20/20; instance/cookie regression 28/28 and 11/11. English and Spanish isolated instances each passed 38/38 authenticated HTTP checks plus locale-specific Account and API-validation assertions; only synthetic validation requests were issued, with no profile/password mutation. Production public HTTP passed 21/21 and fingerprint remained `41:3:189:4`. No database change was made. Root-only rollback archive: `/var/backups/claara/instance-platform/code-pre-i18n-account-20260815T095045Z.tar.gz`. Task 3a awaits owner browser acceptance before Chat localization starts.
- 2026-08-15 Executor: Owner confirmed Account remains correct in the production English instance and explicitly authorized advancing through several subsequent milestones before the next manual review. Task 3a is complete; Task 3b Chat localization started, followed by AI-language behavior and then core Voices surfaces if the preceding gates remain green.
- 2026-08-15 Executor: Implemented and deployed the main Chat localization, including empty/chat composers, file/drop states, selection editing, reports, sharing, folders, source attachment, response actions, downloads, citations, dates, alerts, and JavaScript-generated states. Added locale-prefixed browser catalogs rather than exposing unrelated translations. The resolved instance locale now adds an explicit response-language rule to both general Chat and static/RAG Voice prompts. Localized the Voices catalog and its empty/default states; individual Lex/generic Voice workspaces remain the next UI slice. Installed-code coverage passed 21/21 foundation, 484/484 shared, 362/362 Account, 430/430 Chat, 5/5 AI-language, and 13/13 Voices-catalog checks. English and Spanish isolated instances each passed 38/38 authenticated HTTP checks and localized-shell assertions; public production passed 21/21 and fingerprint remained `41:3:189:4`. No database or production data changed. Temporary candidates and staging resources were removed. Root-only rollback archive: `/var/backups/claara/instance-platform/code-pre-i18n-chat-ai-20260815T101200Z.tar.gz`. Chat and AI-language behavior await owner browser verification; detailed Voice workspaces are not yet complete.
- 2026-08-15 Executor: Owner confirmed the deployed Chat work is correct and authorized several further milestones before review. Task 3b is complete. Localized both Lex and generic Voice workspaces: history, suggestions, composers, document search/viewing, PDF actions, loading/empty/error states, evidence-match metadata, source-conflict summaries, and locale-aware relative dates. Localized stable Voice API errors while retaining detailed server diagnostics in logs. Installed verification passed 126/126 Voice workspace and 58/58 Voice API checks, with all prior suites still green.
- 2026-08-15 Executor: Added optional per-user locale preference under Account, constrained by the active instance allowlist. Resolution now follows `user preference > instance default > English`; normal login and remember-session restoration both carry the preference. Added idempotent migration `027_user_locale.sql`, updated fresh-instance schema, and validated the migration twice against an isolated temporary database before production. Production received a nullable `users.locale` column with all three existing users left at `NULL`, so no existing experience changed. English and Spanish ephemeral instances each passed 38/38 plus real synthetic user overrides in both directions; final Spanish staging remained green and production fingerprint stayed unchanged during each run at `41:3:191:4`. Public production passed 21/21 and installed localization suites passed 1,758 checks. Root-only rollback archives: `/var/backups/claara/instance-platform/code-pre-i18n-voices-locale-20260815T102500Z.tar.gz`, `/var/backups/claara/instance-platform/db-pre-user-locale-20260815T102500Z.sql.gz`, and `/var/backups/claara/instance-platform/code-pre-i18n-voice-api-20260815T103500Z.tar.gz`. Voice workspaces and the Account language selector await owner browser/responsive acceptance.
- 2026-08-15 Executor: Began the remaining Gestures localization block. Fully localized the Gestures catalog, including every enabled/coming-soon card and explanatory state, and removed the catalog's embedded emoji to preserve visual consistency. Localized the primary labels, choices, validation, generation, result, history, copy, load, and delete states of the Write Content workflow; secondary placeholder examples remain for the final residual-copy pass. Coverage passed 107/107 catalog and 68/68 Write workflow checks, all shared regression suites remained green, production HTTP passed 21/21, and Spanish isolated staging passed 38/38 plus user-locale override. Production fingerprint remained `41:3:191:4`. Rollback: `/var/backups/claara/instance-platform/code-pre-i18n-gestures-20260815T105000Z.tar.gz`. Residual audit ranks the next Gesture surfaces by untranslated visible-string matches: image editor 65, legacy image editor 57, content transformer 32, SOP 32, social 31, lead finder 29, course creator 23, project analysis 18, podcast 14, Write Content secondary copy 14, and audio transcriber 12.
- 2026-08-15 Executor: Localized the complete Social media and Content transformer workflows, including static controls, generated result panels, previews, copy/download actions, validation, progress, history, deletion, relative dates, and responsive drawers. Both workflows now pass the resolved locale into AI generation: Social adds an explicit output-language instruction, while Content transformer no longer hardcodes Spanish for English users and the API enforces the authenticated locale. Moved the Transformer's legacy embedded CSS into `public/assets/css/styles.css`. Installed checks passed 82/82 Social, 90/90 Transformer, 21/21 foundation, 828/828 shared regression, and production HTTP 21/21. Spanish isolated staging passed 38/38 plus locale override; production remained `41:3:191:4`. Rollback: `/var/backups/claara/instance-platform/code-pre-i18n-social-repurpose-20260815T114500Z.tar.gz`. These two Gestures await owner browser verification.
- 2026-08-15 Executor: Localized Lead Finder, Process generator, and Course creator across their static interfaces, validation, progress, results, history, deletion, downloads/exports, and relative dates. Process and Course AI prompts now explicitly follow the resolved English/Spanish locale, their API validation is localized, and Course mutations now consistently send CSRF protection. Removed Course emoji labels and switched the two full-height workspaces to `100dvh` for safer mobile layout. Installed verification passed 77/77 Lead, 88/88 Process, 113/113 Course, 21/21 foundation, 1,078/1,078 shared regression, and 21/21 production HTTP checks. Spanish isolated staging passed 38/38 plus localized-shell assertions; production fingerprint remained `41:3:191:4`. Local/production checksums matched and cleanup left zero staging databases, users, directories, or candidate files. No database or user data changed. Rollback: `/var/backups/claara/instance-platform/code-pre-i18n-lead-sop-course-20260815T161437Z.tar.gz`. These three Gestures await combined owner browser verification with the preceding Social and Content transformer work.
- 2026-08-15 Executor: Localized Audio transcriber, Project analysis, and Podcast across static controls, validation, progress, results, history, cancellation/deletion, downloads, relative dates, APIs, and background states. Podcast jobs and audio-transcription jobs now capture the initiating locale; the worker restores that validated locale without relying on a browser session, so cron processing preserves the user's language for progress, generated scripts, and TTS. Project analysis output prompts follow the resolved locale, AI Markdown is escaped before rendering, and missing CSRF protection was added to Project deletion and Podcast worker triggering. Removed embedded/inline styles and emoji, scoped all three workflows in `styles.css`, and changed their full-height shells to `100dvh`. Installed verification passed 64/64 Audio, 64/64 Project, 87/87 Podcast, 21/21 foundation, 1,252/1,252 shared regression, and 21/21 production HTTP checks. Spanish isolated staging passed 38/38 plus localized-shell assertions; production fingerprint remained `41:3:191:4`. Checksums matched and cleanup left zero staging databases, users, directories, or candidates. No database or user data changed. Rollback: `/var/backups/claara/instance-platform/code-pre-i18n-audio-project-podcast-20260815T163329Z.tar.gz`. These workflows await combined owner browser verification.
- 2026-08-16 Executor: Owner accepted the current deployed multi-language batch as good for the present milestone. Voices, prompt-language behavior, and the per-user locale preference are accepted. Remaining Gesture translation residue is explicitly deferred rather than treated as a blocker for beginning the module/control-plane work.
- 2026-08-16 Planner: Audited the live capability inventory and current enforcement. Production has 10 active Gestures, 2 active Voices plus one inactive Voice, and 2 active cross-cutting features. Existing permissions are user-scoped; `InstanceContext::isModuleEnabled()` is not yet used by application routes, many generic/specific Gesture APIs lack feature checks, and tenant superadmins currently bypass all user permission checks. Defined Phase 4 as a static code registry plus validated instance manifest, with instance entitlement evaluated before user permission, superadmins unable to bypass instance entitlement, generic history/jobs deriving module ownership server-side, and Lead Finder as the first real cross-instance isolation case. No application, database, manifest, or server change was made.
- 2026-08-16 Executor: Implemented and deployed Phase 4.1 without changing route or user-permission behavior. Added immutable `ModuleDefinition`/`ModuleRegistry` contracts for 5 core modules, image generation, and all 10 current Gestures; localized English/Spanish metadata; exact and controlled wildcard capability ownership; deterministic dependency-safe ordering; and fail-closed rejection of unknown modules, duplicates, missing/unknown dependencies, duplicate capability owners, and cycles before instance activation. Documented the contract in `docs/architecture/module-registry-contract.md`. Installed verification passed 48/48 registry, 30/30 instance-context, 21/21 i18n foundation, 1,284/1,284 shared regression, 21/21 production HTTP, and 38/38 Spanish ephemeral staging checks. Production fingerprint remained `41:3:191:4`; local/production checksums matched; cleanup left zero staging databases, users, directories, or candidate files. No database, manifest, user data, or visible access changed. Root-only rollback: `/var/backups/claara/instance-platform/code-pre-modules-phase41-20260815T222318Z.tar.gz`. Task 4.1 awaits owner acceptance before composing entitlement with user permissions in Task 4.2.
- 2026-08-16 Executor: Owner reported blank Project analysis, Audio transcriber, and Podcast pages. Production logs confirmed the earlier localization batch referenced nonexistent `I18n::htmlLanguage()` and `I18n::browserCatalog()` methods; the static tests had incorrectly asserted those same invalid names. Replaced them with `htmlLang()` and `javascriptCatalogPrefixJson()`, updated JavaScript consumers to read the catalog `messages` envelope, added a reflection-based page/API contract test (15/15), and extended ephemeral staging to grant a synthetic user access and render all three authenticated page shells. Installed checks passed Project 65/65, Audio 65/65, Podcast 88/88, foundation 21/21, shared 1,284/1,284, registry 48/48, and context 30/30. Spanish ephemeral staging passed 38/38 plus all three real page renders; production fingerprint remained `41:3:191:4`, checksums matched, public HTTP passed 21/21, and cleanup left zero temporary databases/users/candidates. During deployment, `rsync -a` copied the mode of a temporary mode-0700 source root onto `public_html`, briefly causing HTTP 403 responses; detected through the public smoke, restored the Hestia-compatible `dvdgp:www-data` ownership and `0751` mode immediately, then reran the full 21/21 public baseline successfully. No data or database changed. Rollback: `/var/backups/claara/instance-platform/code-pre-gesture-render-hotfix-20260815T223100Z.tar.gz`. The three Gestures await owner browser confirmation; Phase 4.2 remains paused.
- 2026-08-16 Executor: Owner confirmed Project analysis, Audio transcriber, and Podcast now display correctly in production. The render hotfix is owner-accepted. Owner also confirmed Image editor still displays in English with a Spanish profile; this is tracked as remaining Phase 3 localization work, not a rendering regression.
- 2026-08-16 Executor: Localized and deployed the active Image editor across intent presets, prompts, quick edits, reference uploads, source/target editing, parameters, loading/error states, results, mobile controls, history, deletion, relative dates, and API validation. Optional model text now follows the resolved locale while the internal image prompt remains optimized for the image model. Moved the embedded editor CSS into scoped `styles.css`, corrected the editor shell to `100dvh`, and extended the I18n method contract plus authenticated staging to render and assert the Spanish editor. Installed verification passed 164/164 Image editor, 20/20 page contract, 21/21 foundation, 1,432/1,432 shared regression, 48/48 module registry, 30/30 instance context, and 21/21 public HTTP. Spanish ephemeral staging passed 38/38 plus real Project, Audio, Podcast, and Image editor page renders with Spanish text assertions. Production fingerprint remained `41:3:191:4`; local/production checksums matched, root permissions remained `dvdgp:www-data:0751`, and cleanup left zero staging databases/users. No image was generated and no AI usage or production data changed. Rollback: `/var/backups/claara/instance-platform/code-pre-image-editor-i18n-20260815T224816Z.tar.gz`. Image editor awaits owner browser verification before this milestone is accepted.
- 2026-08-16 Executor: Owner accepted the Image editor localization by confirming it looked correct and authorized continuing. Phase 4.1 is likewise accepted through the explicit instruction to proceed with the next module milestone.
- 2026-08-16 Executor: Implemented and deployed Phase 4.2. Added `ModuleEntitlementService` as the central capability-to-module check and composed it with `UserFeatureAccessRepo`: instance entitlement is evaluated first; regular users still require their existing grant; tenant superadmins bypass only that grant; unknown capabilities fail closed; disabled modules are omitted from Gesture, Voice, feature-administration, bulk, and new-user-default catalogs and cannot be granted. Existing permission rows are preserved for reversible reactivation. Expanded the default manifest to all 16 shipped modules so current Claara behavior remains unchanged. TDD passed 19/19 locally for the full enabled/disabled × grant × role matrix; installed pure entitlement checks passed 6/6 with the unavailable SQLite-only repository section explicitly skipped, while a real read-only MariaDB integration check passed with 14 features and 10 Gestures. Installed regression passed registry 48/48, context 30/30, foundation 21/21, shared 1,432/1,432, Image editor 164/164, page contract 20/20, and public HTTP 21/21. Spanish schema-only staging passed 38/38 and rendered Project, Audio, Podcast, and Image editor with synthetic grants. Production fingerprint remained `41:3:191:4`; local/production checksums matched; root stayed `dvdgp:www-data:0751`; cleanup found zero staging databases, users, directories, or candidates. No production database row or user permission changed. Rollback: `/var/backups/claara/instance-platform/code-pre-module-entitlement-20260815T225629Z.tar.gz` (rollback also removes the newly introduced entitlement service and test files). Task 4.2 awaits owner browser verification before direct route/API/job enforcement in Task 4.3.
- 2026-08-16 Executor: Owner confirmed Phase 4.2 behaves correctly and authorized continuing. Task 4.2 is complete and owner-accepted.
- 2026-08-16 Executor: Implemented and deployed Phase 4.3 Gesture boundary enforcement. Added the centralized `GestureAccessGuard`, a closed ten-Gesture catalog, stable localized unavailable/forbidden responses, and a source coverage contract spanning 11 direct pages, 17 fixed APIs including Podcast audio delivery, 6 dynamic history/read/mutation routes, and all 5 job endpoints. Dynamic generation is allow-listed; generic record operations authorize the stored server-side `gesture_type`; mixed history hides disabled modules. Every new background job captures a server-derived `required_module`, overwriting client input; status/cancel/listing re-check access; workers re-check captured ownership, instance entitlement, and originating-user permission before processing. Local verification passed route coverage 44/44, entitlement/job matrix 22/22, registry 48/48, context 30/30, foundation 21/21, shared 1,434/1,434, all ten Gesture suites, and page contract 20/20. Installed verification passed route coverage 44/44, registry 48/48, context 30/30, foundation 21/21, shared 1,434/1,434, page contract 20/20, and production HTTP 21/21. Spanish schema-only staging passed 38/38, then removed `gesture.lead-finder` and proved the catalog, direct page, history/search/export APIs, normal user, superadmin, and pending worker all fail closed without invoking AI. Production Lead Finder remained enabled, fingerprint stayed `41:3:191:4`, checksums matched, root stayed `dvdgp:www-data:0751`, and cleanup found zero staging databases/users/directories. No production rows, permissions, jobs, or AI usage changed. Rollback: `/var/backups/claara/instance-platform/code-pre-gesture-enforcement-20260815T232641Z.tar.gz` (rollback also removes the new guard and route-test files). Task 4.3 awaits owner browser verification before Phase 4.4 core-module enforcement.
- 2026-08-16 Executor: Owner successfully processed a Podcast and authorized continuing. This accepts Phase 4.3 and closes its browser gate.
- 2026-08-16 Executor: Implemented and deployed Phase 4.4 core route enforcement. Added the ordered `CoreRouteRegistry` and bootstrap-level `CoreModuleGuard` for Chat, Voices, Gestures, Connectors, and Administration. Connector administration has one explicit owner (`core.connectors`) before the broad Administration boundary; neutral login/account/help routes stay reachable. Disabled pages redirect to Account with `feature_unavailable`, APIs return localized HTTP 404, and shared desktop/mobile/header navigation omits disabled areas. Existing user/Voice rules and image generation's separate capability check remain mandatory second barriers. The TDD contract passed 42/42 and inventories all 161 current PHP entry points in protected areas, so new unowned routes fail CI. Local regression passed registry 48/48, entitlement 22/22, Gesture boundary 44/44, context 30/30, foundation 21/21, shared 1,434/1,434, page contract 20/20, Image editor 164/164, Podcast 88/88, and the remaining relevant localization suites. Installed checks passed core 42/42, Gesture 44/44, registry 48/48, context 30/30, foundation 21/21, shared 1,434/1,434, page contract 20/20, Image editor 164/164, Podcast 88/88, and public HTTP 21/21. Spanish ephemeral staging passed 38/38, retained all localized Gesture shells, disabled Lead Finder successfully, then disabled Connectors and Administration and proved both hidden and inaccessible by direct page/API calls, including for superadmin. Production remained fully enabled and unchanged at `41:3:191:4`; checksums matched, root stayed `dvdgp:www-data:0751`, and cleanup found zero temporary databases/users/directories. No manifest, database row, permission, user data, or AI usage changed. Rollback: `/var/backups/claara/instance-platform/code-pre-core-enforcement-20260815T234126Z.tar.gz` (rollback also removes the new core registry, guard, and route-test files). Task 4.4 awaits owner browser verification.
- 2026-08-16 Executor: Owner confirmed normal production navigation and behavior after the core route boundary deployment. Phase 4.4 is complete and owner-accepted; the next isolated milestone is Phase 4.5, reusable truthful module-state UX for the later owner control plane.
- 2026-08-16 Executor: Implemented and deployed Phase 4.5 without exposing a temporary tenant-admin page or activation control. Added a reusable module presenter/resolver and owner view for inactive, active, dependency-required, applying, needs-attention, and unavailable states; `Active` is derived only from the effective manifest, while applying retains the separate effective-access truth. The component includes localized dependency names, semantic labels, native keyboard disclosure, loading/empty/error states, reduced motion, and a compact responsive list. All CSS is scoped in `styles.css`. A local browser preview passed desktop visual QA and a 390x844 mobile check with all 16 rows and zero horizontal overflow. TDD passed 45/45; local regression passed registry 48/48, entitlement 22/22, core routes 42/42, Gesture routes 44/44, context 30/30, foundation 21/21, page contract 20/20, shared localization 1,457/1,457, Image editor 164/164, and Podcast 88/88. Installed verification repeated those relevant suites and public HTTP 21/21. Spanish ephemeral staging passed 38/38 plus existing Gesture rendering, disabled Gesture/core-module boundaries, and unchanged production data. Production fingerprint remained `41:3:193:4` from the pre-deployment check; hashes matched, root stayed `dvdgp:www-data:0751`, and cleanup found zero temporary databases/users/directories. No manifest, database row, permission, user data, public route, or AI usage changed. Rollback: `/var/backups/claara/instance-platform/code-pre-module-state-ui-20260815T235216Z.tar.gz` (rollback also removes the new state classes, owner view, test, and local preview script). Task 4.5 awaits owner acceptance before Phase 4.6 cross-instance proof.
- 2026-08-16 Executor: Owner accepted Phase 4.5 and authorized the final Phase 4.6 isolation/regression gate.
- 2026-08-16 Executor: Implemented, installed, and executed the final Phase 4.6 cross-instance module gate. Extended the destructive-safe isolation drill to run the same installed release as Alpha and Beta with independent MariaDB credentials/data, storage, sessions, hosts, and Qdrant prefixes. Alpha enables `gesture.lead-finder`; Beta enables the Gestures core but not Lead Finder. Both normal users retain the same database grant, and each instance also has a superadmin. Anonymous page/API behavior did not disclose module state; Alpha normal and superadmin page/API access succeeded; Alpha queued a real Lead Finder job that completed through the local mock provider with captured `gesture.lead-finder` ownership. Beta omitted the catalog entry and denied normal user, superadmin, history/search APIs, direct page, and a directly seeded pending worker; the worker failed `feature_unavailable` before provider execution. The source contract passed 15/15. All 29 local and installed PHP suites passed (server entitlement repository section retained its documented SQLite-only skip), including registry 48/48, entitlement 22/22 local, core routes 42/42, Gesture routes 44/44, state UI 45/45, context 30/30, cookies 11/11, and every English/Spanish surface suite. Public HTTP passed 21/21. Fresh authenticated ephemeral staging passed 38/38 in both English and Spanish, including disabled Gesture/core-module checks. Production fingerprint remained `41:3:193:4`; production Qdrant remained `lex_knowledge_base:voice_conveniex`; local/installed hashes matched; root stayed `dvdgp:www-data:0751`; independent cleanup found zero temporary databases, users, directories, or Qdrant collections. No customer data, manifest, user permission, application behavior, AI provider, or production application file was changed by this final drill; only the permanent verification script, its contract test, and documentation were installed. Rollback: `/var/backups/claara/instance-platform/code-pre-phase46-isolation-20260815T235857Z.tar.gz` (rollback restores the former isolation script and removes the new contract test/documentation). Task 4.6 awaits owner acceptance before the Planner closes Phase 4 and selects the next phase.
- 2026-08-16 Executor: Owner confirmed normal production behavior after the final drill. Phase 4.6 and the complete Phase 4 gate are owner-accepted.

## Executor's Feedback or Assistance Requests

- Phase 4 has no outstanding owner-verification request. The next planned phase is the owner-only control plane.
- Before provisioning any permanent customer instance, enable the Qdrant server-side API key now supported by the Phase 2 client, update snapshot/restore tooling to authenticate, and decide whether collection-scoped credentials are sufficient or GPAND receives a separate Qdrant service/volume.
- Before implementation, confirm where the owner control plane and per-instance databases/storage will be hosted and whether GP Andalucia requires a data-processing agreement or additional security requirements beyond the planned isolation baseline.
- Before developing against external infrastructure or provider APIs, fetch and record the current official documentation for the selected deployment, storage, database, and AI providers.

## Lessons

- Operational test scripts in production are mode `0750` under `dvdgp:dvdgp`; the `codex` SSH account must invoke them through the approved root command path rather than directly or via `sudo -u dvdgp`.
- A root-owned mode-0700 temporary deployment directory cannot receive `rsync` uploads from `codex`; create it for `codex` or explicitly change only that temporary directory's ownership before upload, while keeping rollback archives root-only.
- For additive database changes, verify an idempotent migration against a uniquely named temporary database, take a transaction-consistent production dump, apply the migration before code that selects the new column, and prove existing rows retain neutral defaults.
- Background jobs must persist the resolved locale at creation and explicitly restore it in workers; cron and token-triggered processing cannot rely on the originating browser session.
- Production PHP does not include PDO SQLite. Keep the full repository matrix in local TDD, make server-only SQLite absence an explicit skip rather than a fatal result, and prove installed integration against isolated MariaDB plus a read-only production catalog check.
- Background-job ownership must be derived from a closed server-side job-type map, captured by the repository rather than trusted from request data, and re-checked immediately before work starts; UI-time permission alone cannot protect queued execution.
- Core route ownership must be centralized and ordered from specific overrides to broad areas; inventory the public entry points in CI so a new endpoint cannot silently bypass the instance contract.
- Owner-facing deployment UI must keep requested, pending, and effective state separate. Only the effective instance manifest may produce an `Active` label; pending work stays `Applying` until verification succeeds.
- Rollback archives must list only files that already exist on the target. New documentation/tests belong in the rollback-removal note rather than the pre-change tar input.

- Keep client customization in instance configuration, data, and registered modules; never create a permanent client-specific branch.
- Feature visibility must be enforced by backend authorization as well as UI hiding.
- Sharing a release artifact is safe only when sessions, credentials, databases, storage, RAG, backups, and logs are isolated.
- Subdomains must use host-only, instance-specific authentication cookies.
- Deploy releases independently per instance with backup, migration checks, smoke tests, audit records, and rollback.
- A command intended for production must explicitly invoke `ssh iaiapro` and verify the remote hostname before interpreting output; a local working directory does not imply remote execution.
- Hestia's backup inventory must be matched against the application's actual configured database name; a green Hestia backup is not proof that an externally created database or Docker volume is covered.
- Production `.env` `ADMIN_EMAIL`/`ADMIN_PASSWORD` values are not a reliable smoke identity: the configured credentials no longer authenticate. Use a dedicated scoped smoke account supplied via `SMOKE_EMAIL`/`SMOKE_PASSWORD`; never print credentials or pass them in shell history.
- A backup is not considered recoverable merely because files and checksums exist: restore MariaDB, storage, and each Qdrant snapshot into uniquely named temporary resources, verify their contents, then independently prove cleanup and unchanged production counts.
- Database-backed smoke suites must run from the production application context when the local environment cannot reach the production database; a local JSON `db_connection_failed` response is not a passing test even if a wrapper exits successfully. Re-run explicitly over `ssh iaiapro` and inspect the suite's own pass/fail totals.
- Non-secret instance manifests must be readable by both the PHP runtime and deployment smoke user. Use 0644 for manifests and keep all credentials in the separately protected `.env`; a 0640 manifest owned only by the web identity breaks CLI verification without protecting secrets.
- When an external HTTP/SSH probe fails, distinguish network transport from application failure before rollback: inspect server load, memory, disk, service state, internal health, and recent logs, then repeat the probe. The brief 2026-08-15 reset occurred with healthy services and no application errors and did not recur.
- Localization tests must validate that every referenced `I18n` method exists and authenticated staging must render each changed page, not only its catalog entry; literal source assertions alone can certify a misspelled API and create a false positive.
- Never deploy a directory source with archive-mode `rsync` directly into an existing application root unless root metadata is explicitly excluded or normalized: the source directory's mode/ownership can overwrite the target root. Deploy explicit files or use `--no-perms --omit-dir-times`, then assert the application-root owner/group/mode before the first HTTP check.
