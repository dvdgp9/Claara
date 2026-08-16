<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function coreRouteCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

$registryPath = $root . '/src/Modules/CoreRouteRegistry.php';
$guardPath = $root . '/src/Modules/CoreModuleGuard.php';
if (!is_file($registryPath) || !is_file($guardPath)) {
    coreRouteCheck(false, 'core route registry and guard exist');
    echo "RESULT {$passed} passed, {$failed} failed\n";
    exit(1);
}

require_once $registryPath;
use Modules\CoreRouteRegistry;

$expectations = [
    '/app/' => 'core.chat',
    '/app.php' => 'core.chat',
    '/api/chat.php' => 'core.chat',
    '/api/chat-stream.php' => 'core.chat',
    '/api/chat/generate-document.php' => 'core.chat',
    '/api/conversations/list.php' => 'core.chat',
    '/api/folders/create.php' => 'core.chat',
    '/api/messages/list.php' => 'core.chat',
    '/api/models/list.php' => 'core.chat',
    '/api/files/upload.php' => 'core.chat',
    '/api/files/serve.php' => 'core.chat',
    '/api/files/document.php' => 'core.chat',
    '/api/flags/create.php' => 'core.chat',
    '/api/capabilities/catalog.php' => 'core.chat',
    '/voices/' => 'core.voices',
    '/voices/view.php' => 'core.voices',
    '/api/voices/catalog.php' => 'core.voices',
    '/api/capabilities/voice-query.php' => 'core.voices',
    '/gestos/' => 'core.gestures',
    '/gestos/lead-finder.php' => 'core.gestures',
    '/api/gestures/history.php' => 'core.gestures',
    '/api/jobs/process.php' => 'core.gestures',
    '/api/files/podcast.php' => 'core.gestures',
    '/connectors.php' => 'core.connectors',
    '/api/connectors/providers.php' => 'core.connectors',
    '/admin/connectors.php' => 'core.connectors',
    '/api/admin/connectors/summary.php' => 'core.connectors',
    '/admin/users.php' => 'core.administration',
    '/api/admin/users/list.php' => 'core.administration',
    '/api/admin/voices/documents/list.php' => 'core.administration',
];
foreach ($expectations as $path => $module) {
    coreRouteCheck(CoreRouteRegistry::moduleForPath($path) === $module, "route owner {$path}");
}

/** @return list<string> */
function publicPhpRoutes(string $root, array $areas): array
{
    $routes = [];
    foreach ($areas as $area) {
        $absolute = $root . '/public/' . $area;
        if (is_file($absolute)) {
            $files = [$absolute];
        } elseif (is_dir($absolute)) {
            $files = [];
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absolute));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php' && !str_starts_with($file->getBasename(), '_')) {
                    $files[] = $file->getPathname();
                }
            }
        } else {
            continue;
        }

        foreach ($files as $file) {
            $relative = str_replace('\\', '/', substr($file, strlen($root . '/public')));
            $routes[] = $relative === '/app/index.php' ? '/app/' : $relative;
        }
    }
    sort($routes);
    return array_values(array_unique($routes));
}

$ownedEndpointAreas = [
    'admin',
    'api/admin',
    'api/connectors',
    'api/voices',
    'voices',
    'api/gestures',
    'gestos',
    'api/jobs',
    'api/conversations',
    'api/folders',
    'api/messages',
    'api/models',
    'api/flags',
    'api/chat',
    'app',
    'app.php',
    'flags.php',
    'api/chat.php',
    'api/chat-stream.php',
    'api/files/upload.php',
    'api/files/serve.php',
    'api/files/document.php',
    'api/files/podcast.php',
    'api/capabilities/catalog.php',
    'api/capabilities/voice-query.php',
    'connectors.php',
];
$unownedEndpoints = [];
$ownedEndpoints = publicPhpRoutes($root, $ownedEndpointAreas);
foreach ($ownedEndpoints as $route) {
    if (CoreRouteRegistry::moduleForPath($route) === null) {
        $unownedEndpoints[] = $route;
    }
}
if ($unownedEndpoints !== []) {
    echo 'UNOWNED ' . implode(', ', $unownedEndpoints) . "\n";
}
coreRouteCheck(
    $ownedEndpoints !== [] && $unownedEndpoints === [],
    'every public core endpoint declares module ownership (' . count($ownedEndpoints) . ' checked)'
);

foreach (['/', '/login.php', '/account.php', '/api/auth/login.php', '/api/account/activity.php', '/api/faq.php'] as $path) {
    coreRouteCheck(CoreRouteRegistry::moduleForPath($path) === null, "module-neutral route {$path}");
}

$bootstrap = file_get_contents($root . '/src/App/bootstrap.php') ?: '';
coreRouteCheck(str_contains($bootstrap, 'CoreModuleGuard::enforceCurrentRequest()'), 'bootstrap enforces the active route centrally');

foreach (['public/includes/left-tabs.php', 'public/includes/bottom-nav.php', 'public/includes/header-unified.php'] as $file) {
    $source = file_get_contents($root . '/' . $file) ?: '';
    coreRouteCheck(str_contains($source, 'isModuleEnabled('), "navigation is module-aware: {$file}");
}

$chatSource = (file_get_contents($root . '/public/api/chat.php') ?: '')
    . (file_get_contents($root . '/public/api/chat-stream.php') ?: '')
    . (file_get_contents($root . '/public/app.php') ?: '');
coreRouteCheck(substr_count($chatSource, 'hasImageGenerationAccess') >= 3, 'image generation keeps its per-user and instance entitlement check');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
