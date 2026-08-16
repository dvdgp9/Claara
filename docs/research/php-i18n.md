# PHP Internationalization Notes

Date reviewed: 2026-08-15  
Official documentation:

- https://www.php.net/manual/en/book.intl.php
- https://www.php.net/manual/en/class.locale.php
- https://www.php.net/manual/en/book.gettext.php

## Production Capability

The production PHP 8.3 runtime currently has `intl`, `gettext`, `mbstring`, and `openssl` available.

## Claara Decision

- Use application-owned keyed PHP dictionaries for deterministic deployments and straightforward JavaScript export.
- Use `intl`/ICU for locale validation and locale-aware dates, numbers, and plural-sensitive formatting.
- Do not depend on OS gettext catalogs for primary UI lookup; this avoids server-locale and compiled-catalog deployment coupling.
- Use stable translation keys, English fallback, placeholder validation, and missing-key diagnostics.
- Keep API error codes language-neutral and localize only display messages.

## Locale Resolution

1. Authenticated user's supported preference.
2. Instance default language.
3. English fallback.

The instance controls which languages are available. UI locale and generated-document/template locale remain separate concepts.

## Migration Notes

- The existing extraction pack contains approximately 1,000 candidate user-facing English strings but is not a runtime translation implementation.
- Begin with shared shell, authentication, account, chat, Voices, and GPAND surfaces.
- Include JavaScript states, API errors, HTML `lang`, email/export text, and AI output-language instructions in acceptance tests.

