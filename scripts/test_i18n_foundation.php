<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/I18n/LocaleResolver.php';
require_once dirname(__DIR__) . '/src/I18n/Translator.php';
require_once dirname(__DIR__) . '/src/I18n/I18n.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceException.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceManifest.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceResources.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceContext.php';

use I18n\I18n;
use I18n\LocaleResolver;
use I18n\Translator;
use Instances\InstanceContext;
use Instances\InstanceManifest;

$passed = 0;
$failed = 0;

function check(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

check(LocaleResolver::resolve(null, 'en', ['en', 'es']) === 'en', 'instance default is used without user preference');
check(LocaleResolver::resolve('es', 'en', ['en', 'es']) === 'es', 'supported user preference wins');
check(LocaleResolver::resolve('es-ES', 'en', ['en', 'es']) === 'es', 'regional preference can use an allowed base locale');
check(LocaleResolver::resolve('fr', 'es', ['en', 'es']) === 'es', 'unsupported preference falls back to instance default');
check(LocaleResolver::resolve('fr', 'fr', ['es']) === 'en', 'English is the final safe fallback');

$diagnostics = [];
$translator = new Translator(
    'es',
    [
        'en' => [
            'auth.login.title' => 'Log in',
            'welcome' => 'Welcome, {name}',
            'fallback.only' => 'English fallback',
            'invalid.placeholders' => 'Hello, {name}',
        ],
        'es' => [
            'auth.login.title' => 'Iniciar sesión',
            'welcome' => 'Te damos la bienvenida, {name}',
            'invalid.placeholders' => 'Hola',
        ],
    ],
    static function (string $message) use (&$diagnostics): void {
        $diagnostics[] = $message;
    }
);

check($translator->locale() === 'es', 'translator exposes resolved locale');
check($translator->translate('auth.login.title') === 'Iniciar sesión', 'Spanish dictionary value is returned');
check($translator->translate('welcome', ['name' => 'David']) === 'Te damos la bienvenida, David', 'named placeholders are interpolated');
check($translator->translate('fallback.only') === 'English fallback', 'missing Spanish key falls back to English');
check($translator->translate('missing.everywhere') === 'missing.everywhere', 'missing key fails visibly and safely');
check($translator->translate('invalid.placeholders', ['name' => 'David']) === 'Hello, David', 'placeholder mismatch falls back to English');
check($translator->translate('welcome', ['name' => '<David>']) === 'Te damos la bienvenida, <David>', 'translator does not silently alter caller data');

$js = $translator->javascriptCatalog(['auth.login.title', 'fallback.only', 'missing.everywhere']);
check($js === [
    'auth.login.title' => 'Iniciar sesión',
    'fallback.only' => 'English fallback',
    'missing.everywhere' => 'missing.everywhere',
], 'JavaScript catalog uses the same fallback rules');
check($translator->javascriptCatalogPrefix('auth.') === ['auth.login.title' => 'Iniciar sesión'], 'JavaScript catalog can export one translation namespace');

check(count(array_filter($diagnostics, static fn (string $message): bool => str_contains($message, 'missing.everywhere'))) === 1, 'missing keys are diagnosed once');
check(count(array_filter($diagnostics, static fn (string $message): bool => str_contains($message, 'invalid.placeholders'))) === 1, 'placeholder mismatches are diagnosed once');

$manifest = InstanceManifest::fromArray([
    'schema_version' => 1,
    'id' => 'spanish-test',
    'slug' => 'spanish-test',
    'status' => 'active',
    'canonical_domain' => 'spanish.example.invalid',
    'domains' => ['spanish.example.invalid'],
    'branding' => [
        'product_name' => 'Claara',
        'organization_name' => 'Synthetic Spanish Test',
        'logo_path' => '/assets/images/logo.png',
        'login_logo_path' => '/assets/images/claara-logo.png',
        'accent_color' => '#FF8B73',
    ],
    'locales' => ['default' => 'es', 'allowed' => ['en', 'es']],
    'modules' => ['enabled' => ['core.chat']],
    'limits' => ['users' => 2],
    'release' => ['channel' => 'test', 'id' => 'i18n-foundation'],
    'resources' => [
        'database' => ['env_prefix' => 'TEST_DB'],
        'storage' => ['path_env' => 'TEST_STORAGE_ROOT'],
        'rag' => ['env_prefix' => 'TEST_QDRANT', 'collection_prefix' => 'spanish_test__'],
        'session' => ['namespace' => 'a12b34c56d78'],
        'secrets' => ['scope' => 'spanish-test'],
        'backups' => ['scope' => 'spanish-test'],
    ],
]);
I18n::boot(
    new InstanceContext($manifest, 'spanish.example.invalid'),
    dirname(__DIR__) . '/resources/i18n'
);
check(I18n::locale() === 'es', 'instance policy boots the Spanish locale');
check(I18n::htmlLang() === 'es', 'HTML language helper follows the resolved locale');
check(I18n::translate('auth.login.title') === 'Iniciar sesión', 'facade loads the application Spanish dictionary');
$browserCatalog = json_decode(I18n::javascriptCatalogJson(['auth.login.title', 'common.loading']), true);
check(($browserCatalog['locale'] ?? null) === 'es', 'browser catalog exposes its locale');
check(($browserCatalog['messages']['common.loading'] ?? null) === 'Cargando…', 'browser catalog exports translated JavaScript states');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
