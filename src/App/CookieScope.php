<?php
namespace App;

use Instances\InstanceContext;

final class CookieScope
{
    private const NAME_HASH_LENGTH = 12;

    public static function host(): string
    {
        $requestHost = self::normalizeHost((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($requestHost !== null) {
            return $requestHost;
        }

        $appUrl = (string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '');
        $configuredHost = self::normalizeHost((string)(parse_url($appUrl, PHP_URL_HOST) ?: ''));

        return $configuredHost ?? 'localhost';
    }

    public static function sessionName(): string
    {
        return self::cookieName('claara_session');
    }

    public static function rememberName(): string
    {
        return self::cookieName('claara_remember');
    }

    public static function isHttps(): bool
    {
        $forwardedProto = strtolower(trim((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')));
        $appUrl = (string)($_ENV['APP_URL'] ?? getenv('APP_URL') ?: '');
        $configuredScheme = strtolower((string)(parse_url($appUrl, PHP_URL_SCHEME) ?: ''));

        return (!empty($_SERVER['HTTPS']) && strtolower((string)$_SERVER['HTTPS']) !== 'off')
            || $forwardedProto === 'https'
            || $configuredScheme === 'https';
    }

    public static function sessionOptions(int $lifetime): array
    {
        return [
            'lifetime' => max(0, $lifetime),
            'path' => '/',
            // An empty domain makes PHP emit a host-only cookie.
            'domain' => '',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public static function persistentOptions(int $lifetime, ?int $now = null): array
    {
        return [
            'expires' => ($now ?? time()) + max(0, $lifetime),
            'path' => '/',
            // Deliberately omit Domain so the cookie is host-only.
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    public static function expiredOptions(): array
    {
        return [
            'expires' => time() - 86400,
            'path' => '/',
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ];
    }

    /**
     * Remove cookies created by the previous base-domain policy. These names are
     * never used for authentication after the migration.
     */
    public static function clearLegacyCookies(): void
    {
        foreach (['claara_session', 'claara_remember'] as $legacyName) {
            if (!array_key_exists($legacyName, $_COOKIE)) {
                continue;
            }

            setcookie($legacyName, '', self::expiredOptions());

            $host = self::host();
            if ($host === 'claara.tech' || str_ends_with($host, '.claara.tech')) {
                $parentOptions = self::expiredOptions();
                $parentOptions['domain'] = 'claara.tech';
                setcookie($legacyName, '', $parentOptions);
            }
        }
    }

    private static function cookieName(string $prefix): string
    {
        $context = class_exists(InstanceContext::class) ? InstanceContext::currentOrNull() : null;
        $suffix = $context !== null
            ? $context->resources()->sessionNamespace()
            : substr(hash('sha256', self::host()), 0, self::NAME_HASH_LENGTH);
        return $prefix . '_' . $suffix;
    }

    private static function normalizeHost(string $value): ?string
    {
        $value = strtolower(trim($value));
        if ($value === '' || preg_match('/[\x00-\x20\x7f\/@]/', $value)) {
            return null;
        }

        $host = parse_url('http://' . $value, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }

        $host = strtolower(rtrim($host, '.'));
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }

        if (strlen($host) > 253 || preg_match('/^[a-z0-9](?:[a-z0-9.-]*[a-z0-9])?$/', $host) !== 1) {
            return null;
        }

        foreach (explode('.', $host) as $label) {
            if ($label === '' || strlen($label) > 63 || $label[0] === '-' || str_ends_with($label, '-')) {
                return null;
            }
        }

        return $host;
    }
}
