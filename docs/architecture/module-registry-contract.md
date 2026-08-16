# Claara Module Registry Contract

Status: Registry, permissions, and runtime route boundaries implemented (Phases 4.1–4.4)  
Date: 2026-08-16

## Purpose

The module registry is the application-owned catalog of capabilities that may be contracted for an instance. It provides one stable vocabulary for instance manifests, backend authorization, navigation, the future owner control plane, release checks, and health reporting.

The registry does not replace tenant-user permissions. Effective access always requires both:

1. the module is enabled in the active instance manifest; and
2. the tenant user has the relevant permission.

A tenant superadministrator may bypass the second condition where current product rules allow it, but can never bypass instance entitlement.

## Source of Truth

`Modules\ModuleRegistry` is immutable application code shipped with each release. An instance manifest may only reference module slugs known to that release. The manifest remains the entitlement source until the owner control plane is built; the control plane will later generate and deploy the same validated contract.

Unknown modules, duplicate enabled slugs, missing dependencies, unknown definition dependencies, duplicate capability ownership, and dependency cycles fail closed before an instance context is activated.

## Definition Shape

Every `ModuleDefinition` contains:

- stable module slug;
- localized name and description keys;
- default state for new-instance planning;
- required module dependencies;
- owned capability mappings;
- optional navigation route, icon, and area;
- configuration schema reserved for validated module settings;
- optional health-check identifier.

Core modules are enabled by default for planning purposes. Optional features and individual Gestures are disabled by default. Every Gesture module depends on `core.gestures`; image generation depends on `core.chat`.

## Capability Ownership

Existing database permissions use `feature_type + feature_slug`. Registry ownership uses the canonical `type:slug` representation. Exact ownership is preferred. A controlled wildcard is supported for dynamic capability families such as `voice:*`, owned by `core.voices`.

An unregistered capability has no module owner and fails closed. Client input never creates module or capability identifiers dynamically.

## Effective Access

`Modules\ModuleEntitlementService` resolves capability ownership through the registry and checks the active instance manifest. `Repos\UserFeatureAccessRepo` composes that result with existing user grants:

- instance disabled or capability unknown: denied for every user;
- instance enabled and tenant superadministrator: allowed without an individual grant;
- instance enabled and regular user: allowed only with an enabled individual grant.

Administration catalogs omit capabilities disabled for the active instance. Disabled or unknown capabilities cannot be granted, bulk-enabled, or included in new-user defaults. Existing database rows are preserved and simply become ineffective while their owning module is disabled, so a later reactivation is reversible without rewriting tenant data.

The `default` manifest explicitly enables every currently shipped optional module to preserve the existing Claara experience. New customer manifests can select a strict subset after dependency validation.

## Activation and Ordering

Enabled module lists are validated as sets. Dependency-safe ordering is deterministic and places dependencies before their dependants. This ordering will be reused by provisioning, migrations, health checks, activation, and rollback.

Phase 4.2 enforces entitlement anywhere the existing access repository is consulted, including catalogs, user grants, Voices, Gestures, and image-generation checks.

## Gesture Boundary Enforcement

`Gestures\GestureAccessGuard` is the shared Phase 4.3 boundary for Gesture HTTP operations and background work. Fixed-purpose APIs declare one server-owned Gesture slug. Dynamic history and generation APIs accept only registered, allow-listed Gesture slugs; record operations load the stored execution first and authorize its server-side `gesture_type` before reading or mutating it.

Background job types have a closed mapping to their owning Gesture. Job creation overwrites any client-supplied ownership marker with the server-derived `required_module`. Status, cancellation, and active-job discovery re-check effective access, and the worker validates the captured/derived module plus the originating user's current access before marking work as processing. Disabling a module therefore fails queued work closed without invoking its provider.

Disabled instance modules return the stable `feature_unavailable` response and a generic localized message. Tenant-user permission failures remain `forbidden`. This distinction is evaluated server-side; client-controlled slugs never establish ownership.

## Core Route Boundary

`Modules\CoreRouteRegistry` assigns each public Chat, Voices, Gestures, Connectors, and Administration route family to exactly one core module. Specific ownership overrides are evaluated before broad areas: connector administration belongs to `core.connectors`, while the remaining administration surface belongs to `core.administration`. The shared bootstrap invokes `Modules\CoreModuleGuard` after instance and locale initialization, so direct page and API requests fail closed before endpoint logic runs.

For a disabled core module, browser pages redirect to the neutral account page with `feature_unavailable`; API routes return the same stable code with HTTP 404. Shared navigation resolves the active instance entitlement and omits disabled areas. More granular checks remain in force after this boundary—for example, image generation still requires both `core.chat` and its per-instance/per-user capability.

The route-contract test inventories all current public PHP entry points in the protected areas. A newly added endpoint in those areas fails CI if no core owner can be resolved. Neutral authentication, account, and help routes remain deliberately outside the module boundary so users retain a safe destination when a contracted area is disabled.

## Owner Module-State Presentation

`Modules\ModuleCatalogPresenter` and `Modules\ModuleStateResolver` convert registry definitions, effective instance state, deployment progress, dependencies, release availability, and health into a reusable owner-facing view model. The presentation vocabulary is deliberately small and localized: inactive, active, dependency required, applying, needs attention, and unavailable.

`Active` is derived only from the effective instance manifest. A requested or deploying change is never presented as already active; the model retains `is_effectively_active` separately while a change is applying. Missing dependencies are resolved through registry metadata rather than infrastructure identifiers, and a tenant-facing error never exposes operational details.

The reusable catalog view includes loading, empty, error, and populated states, native keyboard disclosure, semantic status labels, reduced-motion behavior, and a single-column mobile layout. It contains no activation form or tenant-admin control. Phase 5 will supply owner authentication, audited actions, deployment state, and health data to this existing presentation contract.
