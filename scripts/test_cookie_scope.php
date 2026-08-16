<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/App/CookieScope.php';

use App\CookieScope;

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

function resetRequest(array $server = [], ?string $appUrl = null): void
{
    $_SERVER = $server;
    unset($_ENV['APP_URL']);
    putenv('APP_URL');
    if ($appUrl !== null) {
        $_ENV['APP_URL'] = $appUrl;
        putenv('APP_URL=' . $appUrl);
    }
}

resetRequest(['HTTP_HOST' => 'Claara.Tech:443']);
check('normalizes request host and strips port', CookieScope::host() === 'claara.tech');

$defaultSession = CookieScope::sessionName();
$defaultRemember = CookieScope::rememberName();
check('session cookie name is valid and namespaced', preg_match('/^claara_session_[a-f0-9]{12}$/', $defaultSession) === 1);
check('remember cookie name is valid and namespaced', preg_match('/^claara_remember_[a-f0-9]{12}$/', $defaultRemember) === 1);

resetRequest(['HTTP_HOST' => 'gpand.claara.tech']);
check('tenant session name differs from default host', CookieScope::sessionName() !== $defaultSession);
check('tenant remember name differs from default host', CookieScope::rememberName() !== $defaultRemember);

$sessionOptions = CookieScope::sessionOptions(0);
$persistentOptions = CookieScope::persistentOptions(3600, 1_700_000_000);
check('session options force an empty cookie domain', array_key_exists('domain', $sessionOptions) && $sessionOptions['domain'] === '');
check('persistent options omit the Domain attribute', !array_key_exists('domain', $persistentOptions));
check('cookie options retain secure defaults', $sessionOptions['httponly'] === true && $sessionOptions['samesite'] === 'Lax');

resetRequest(['HTTP_HOST' => "bad host\r\n.example"], 'https://claara.tech');
check('invalid Host falls back to configured application host', CookieScope::host() === 'claara.tech');

resetRequest(['HTTP_HOST' => 'gpand.claara.tech', 'HTTP_X_FORWARDED_PROTO' => 'https']);
check('trusted HTTPS proxy signal enables Secure cookies', CookieScope::isHttps() === true);

resetRequest(['HTTP_HOST' => 'localhost:8000']);
check('plain local development does not force Secure', CookieScope::isHttps() === false);

fwrite(STDOUT, "\n==== {$passed} passed, {$failed} failed ====\n");
exit($failed === 0 ? 0 : 1);
