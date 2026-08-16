<?php
declare(strict_types=1);

namespace I18n;

use Closure;

final class Translator
{
    private Closure $diagnostic;
    private array $reported = [];

    public function __construct(
        private readonly string $activeLocale,
        private readonly array $dictionaries,
        ?callable $diagnostic = null
    ) {
        $this->diagnostic = $diagnostic !== null
            ? Closure::fromCallable($diagnostic)
            : static fn (string $message): bool => error_log($message);
    }

    public function locale(): string
    {
        return $this->activeLocale;
    }

    public function translate(string $key, array $parameters = []): string
    {
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,159}$/', $key) !== 1) {
            $this->report('invalid-key', $key, 'Translation key is invalid');
            return $key;
        }

        $english = $this->value('en', $key);
        $localized = $this->value($this->activeLocale, $key);

        if ($localized === null) {
            if ($this->activeLocale !== 'en' && $english !== null) {
                $this->report('missing-localized', $key, "Missing {$this->activeLocale} translation");
            }
            $localized = $english;
        }

        if ($localized === null) {
            $this->report('missing-key', $key, 'Missing translation key in active and English dictionaries');
            return $key;
        }

        if ($english !== null && $localized !== $english && $this->placeholders($localized) !== $this->placeholders($english)) {
            $this->report('placeholder-mismatch', $key, "Placeholder mismatch in {$this->activeLocale} translation");
            $localized = $english;
        }

        return $this->interpolate($key, $localized, $parameters);
    }

    public function javascriptCatalog(array $keys): array
    {
        $catalog = [];
        foreach (array_values(array_unique($keys)) as $key) {
            if (is_string($key)) {
                $catalog[$key] = $this->translate($key);
            }
        }
        return $catalog;
    }

    public function javascriptCatalogPrefix(string $prefix): array
    {
        if ($prefix === '' || preg_match('/^[a-z0-9][a-z0-9._-]*$/', $prefix) !== 1) {
            return [];
        }
        $keys = [];
        foreach (['en', $this->activeLocale] as $locale) {
            foreach (array_keys($this->dictionaries[$locale] ?? []) as $key) {
                if (is_string($key) && str_starts_with($key, $prefix)) {
                    $keys[$key] = true;
                }
            }
        }
        $keys = array_keys($keys);
        sort($keys);
        return $this->javascriptCatalog($keys);
    }

    private function value(string $locale, string $key): ?string
    {
        $value = $this->dictionaries[$locale][$key] ?? null;
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function placeholders(string $value): array
    {
        preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', $value, $matches);
        $placeholders = array_values(array_unique($matches[1] ?? []));
        sort($placeholders);
        return $placeholders;
    }

    private function interpolate(string $key, string $value, array $parameters): string
    {
        $replacements = [];
        foreach ($this->placeholders($value) as $placeholder) {
            if (!array_key_exists($placeholder, $parameters)) {
                $this->report('missing-parameter', $key . ':' . $placeholder, "Missing translation parameter {{$placeholder}} for {$key}");
                continue;
            }
            $parameter = $parameters[$placeholder];
            if (!is_scalar($parameter) && !($parameter instanceof \Stringable)) {
                $this->report('invalid-parameter', $key . ':' . $placeholder, "Invalid translation parameter {{$placeholder}} for {$key}");
                continue;
            }
            $replacements['{' . $placeholder . '}'] = (string)$parameter;
        }
        return strtr($value, $replacements);
    }

    private function report(string $type, string $key, string $message): void
    {
        $fingerprint = $type . ':' . $key;
        if (isset($this->reported[$fingerprint])) {
            return;
        }
        $this->reported[$fingerprint] = true;
        ($this->diagnostic)("[i18n] {$message} [locale={$this->activeLocale}, key={$key}]");
    }
}
