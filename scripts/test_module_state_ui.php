<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$passed = 0;
$failed = 0;

function moduleUiCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

$requiredFiles = [
    'src/Modules/ModulePresentationState.php',
    'src/Modules/ModuleStateResolver.php',
    'src/Modules/ModuleCatalogPresenter.php',
    'resources/views/owner/module-catalog.php',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        moduleUiCheck(false, "module-state UI file exists: {$file}");
    }
}
if ($failed > 0) {
    echo "RESULT {$passed} passed, {$failed} failed\n";
    exit(1);
}

require_once $root . '/src/Modules/ModuleConfigurationException.php';
require_once $root . '/src/Modules/ModuleDefinition.php';
require_once $root . '/src/Modules/ModuleRegistry.php';
require_once $root . '/src/Modules/ModulePresentationState.php';
require_once $root . '/src/Modules/ModuleStateResolver.php';
require_once $root . '/src/Modules/ModuleCatalogPresenter.php';

use Modules\ModuleCatalogPresenter;
use Modules\ModulePresentationState;
use Modules\ModuleRegistry;

$registry = ModuleRegistry::defaults();
$presenter = new ModuleCatalogPresenter($registry, [
    'core.chat',
    'core.connectors',
    'core.administration',
]);
$items = $presenter->present([
    'core.connectors' => ['deployment_pending' => true],
    'core.administration' => ['health' => 'needs_attention'],
    'gesture.course-creator' => ['requested_enabled' => true],
    'feature.image-generation' => ['available' => false],
]);
$bySlug = [];
foreach ($items as $item) {
    $bySlug[$item['slug']] = $item;
}

moduleUiCheck($bySlug['core.chat']['state'] === ModulePresentationState::ACTIVE, 'effective module resolves active');
moduleUiCheck($bySlug['core.voices']['state'] === ModulePresentationState::INACTIVE, 'disabled module resolves inactive');
moduleUiCheck($bySlug['core.connectors']['state'] === ModulePresentationState::APPLYING, 'pending module resolves applying');
moduleUiCheck($bySlug['core.connectors']['is_effectively_active'] === true, 'applying state preserves truthful effective access');
moduleUiCheck($bySlug['core.administration']['state'] === ModulePresentationState::NEEDS_ATTENTION, 'unhealthy enabled module needs attention');
moduleUiCheck($bySlug['gesture.course-creator']['state'] === ModulePresentationState::DEPENDENCY_REQUIRED, 'requested module reports missing dependency');
moduleUiCheck($bySlug['gesture.course-creator']['missing_dependency_keys'] === ['modules.core_gestures.name'], 'dependency uses localized module metadata');
moduleUiCheck($bySlug['feature.image-generation']['state'] === ModulePresentationState::UNAVAILABLE, 'release-unavailable module resolves unavailable');

$activeItems = array_values(array_filter($items, static fn(array $item): bool => $item['state'] === ModulePresentationState::ACTIVE));
moduleUiCheck(count($activeItems) === 1 && $activeItems[0]['slug'] === 'core.chat', 'Active is shown only for effective instance state');

$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$stateKeys = [];
foreach (ModulePresentationState::all() as $state) {
    $stateKeys[] = ModulePresentationState::labelKey($state);
    $stateKeys[] = ModulePresentationState::descriptionKey($state);
}
foreach (array_merge($stateKeys, [
    'module_ui.catalog_title',
    'module_ui.catalog_description',
    'module_ui.details',
    'module_ui.dependencies',
    'module_ui.empty_title',
    'module_ui.empty_description',
    'module_ui.loading',
    'module_ui.error_title',
]) as $key) {
    moduleUiCheck(isset($en[$key], $es[$key]), "localized module UI key exists: {$key}");
}

$moduleCatalogItems = $items;
$moduleCatalogTranslate = static fn(string $key, array $parameters = []): string => strtr(
    (string)($en[$key] ?? "[{$key}]"),
    array_combine(
        array_map(static fn(string $name): string => '{' . $name . '}', array_keys($parameters)),
        array_map(static fn(mixed $value): string => (string)$value, array_values($parameters))
    ) ?: []
);
ob_start();
require $root . '/resources/views/owner/module-catalog.php';
$html = (string)ob_get_clean();

foreach (ModulePresentationState::all() as $state) {
    moduleUiCheck(str_contains($html, 'data-state="' . $state . '"'), "renderer exposes {$state} state");
}
moduleUiCheck(str_contains($html, '<details'), 'module rows provide keyboard-native progressive disclosure');
moduleUiCheck(str_contains($html, 'aria-label='), 'module state badges expose an accessible label');
moduleUiCheck(!str_contains($html, '<style'), 'module renderer contains no inline CSS');
moduleUiCheck(!preg_match('/[😀-🙏🌀-🫿]/u', $html), 'module renderer contains no emoji');

$moduleCatalogItems = [];
$moduleCatalogLoading = true;
ob_start();
require $root . '/resources/views/owner/module-catalog.php';
$loadingHtml = (string)ob_get_clean();
moduleUiCheck(str_contains($loadingHtml, 'aria-busy="true"'), 'renderer has a semantic loading state');

$moduleCatalogLoading = false;
$moduleCatalogError = 'Synthetic error';
ob_start();
require $root . '/resources/views/owner/module-catalog.php';
$errorHtml = (string)ob_get_clean();
moduleUiCheck(str_contains($errorHtml, 'role="alert"'), 'renderer has an inline error state');

$moduleCatalogError = null;
ob_start();
require $root . '/resources/views/owner/module-catalog.php';
$emptyHtml = (string)ob_get_clean();
moduleUiCheck(str_contains($emptyHtml, 'module-state-empty'), 'renderer has an informative empty state');

$css = file_get_contents($root . '/public/assets/css/styles.css') ?: '';
moduleUiCheck(str_contains($css, '.module-state-catalog'), 'module-state styles are scoped in styles.css');
moduleUiCheck(str_contains($css, '@media (prefers-reduced-motion: reduce)'), 'module-state motion respects reduced-motion preference');
moduleUiCheck(str_contains($css, '@media (max-width: 767px)'), 'module-state layout has a mobile single-column rule');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
