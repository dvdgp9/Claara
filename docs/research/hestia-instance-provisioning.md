# HestiaCP Instance Provisioning Notes

Date reviewed: 2026-08-15  
Official documentation:

- https://hestiacp.com/docs/reference/cli
- https://hestiacp.com/docs/server-administration/web-templates
- https://hestiacp.com/docs/user-guide/web-domains

## Relevant Capabilities

- Hestia provides CLI operations for web domains, aliases, backends, document roots, SSL, and user configuration.
- Hestia supports custom web templates and custom document roots.
- Default templates are overwritten during rebuilds/updates; custom templates must be copied under a new name rather than editing defaults.
- Hestia supports allowing other Hestia users to own subdomains of a parent domain.

## Claara Decision

- Keep Hestia for the first instance platform iteration.
- Use a separate Hestia/system identity per tenant where practical to strengthen filesystem/PHP isolation.
- Use a copied custom template or supported custom document root for a versioned `current/public` release path.
- Provisioning automation must call fixed Hestia CLI operations through a restricted worker, never arbitrary shell supplied by the web panel.
- Validate every domain, user, path, template, and release identifier before calling Hestia.

## Verification Required in Staging

- Subdomain ownership under `claara.tech` for a separate Hestia user.
- Custom document root survives rebuild and Hestia update.
- TLS issuance/renewal and HSTS.
- PHP-FPM pool Unix identity and cross-user filesystem denial.
- Atomic release switch and rollback behavior.

