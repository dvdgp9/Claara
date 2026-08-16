<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/Instances/InstanceException.php';
require_once $root . '/src/Instances/InstanceManifest.php';
require_once $root . '/src/Instances/InstanceResources.php';
require_once $root . '/src/Instances/InstanceContext.php';
require_once $root . '/src/Modules/ModuleConfigurationException.php';
require_once $root . '/src/Modules/ModuleDefinition.php';
require_once $root . '/src/Modules/ModuleRegistry.php';

$servicePath = $root . '/src/Modules/ModuleEntitlementService.php';
$failed = 0;
$passed = 0;

function entitlementCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

if (!is_file($servicePath)) {
    entitlementCheck(false, 'Module entitlement service exists');
    echo "RESULT {$passed} passed, {$failed} failed\n";
    exit(1);
}

require_once $servicePath;
require_once $root . '/src/Repos/UserFeatureAccessRepo.php';
require_once $root . '/src/Gestures/GestureAccessGuard.php';

use Gestures\GestureAccessGuard;
use Instances\InstanceContext;
use Instances\InstanceManifest;
use Modules\ModuleEntitlementService;
use Modules\ModuleRegistry;
use Repos\UserFeatureAccessRepo;

/** @param list<string> $modules */
function entitlementContext(array $modules): InstanceContext
{
    return new InstanceContext(InstanceManifest::fromArray([
        'schema_version' => 1,
        'id' => 'test',
        'slug' => 'test',
        'status' => 'active',
        'canonical_domain' => 'test.claara.tech',
        'domains' => ['test.claara.tech'],
        'branding' => [
            'product_name' => 'Claara',
            'organization_name' => 'Test',
            'logo_path' => '/assets/images/logo.png',
            'login_logo_path' => '/assets/images/claara-logo.png',
            'accent_color' => '#FF8B73',
        ],
        'locales' => ['default' => 'en', 'allowed' => ['en', 'es']],
        'modules' => ['enabled' => $modules],
        'limits' => ['users' => 5, 'storage_bytes' => 1000],
        'release' => ['channel' => 'test', 'id' => 'test'],
        'resources' => [
            'database' => ['env_prefix' => 'TEST_DB'],
            'storage' => ['path_env' => 'TEST_STORAGE_ROOT'],
            'rag' => ['env_prefix' => 'TEST_QDRANT', 'collection_prefix' => 'test__'],
            'session' => ['namespace' => 'abcdef123456'],
            'secrets' => ['scope' => 'test'],
            'backups' => ['scope' => 'test'],
        ],
    ]), 'test.claara.tech');
}

$entitlements = new ModuleEntitlementService(
    entitlementContext(['core.gestures', 'core.voices', 'gesture.lead-finder']),
    ModuleRegistry::defaults()
);

entitlementCheck($entitlements->isCapabilityEnabled('gesture', 'lead-finder'), 'enabled Gesture capability is entitled');
entitlementCheck(!$entitlements->isCapabilityEnabled('gesture', 'podcast-from-article'), 'disabled Gesture capability is denied');
entitlementCheck($entitlements->isCapabilityEnabled('voice', 'lex'), 'Voice wildcard follows core Voices entitlement');
entitlementCheck(!$entitlements->isCapabilityEnabled('feature', 'image-generation'), 'disabled cross-cutting feature is denied');
entitlementCheck(!$entitlements->isCapabilityEnabled('feature', 'not-registered'), 'unknown capability fails closed');

$registry = ModuleRegistry::defaults();
$defaultManifest = InstanceManifest::fromFile($root . '/config/instances/default.json');
$defaultEntitlements = new ModuleEntitlementService(
    new InstanceContext($defaultManifest, 'claara.tech'),
    $registry
);
$defaultModuleSlugs = array_map(
    static fn($definition): string => $definition->slug(),
    $registry->all()
);
entitlementCheck(
    array_reduce(
        $defaultModuleSlugs,
        static fn(bool $enabled, string $slug): bool => $enabled && $defaultEntitlements->isModuleEnabled($slug),
        true
    ),
    'default instance preserves access to every currently shipped module'
);

if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
    echo "SKIP repository integration checks require the PDO SQLite test driver\n";
    echo "RESULT {$passed} passed, {$failed} failed, 1 skipped\n";
    exit($failed === 0 ? 0 : 1);
}

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT, first_name TEXT, last_name TEXT, is_superadmin INTEGER, status TEXT)');
$pdo->exec('CREATE TABLE user_feature_access (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, feature_type TEXT, feature_slug TEXT, enabled INTEGER, created_at TEXT DEFAULT CURRENT_TIMESTAMP, updated_at TEXT DEFAULT CURRENT_TIMESTAMP, UNIQUE(user_id, feature_type, feature_slug))');
$pdo->exec('CREATE TABLE available_features (id INTEGER PRIMARY KEY AUTOINCREMENT, feature_type TEXT, feature_slug TEXT, name TEXT, description TEXT, icon TEXT, sort_order INTEGER, is_active INTEGER, route TEXT, trigger_guidance TEXT, input_schema TEXT)');
$pdo->exec("INSERT INTO users VALUES (1, 'normal@example.invalid', 'Normal', 'User', 0, 'active'), (2, 'admin@example.invalid', 'Admin', 'User', 1, 'active'), (3, 'new@example.invalid', 'New', 'User', 0, 'active')");
$pdo->exec("INSERT INTO available_features (feature_type, feature_slug, name, description, icon, sort_order, is_active) VALUES
    ('gesture', 'lead-finder', 'Lead Finder', '', '', 1, 1),
    ('gesture', 'podcast-from-article', 'Podcast', '', '', 2, 1),
    ('voice', 'lex', 'Lex', '', '', 1, 1),
    ('feature', 'image-generation', 'Images', '', '', 1, 1)");
$pdo->exec("INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled) VALUES
    (1, 'gesture', 'lead-finder', 1),
    (1, 'gesture', 'podcast-from-article', 1),
    (1, 'voice', 'lex', 1),
    (2, 'gesture', 'podcast-from-article', 1)");

$repo = new UserFeatureAccessRepo($pdo, $entitlements);
entitlementCheck($repo->hasGestureAccess(1, 'lead-finder'), 'normal user with grant and entitlement is allowed');
entitlementCheck(!$repo->hasGestureAccess(1, 'podcast-from-article'), 'normal user grant cannot bypass instance entitlement');
entitlementCheck($repo->hasVoiceAccess(1, 'lex'), 'normal Voice grant works when module is enabled');
entitlementCheck($repo->hasGestureAccess(2, 'lead-finder'), 'superadmin bypasses the user grant only');
entitlementCheck(!$repo->hasGestureAccess(2, 'podcast-from-article'), 'superadmin cannot bypass instance entitlement');

$available = array_map(
    static fn(array $row): string => $row['feature_type'] . ':' . $row['feature_slug'],
    $repo->getAvailableFeatures()
);
sort($available);
entitlementCheck($available === ['gesture:lead-finder', 'voice:lex'], 'disabled capabilities are omitted from permission administration');
entitlementCheck(!$repo->setAccess(1, 'gesture', 'podcast-from-article', true), 'disabled capability cannot be granted');
entitlementCheck(!$repo->setAccess(1, 'feature', 'not-registered', true), 'unknown capability cannot be granted');
entitlementCheck(!$repo->setAccess(1, 'feature', 'not-registered', false), 'unknown capability cannot create a disabled permission row');

$normalGestures = array_column($repo->getAccessibleGestures(1), 'feature_slug');
$adminGestures = array_column($repo->getAccessibleGestures(2), 'feature_slug');
entitlementCheck($normalGestures === ['lead-finder'], 'normal Gesture catalog composes entitlement and grant');
entitlementCheck($adminGestures === ['lead-finder'], 'superadmin Gesture catalog remains instance-scoped');

entitlementCheck($repo->grantDefaultAccessForNewUser(3), 'new-user defaults skip capabilities disabled for the instance');
$newUserAccess = array_keys(array_filter($repo->getUserAccess(3)));
sort($newUserAccess);
entitlementCheck($newUserAccess === ['gesture:lead-finder', 'voice:lex'], 'new user receives only entitled defaults');

$gestureGuard = new GestureAccessGuard($repo, $entitlements);
$visibleExecutions = $gestureGuard->filterExecutions(['id' => 1], [
    ['id' => 1, 'gesture_type' => 'lead-finder'],
    ['id' => 2, 'gesture_type' => 'podcast-from-article'],
    ['id' => 3, 'gesture_type' => 'not-registered'],
]);
entitlementCheck(array_column($visibleExecutions, 'id') === [1], 'mixed Gesture history hides disabled and unknown modules');

$capturedLeadInput = GestureAccessGuard::captureJobRequirement('lead-finder', ['required_module' => 'gesture.podcast-from-article']);
entitlementCheck(($capturedLeadInput['required_module'] ?? null) === 'gesture.lead-finder', 'job creation overwrites client-supplied module ownership');
$visibleJobs = $gestureGuard->filterJobs(['id' => 1], [
    ['id' => 1, 'user_id' => 1, 'job_type' => 'lead-finder', 'input_data' => $capturedLeadInput],
    ['id' => 2, 'user_id' => 1, 'job_type' => 'podcast', 'input_data' => ['required_module' => 'gesture.podcast-from-article']],
    ['id' => 3, 'user_id' => 1, 'job_type' => 'lead-finder', 'input_data' => ['required_module' => 'gesture.podcast-from-article']],
]);
entitlementCheck(array_column($visibleJobs, 'id') === [1], 'job listing enforces entitlement and rejects captured-module mismatch');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
