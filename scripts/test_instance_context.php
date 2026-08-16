<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Instances/InstanceException.php';
require_once dirname(__DIR__) . '/src/Modules/ModuleConfigurationException.php';
require_once dirname(__DIR__) . '/src/Modules/ModuleDefinition.php';
require_once dirname(__DIR__) . '/src/Modules/ModuleRegistry.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceManifest.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceResources.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceContext.php';
require_once dirname(__DIR__) . '/src/Instances/InstanceResolver.php';
require_once dirname(__DIR__) . '/src/App/Env.php';
require_once dirname(__DIR__) . '/src/App/Storage.php';
require_once dirname(__DIR__) . '/src/App/CookieScope.php';
require_once dirname(__DIR__) . '/src/Rag/QdrantClient.php';

use Instances\InstanceConfigurationException;
use Instances\InstanceManifest;
use Instances\InstanceResolver;
use Instances\InstanceUnavailableException;
use Instances\UnknownInstanceException;
use Instances\InstanceContext;
use App\CookieScope;
use App\Storage;
use Rag\QdrantClient;

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        fwrite(STDOUT, "[PASS] {$label}\n");
        return;
    }
    $failed++;
    fwrite(STDERR, "[FAIL] {$label}\n");
}

function expectException(string $label, string $class, callable $callback): void
{
    try {
        $callback();
        check($label, false);
    } catch (Throwable $error) {
        check($label, $error instanceof $class);
    }
}

function manifestData(string $slug = 'default', array $overrides = []): array
{
    $base = [
        'schema_version' => 1,
        'id' => $slug,
        'slug' => $slug,
        'status' => 'active',
        'canonical_domain' => $slug === 'default' ? 'claara.tech' : $slug . '.claara.tech',
        'domains' => $slug === 'default' ? ['claara.tech', 'www.claara.tech'] : [$slug . '.claara.tech'],
        'branding' => [
            'product_name' => 'Claara',
            'organization_name' => $slug === 'default' ? 'Grupo Ebone' : 'Synthetic Tenant',
            'logo_path' => '/assets/images/logo.png',
            'login_logo_path' => '/assets/images/claara-logo.png',
            'accent_color' => '#FF8B73',
        ],
        'locales' => ['default' => 'en', 'allowed' => ['en', 'es']],
        'modules' => ['enabled' => ['core.chat', 'core.voices']],
        'limits' => ['users' => 20, 'storage_bytes' => 10_737_418_240],
        'release' => ['channel' => 'stable', 'id' => 'current'],
        'resources' => [
            'database' => ['env_prefix' => strtoupper($slug) . '_DB'],
            'storage' => ['path_env' => strtoupper($slug) . '_STORAGE_ROOT'],
            'rag' => ['env_prefix' => strtoupper($slug) . '_QDRANT', 'collection_prefix' => $slug . '__'],
            'session' => ['namespace' => substr(hash('sha256', $slug), 0, 12)],
            'secrets' => ['scope' => $slug],
            'backups' => ['scope' => $slug],
        ],
    ];
    return array_replace_recursive($base, $overrides);
}

$manifest = InstanceManifest::fromArray(manifestData());
$context = InstanceResolver::resolve($manifest, 'Claara.Tech:443');
check('resolves and normalizes an allowed host', $context->host() === 'claara.tech');
check('exposes immutable instance identity', $context->id() === 'default' && $context->slug() === 'default');
check('exposes branding without secrets', $context->branding()['organization_name'] === 'Grupo Ebone');
check('exposes locale policy', $context->defaultLocale() === 'en' && $context->allowedLocales() === ['en', 'es']);
check('exposes enabled modules', $context->isModuleEnabled('core.chat') && !$context->isModuleEnabled('gpand.certificates'));
check('exposes limits', $context->limit('users') === 20);
check('exposes release metadata', $context->release()['channel'] === 'stable');
check('exposes resource references', $context->resources()->databaseEnvPrefix() === 'DEFAULT_DB');
check('uses the manifest session namespace', $context->resources()->sessionNamespace() === substr(hash('sha256', 'default'), 0, 12));

expectException('rejects an unknown host', UnknownInstanceException::class, fn() => InstanceResolver::resolve($manifest, 'gpand.claara.tech'));
expectException('rejects a malformed host', UnknownInstanceException::class, fn() => InstanceResolver::resolve($manifest, "bad host\r\n.example"));
expectException('rejects a suspended instance', InstanceUnavailableException::class, fn() => InstanceResolver::resolve(InstanceManifest::fromArray(manifestData('paused', ['status' => 'suspended'])), 'paused.claara.tech'));
expectException('rejects a canonical domain outside allowed domains', InstanceConfigurationException::class, fn() => InstanceManifest::fromArray(manifestData('bad', ['canonical_domain' => 'other.example'])));
expectException('rejects unsupported default locale', InstanceConfigurationException::class, fn() => InstanceManifest::fromArray(manifestData('bad', ['locales' => ['default' => 'fr', 'allowed' => ['en', 'es']]])));
expectException('rejects duplicate modules', InstanceConfigurationException::class, fn() => InstanceManifest::fromArray(manifestData('bad', ['modules' => ['enabled' => ['core.chat', 'core.chat']]])));
expectException('rejects unknown modules before activating an instance', InstanceConfigurationException::class, fn() => InstanceResolver::resolve(InstanceManifest::fromArray(manifestData('bad', ['modules' => ['enabled' => ['unknown.module']]])), 'bad.claara.tech'));
expectException('rejects a module whose dependency is not enabled', InstanceConfigurationException::class, fn() => InstanceResolver::resolve(InstanceManifest::fromArray(manifestData('bad', ['modules' => ['enabled' => ['gesture.lead-finder']]])), 'bad.claara.tech'));
expectException('rejects invalid environment prefixes', InstanceConfigurationException::class, fn() => InstanceManifest::fromArray(manifestData('bad', ['resources' => ['database' => ['env_prefix' => 'BAD-PREFIX']]])));

$alpha = InstanceResolver::resolve(InstanceManifest::fromArray(manifestData('alpha')), 'alpha.claara.tech');
$beta = InstanceResolver::resolve(InstanceManifest::fromArray(manifestData('beta')), 'beta.claara.tech');
check('two instances have different database references', $alpha->resources()->databaseEnvPrefix() !== $beta->resources()->databaseEnvPrefix());
check('two instances have different storage references', $alpha->resources()->storagePathEnv() !== $beta->resources()->storagePathEnv());
check('two instances have different RAG prefixes', $alpha->resources()->ragCollectionPrefix() !== $beta->resources()->ragCollectionPrefix());
check('two instances have different session namespaces', $alpha->resources()->sessionNamespace() !== $beta->resources()->sessionNamespace());

putenv('ALPHA_DB_HOST=127.0.0.1');
putenv('ALPHA_DB_PORT=3307');
putenv('ALPHA_DB_NAME=alpha_data');
putenv('ALPHA_DB_USER=alpha_user');
putenv('ALPHA_DB_PASS=alpha_secret');
putenv('ALPHA_STORAGE_ROOT=/var/tmp/claara-alpha-storage');
putenv('ALPHA_QDRANT_HOST=127.0.0.1');
putenv('ALPHA_QDRANT_PORT=7333');
putenv('ALPHA_QDRANT_API_KEY=alpha_qdrant_secret');
$database = $alpha->resources()->databaseConfig();
$rag = $alpha->resources()->ragConfig();
check('resolves database secrets only from the instance prefix', $database['name'] === 'alpha_data' && $database['user'] === 'alpha_user' && $database['port'] === 3307);
check('resolves an absolute instance storage root', $alpha->resources()->storageRoot(dirname(__DIR__)) === '/var/tmp/claara-alpha-storage');
check('resolves RAG endpoint and secret from the instance prefix', $rag['port'] === 7333 && $rag['api_key'] === 'alpha_qdrant_secret');

InstanceContext::activate($alpha);
check('cookie names use the active instance namespace', CookieScope::sessionName() === 'claara_session_' . $alpha->resources()->sessionNamespace());
check('storage paths stay under the active instance root', Storage::path('chat-files/example.txt') === '/var/tmp/claara-alpha-storage/chat-files/example.txt');
expectException('storage rejects parent traversal', InstanceConfigurationException::class, fn() => Storage::path('../beta/secret.txt'));
$qdrant = new QdrantClient();
check('RAG collections receive the active instance prefix', $qdrant->scopedCollectionName('lex_knowledge_base') === 'alpha__lex_knowledge_base');
expectException('RAG collection names reject traversal-like input', InvalidArgumentException::class, fn() => $qdrant->scopedCollectionName('../beta'));

fwrite(STDOUT, "\n==== {$passed} passed, {$failed} failed ====\n");
exit($failed === 0 ? 0 : 1);
