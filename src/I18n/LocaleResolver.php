<?php
declare(strict_types=1);

namespace I18n;

final class LocaleResolver
{
    public static function resolve(?string $userPreference, string $instanceDefault, array $allowedLocales): string
    {
        $allowed = [];
        foreach ($allowedLocales as $locale) {
            if (!is_string($locale)) {
                continue;
            }
            $normalized = self::normalize($locale);
            if ($normalized !== null) {
                $allowed[$normalized] = true;
            }
        }

        $preferred = self::normalize($userPreference);
        if ($preferred !== null) {
            if (isset($allowed[$preferred])) {
                return $preferred;
            }
            $base = explode('-', $preferred, 2)[0];
            if (isset($allowed[$base])) {
                return $base;
            }
        }

        $default = self::normalize($instanceDefault);
        if ($default !== null && isset($allowed[$default])) {
            return $default;
        }

        return 'en';
    }

    public static function normalize(?string $locale): ?string
    {
        if ($locale === null) {
            return null;
        }
        $locale = str_replace('_', '-', trim($locale));
        if (preg_match('/^([A-Za-z]{2})(?:-([A-Za-z]{2}))?$/', $locale, $matches) !== 1) {
            return null;
        }
        return strtolower($matches[1]) . (isset($matches[2]) ? '-' . strtoupper($matches[2]) : '');
    }
}
