# Claara Instance Platform UX Flows

Status: Planner Phase 0 direction  
Date: 2026-08-15

## Experience Principles

- The owner panel is an operational product, not a server console.
- Show business language first; reveal infrastructure details progressively.
- Prefer a compact list and focused workspace over grids of generic cards.
- Every action has loading, empty, error, success, and recovery states.
- Never display a successful toggle until backend enforcement and configuration propagation are confirmed.
- Destructive actions explain impact and produce an audit record.
- Desktop remains efficient for administration; mobile collapses to a clear single column without losing critical actions.
- Spanish wording is reviewed as product copy rather than mechanically translated.

## Owner Navigation

Primary areas:

1. **Instances** — lifecycle, health, usage, versions, and contracted capabilities.
2. **Modules** — reusable product catalog and dependencies.
3. **Releases** — tested versions, rollout state, and rollback history.
4. **Operations** — deployment/backup jobs and incidents.
5. **Audit** — immutable owner actions.
6. **Owner settings** — MFA, notification, and support-access policy.

## Instances List

Use a compact responsive table with:

- Instance/client name and domain.
- Status: provisioning, healthy, attention, suspended, updating, rollback.
- Current release.
- Enabled modules count.
- Active users versus limit.
- Storage/AI usage summary.
- Last successful backup.
- Contextual action menu.

Filters: status, release, module, and attention required. Empty state explains how the first instance is created. Loading uses row-shaped skeletons. Errors preserve filters and provide retry.

## Create Instance Flow

A guided five-step flow:

1. **Identity** — client name, internal slug, and domain.
2. **Experience** — default/allowed languages, logo, titles, and accent.
3. **Plan** — users, storage, AI usage, and retention limits.
4. **Modules** — select modules with dependency and cost/impact explanations.
5. **Review** — exact resources and effects before provisioning.

Submission creates a visible provisioning job. The UI shows named stages rather than an indefinite spinner. Failure identifies the failed stage, keeps safe completed work, and offers retry/rollback.

## Instance Workspace

Tabs:

- **Overview:** health, usage, domain, current release, last backup, and actionable warnings.
- **Modules:** searchable entitlement list with states and dependencies.
- **Experience:** branding and locale policy.
- **Limits & usage:** users, storage, AI, and trend warnings.
- **Versions:** current/recommended release, update preflight, history, and rollback.
- **Security & recovery:** session policy, backups, restore tests, and support access.
- **Audit:** owner changes scoped to this instance.

## Activate Module Flow

1. Owner opens the module row.
2. A side panel explains capability, dependencies, data changes, and affected navigation.
3. Owner selects **Activate**.
4. UI shows `Applying configuration` while the signed manifest updates.
5. Backend health check verifies the module is actually gated on for that tenant.
6. State becomes `Active`; failure becomes `Needs attention` with safe retry/revert.

The client never sees a partially activated module.

## Update Instance Flow

1. Show current and proposed release plus relevant changes.
2. Run preflight: capacity, backup readiness, migration compatibility, and module health.
3. Owner confirms immediate or scheduled deployment.
4. Show stages: backup, migration, release switch, health checks, complete.
5. If validation fails, show automatic rollback status and retained diagnostic id.

## Client Administration Boundary

GP Andalucia administrators see only:

- Their users and internal permissions.
- PRL Voice knowledge and source status.
- Certificate templates/editor and generation history.
- Their organization profile and allowed branding fields.
- Their usage against visible limits.

They never see instance provisioning, other tenants, provider credentials, release controls, infrastructure paths, or owner audit data.

## Language UX

- Instance default language is controlled by the owner.
- Allowed user language selection lives in the account menu.
- Changing language returns the user to the same page/state.
- Template/document language is selected independently where relevant.
- Missing translations never expose raw keys to end users; production falls back to English while diagnostics record the missing key.

## Accessibility and Interaction Acceptance

- Keyboard access and visible focus for all actions and dialogs.
- Semantic labels and inline field errors.
- Status is never communicated by color alone.
- Reduced-motion preference is respected.
- Touch targets remain usable on mobile.
- Confirmation dialogs return focus correctly.
- Tables collapse into labelled rows rather than horizontal overflow where practical.

