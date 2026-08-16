<?php
declare(strict_types=1);

namespace Modules;

final class CoreRouteRegistry
{
    /** @var list<array{pattern:string,module:string}> */
    private const ROUTES = [
        // More specific routes must precede their broader parent areas.
        ['pattern' => '#^/api/admin/connectors(?:/|\.php|$)#', 'module' => 'core.connectors'],
        ['pattern' => '#^/admin/connectors\.php$#', 'module' => 'core.connectors'],
        ['pattern' => '#^/api/capabilities/voice-query\.php$#', 'module' => 'core.voices'],
        ['pattern' => '#^/api/capabilities/catalog\.php$#', 'module' => 'core.chat'],
        ['pattern' => '#^/api/files/podcast\.php$#', 'module' => 'core.gestures'],
        ['pattern' => '#^/api/files/(?:document|serve|upload)\.php$#', 'module' => 'core.chat'],

        ['pattern' => '#^/app(?:/|\.php)?$#', 'module' => 'core.chat'],
        ['pattern' => '#^/flags\.php$#', 'module' => 'core.chat'],
        ['pattern' => '#^/api/chat(?:[-./]|$)#', 'module' => 'core.chat'],
        ['pattern' => '#^/api/(?:conversations|folders|messages|models|flags)(?:/|$)#', 'module' => 'core.chat'],

        ['pattern' => '#^/voices(?:/|$)#', 'module' => 'core.voices'],
        ['pattern' => '#^/api/voices(?:/|$)#', 'module' => 'core.voices'],

        ['pattern' => '#^/gestos(?:/|$)#', 'module' => 'core.gestures'],
        ['pattern' => '#^/api/gestures(?:/|$)#', 'module' => 'core.gestures'],
        ['pattern' => '#^/api/jobs(?:/|$)#', 'module' => 'core.gestures'],

        ['pattern' => '#^/connectors\.php$#', 'module' => 'core.connectors'],
        ['pattern' => '#^/api/connectors(?:/|$)#', 'module' => 'core.connectors'],

        ['pattern' => '#^/admin(?:/|$)#', 'module' => 'core.administration'],
        ['pattern' => '#^/api/admin(?:/|$)#', 'module' => 'core.administration'],
    ];

    public static function moduleForPath(string $requestPath): ?string
    {
        $path = parse_url($requestPath, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }
        $path = '/' . ltrim(preg_replace('#/+#', '/', $path) ?? $path, '/');

        foreach (self::ROUTES as $route) {
            if (preg_match($route['pattern'], $path) === 1) {
                return $route['module'];
            }
        }
        return null;
    }
}
