<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Modules/ModuleConfigurationException.php';
require_once $root . '/src/Modules/ModuleDefinition.php';
require_once $root . '/src/Modules/ModuleRegistry.php';

use Modules\ModuleConfigurationException;
use Modules\ModuleDefinition;
use Modules\ModuleRegistry;

$passed = 0;
$failed = 0;

function moduleCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

function moduleExpectFailure(string $label, callable $callback): void
{
    try {
        $callback();
        moduleCheck(false, $label);
    } catch (Throwable $error) {
        moduleCheck($error instanceof ModuleConfigurationException, $label);
    }
}

function definition(string $slug, array $dependencies = [], array $capabilities = []): ModuleDefinition
{
    return new ModuleDefinition(
        $slug,
        'modules.test.name',
        'modules.test.description',
        false,
        $dependencies,
        $capabilities,
        [],
        [],
        null
    );
}

$registry = ModuleRegistry::defaults();
$expected = [
    'core.administration',
    'core.chat',
    'core.connectors',
    'core.gestures',
    'core.voices',
    'feature.image-generation',
    'gesture.audio-transcriber',
    'gesture.content-repurposer',
    'gesture.course-creator',
    'gesture.image-editor',
    'gesture.lead-finder',
    'gesture.podcast-from-article',
    'gesture.project-admin',
    'gesture.social-media',
    'gesture.sop-generator',
    'gesture.write-article',
];
$actual = array_map(static fn(ModuleDefinition $module): string => $module->slug(), $registry->all());
sort($actual);
moduleCheck($actual === $expected, 'default catalog registers every current module exactly once');

$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
foreach ($registry->all() as $module) {
    moduleCheck(isset($en[$module->nameKey()], $es[$module->nameKey()]), "localized module name exists: {$module->slug()}");
    moduleCheck(isset($en[$module->descriptionKey()], $es[$module->descriptionKey()]), "localized module description exists: {$module->slug()}");
}

$lead = $registry->require('gesture.lead-finder');
moduleCheck($lead->dependencies() === ['core.gestures'], 'Lead Finder declares the Gestures framework dependency');
moduleCheck($lead->capabilities() === ['gesture:lead-finder'], 'Lead Finder owns its existing capability');
moduleCheck($lead->defaultEnabled() === false, 'optional modules are disabled by default');
moduleCheck(($lead->navigation()['route'] ?? null) === '/gestos/lead-finder.php', 'Lead Finder declares navigation metadata');
moduleCheck($registry->moduleForCapability('gesture', 'lead-finder')?->slug() === 'gesture.lead-finder', 'exact capability resolves to its module');
moduleCheck($registry->moduleForCapability('voice', 'lex')?->slug() === 'core.voices', 'dynamic Voice capability resolves through wildcard ownership');
moduleCheck($registry->moduleForCapability('gesture', 'not-registered') === null, 'unknown capability fails closed');

$enabled = ['gesture.lead-finder', 'core.gestures', 'core.chat'];
$registry->validateEnabledModules($enabled);
moduleCheck(
    $registry->orderedEnabled($enabled) === ['core.chat', 'core.gestures', 'gesture.lead-finder'],
    'enabled modules are returned in deterministic dependency-safe order'
);

moduleExpectFailure('unknown enabled module is rejected', fn() => $registry->validateEnabledModules(['core.chat', 'unknown.module']));
moduleExpectFailure('duplicate enabled module is rejected', fn() => $registry->validateEnabledModules(['core.chat', 'core.chat']));
moduleExpectFailure('missing enabled dependency is rejected', fn() => $registry->validateEnabledModules(['gesture.lead-finder']));
moduleExpectFailure('duplicate module definitions are rejected', fn() => new ModuleRegistry([definition('test.alpha'), definition('test.alpha')]));
moduleExpectFailure('unknown definition dependency is rejected', fn() => new ModuleRegistry([definition('test.alpha', ['test.missing'])]));
moduleExpectFailure('duplicate capability ownership is rejected', fn() => new ModuleRegistry([
    definition('test.alpha', [], ['feature:shared']),
    definition('test.beta', [], ['feature:shared']),
]));
moduleExpectFailure('dependency cycles are rejected', fn() => new ModuleRegistry([
    definition('test.alpha', ['test.beta']),
    definition('test.beta', ['test.alpha']),
]));

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
