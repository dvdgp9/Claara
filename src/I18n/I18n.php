<?php
declare(strict_types=1);

namespace I18n;

use Instances\InstanceContext;
use RuntimeException;

final class I18n
{
    private static ?Translator $translator = null;

    public static function boot(InstanceContext $instance, string $dictionaryDirectory, ?string $userPreference = null): void
    {
        $locale = LocaleResolver::resolve($userPreference, $instance->defaultLocale(), $instance->allowedLocales());
        $dictionaries = [];
        foreach (array_values(array_unique(array_merge(['en'], $instance->allowedLocales()))) as $allowedLocale) {
            $normalized = LocaleResolver::normalize(is_string($allowedLocale) ? $allowedLocale : null);
            if ($normalized === null) {
                continue;
            }
            $path = rtrim($dictionaryDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $normalized . '.php';
            if (!is_file($path) || !is_readable($path)) {
                error_log("[i18n] Dictionary unavailable [locale={$normalized}]");
                continue;
            }
            $dictionary = require $path;
            if (!is_array($dictionary)) {
                throw new RuntimeException("Invalid translation dictionary: {$normalized}");
            }
            $dictionaries[$normalized] = $dictionary;
        }
        if (!isset($dictionaries['en'])) {
            throw new RuntimeException('English fallback dictionary is unavailable');
        }
        self::$translator = new Translator($locale, $dictionaries);
    }

    public static function locale(): string
    {
        return self::translator()->locale();
    }

    public static function htmlLang(): string
    {
        return self::locale();
    }

    public static function translate(string $key, array $parameters = []): string
    {
        return self::translator()->translate($key, $parameters);
    }

    public static function javascriptCatalog(array $keys): array
    {
        return self::translator()->javascriptCatalog($keys);
    }

    public static function javascriptCatalogJson(array $keys): string
    {
        return json_encode(
            ['locale' => self::locale(), 'messages' => self::javascriptCatalog($keys)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    public static function javascriptCatalogPrefixJson(string $prefix): string
    {
        return json_encode(
            ['locale' => self::locale(), 'messages' => self::translator()->javascriptCatalogPrefix($prefix)],
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );
    }

    private static function translator(): Translator
    {
        if (self::$translator === null) {
            throw new RuntimeException('Internationalization has not been initialized');
        }
        return self::$translator;
    }
}
