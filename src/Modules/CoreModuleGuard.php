<?php
declare(strict_types=1);

namespace Modules;

use App\Response;
use I18n\I18n;

final class CoreModuleGuard
{
    public static function enforceCurrentRequest(): void
    {
        $requestPath = (string)($_SERVER['REQUEST_URI'] ?? '');
        $module = CoreRouteRegistry::moduleForPath($requestPath);
        if ($module === null || ModuleEntitlementService::current()->isModuleEnabled($module)) {
            return;
        }

        $path = parse_url($requestPath, PHP_URL_PATH);
        if (is_string($path) && str_starts_with($path, '/api/')) {
            Response::error('feature_unavailable', I18n::translate('access.feature_unavailable'), 404);
        }

        header('Location: /account.php?error=feature_unavailable', true, 302);
        exit;
    }
}
