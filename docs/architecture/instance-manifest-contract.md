# Instance Manifest and Runtime Context

Status: Implemented in Phase 2  
Date: 2026-08-15

## Startup Boundary

Every web or CLI request now performs this sequence:

1. Load deployment secrets from `.env`.
2. Load and validate one non-secret instance manifest.
3. Normalize the direct `Host` header without trusting forwarded host values.
4. Require the host to belong to the active manifest and require `status=active`.
5. Activate one immutable `InstanceContext`.
6. Only then initialize session storage, authentication, database access, or application repositories.

Unknown hosts return stable error `instance_not_found` with HTTP 421 before a session cookie is created. Suspended instances return `instance_unavailable` with HTTP 503. Invalid manifests return a generic 500 response while the specific diagnostic is written to the server log.

## Manifest Contents

The version-1 JSON contract contains only:

- Instance id, slug, lifecycle status, canonical domain, and allowed domains.
- Product/organization branding and asset paths.
- Default and allowed locales.
- Enabled module slugs.
- Numeric plan limits.
- Release channel/id.
- References to database, storage, RAG, session, secret, and backup scopes.

It never contains passwords, API keys, connector credentials, customer content, or database rows. Environment references are strictly validated before use.

The current Claara deployment uses `config/instances/default.json`. A local example is provided at `config/instances/local.example.json`.

## Resource Resolution

- Database values are loaded only from the manifest's environment prefix, such as `DB_HOST`, `DB_NAME`, and `DB_USER` for the default instance.
- Storage paths are loaded from the assigned path variable and must be absolute. All application storage consumers use `App\Storage`, which rejects parent traversal.
- PHP session files live below the active instance storage root; cookie names use the manifest session namespace and remain host-only.
- Qdrant endpoint/API-key values use the assigned environment prefix. Every collection operation automatically adds the instance collection prefix.
- Secrets and backup scopes are stable identifiers for later provisioning/control-plane enforcement; values are not exposed through the context.

## Current Default Compatibility

The default manifest deliberately retains:

- `claara.tech` and `www.claara.tech`.
- English default with English/Spanish allowed.
- Existing Claara branding.
- Empty Qdrant collection prefix, preserving `lex_knowledge_base` and `voice_conveniex`.
- Session namespace `2dfa7c360aec`, preserving the host-derived cookie name introduced in Phase 1.
- Existing database environment prefix `DB` and current storage root fallback.

## Future Control Plane

The owner control plane will generate and distribute these manifests. Signature verification, audit/version metadata, deployment propagation, and module enforcement are intentionally separate later phases; this runtime contract establishes the fail-closed boundary they will control.
