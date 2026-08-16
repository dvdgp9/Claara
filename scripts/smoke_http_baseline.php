<?php
declare(strict_types=1);

/**
 * HTTP characterization smoke test for the current Claara deployment.
 *
 * Anonymous mode is read-only. Authenticated mode performs a normal login
 * (which updates last_login_at) and then only issues read-only requests. It
 * never sends chat prompts, invokes an LLM, creates content, or logs out other
 * sessions.
 *
 * Usage:
 *   php scripts/smoke_http_baseline.php --base-url=https://claara.tech
 *   php scripts/smoke_http_baseline.php --base-url=https://claara.tech \
 *     --authenticated --allow-production-auth
 *
 * Credentials are read from SMOKE_EMAIL/SMOKE_PASSWORD, falling back to
 * ADMIN_EMAIL/ADMIN_PASSWORD from the local .env. They are never printed.
 */

final class SmokeResponse
{
    /** @param array<string, list<string>> $headers */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $contentType,
        public readonly string $effectiveUrl,
    ) {
    }

    public function header(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];
        return $values === [] ? null : $values[count($values) - 1];
    }

    /** @return list<string> */
    public function headerValues(string $name): array
    {
        return $this->headers[strtolower($name)] ?? [];
    }

    /** @return array<string, mixed>|null */
    public function json(): ?array
    {
        $decoded = json_decode($this->body, true);
        return is_array($decoded) ? $decoded : null;
    }
}

final class SmokeHttpClient
{
    private string $cookieJar;

    public function __construct(private readonly string $baseUrl)
    {
        $cookieJar = tempnam(sys_get_temp_dir(), 'claara-smoke-cookie-');
        if ($cookieJar === false) {
            throw new RuntimeException('Could not create temporary cookie jar');
        }
        $this->cookieJar = $cookieJar;
    }

    public function __destruct()
    {
        if (is_file($this->cookieJar)) {
            @unlink($this->cookieJar);
        }
    }

    /** @param array<string, mixed>|null $jsonBody */
    public function request(string $method, string $path, ?array $jsonBody = null): SmokeResponse
    {
        $url = $this->baseUrl . '/' . ltrim($path, '/');
        $headers = [];
        $requestHeaders = ['Accept: application/json, text/html;q=0.9'];

        $ch = curl_init($url);
        if ($ch === false) {
            throw new RuntimeException('Could not initialize cURL');
        }

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_USERAGENT => 'ClaaraBaselineSmoke/1.0',
            CURLOPT_HEADERFUNCTION => static function ($curl, string $line) use (&$headers): int {
                $length = strlen($line);
                $line = trim($line);
                if ($line === '' || !str_contains($line, ':')) {
                    return $length;
                }
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))][] = trim($value);
                return $length;
            },
        ]);

        if ($jsonBody !== null) {
            $encoded = json_encode($jsonBody, JSON_UNESCAPED_SLASHES);
            if ($encoded === false) {
                throw new RuntimeException('Could not encode request JSON');
            }
            $requestHeaders[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $encoded);
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $requestHeaders);
        $body = curl_exec($ch);
        if ($body === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new RuntimeException("HTTP request failed for {$method} {$path}: {$error}");
        }

        $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)(curl_getinfo($ch, CURLINFO_CONTENT_TYPE) ?: '');
        $effectiveUrl = (string)(curl_getinfo($ch, CURLINFO_EFFECTIVE_URL) ?: $url);
        curl_close($ch);

        return new SmokeResponse($status, $headers, (string)$body, $contentType, $effectiveUrl);
    }
}

final class SmokeSuite
{
    private int $passed = 0;
    private int $failed = 0;
    private int $observations = 0;

    public function check(string $label, bool $condition, ?SmokeResponse $response = null): void
    {
        if ($condition) {
            $this->passed++;
            fwrite(STDOUT, "[PASS] {$label}\n");
            return;
        }

        $this->failed++;
        fwrite(STDOUT, "[FAIL] {$label}");
        if ($response !== null) {
            fwrite(STDOUT, ' — ' . $this->diagnostic($response));
        }
        fwrite(STDOUT, "\n");
    }

    public function observe(string $label, string $value): void
    {
        $this->observations++;
        fwrite(STDOUT, "[OBSERVE] {$label}: {$value}\n");
    }

    public function assertSecurityHeaders(string $label, SmokeResponse $response, bool $expectHsts = true): void
    {
        $this->check("{$label}: X-Frame-Options", strtoupper((string)$response->header('x-frame-options')) === 'SAMEORIGIN', $response);
        $this->check("{$label}: X-Content-Type-Options", strtolower((string)$response->header('x-content-type-options')) === 'nosniff', $response);
        $this->check("{$label}: Referrer-Policy", $response->header('referrer-policy') === 'strict-origin-when-cross-origin', $response);
        $this->check(
            $expectHsts ? "{$label}: HSTS" : "{$label}: HSTS omitted on local HTTP",
            $expectHsts
                ? str_contains(strtolower((string)$response->header('strict-transport-security')), 'max-age=')
                : $response->header('strict-transport-security') === null,
            $response,
        );
    }

    public function finish(): never
    {
        fwrite(STDOUT, sprintf(
            "\n==== %d passed, %d failed, %d observations ====\n",
            $this->passed,
            $this->failed,
            $this->observations,
        ));
        exit($this->failed === 0 ? 0 : 1);
    }

    private function diagnostic(SmokeResponse $response): string
    {
        $json = $response->json();
        $errorCode = is_array($json['error'] ?? null) ? ($json['error']['code'] ?? null) : null;
        $keys = $json === null ? [] : array_keys($json);
        $parts = [
            'status=' . $response->status,
            'type=' . ($response->contentType !== '' ? $response->contentType : 'unknown'),
            'bytes=' . strlen($response->body),
        ];
        if (is_string($errorCode) && $errorCode !== '') {
            $parts[] = 'error=' . $errorCode;
        }
        if ($keys !== []) {
            $parts[] = 'json_keys=' . implode(',', array_map('strval', $keys));
        }
        return implode(' ', $parts);
    }
}

/** @return array<string, string> */
function loadEnvValues(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $values = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        if (strlen($value) >= 2 && (($value[0] === '"' && str_ends_with($value, '"')) || ($value[0] === "'" && str_ends_with($value, "'")))) {
            $value = substr($value, 1, -1);
        }
        $values[$key] = $value;
    }
    return $values;
}

/** @param array<string, mixed> $options */
function optionEnabled(array $options, string $key): bool
{
    return array_key_exists($key, $options);
}

$options = getopt('', [
    'base-url::',
    'env-file::',
    'authenticated',
    'allow-production-auth',
    'help',
]);

if (optionEnabled($options, 'help')) {
    fwrite(STDOUT, "See the usage block at the top of this file.\n");
    exit(0);
}

$baseUrl = rtrim((string)($options['base-url'] ?? 'https://claara.tech'), '/');
$authenticated = optionEnabled($options, 'authenticated');
$host = strtolower((string)(parse_url($baseUrl, PHP_URL_HOST) ?: ''));
$scheme = strtolower((string)(parse_url($baseUrl, PHP_URL_SCHEME) ?: ''));

if (!filter_var($baseUrl, FILTER_VALIDATE_URL) || $host === '') {
    fwrite(STDERR, "Invalid --base-url\n");
    exit(2);
}
if (!in_array((string)parse_url($baseUrl, PHP_URL_SCHEME), ['https', 'http'], true)) {
    fwrite(STDERR, "Only HTTP(S) base URLs are supported\n");
    exit(2);
}
if ($authenticated && $host === 'claara.tech' && !optionEnabled($options, 'allow-production-auth')) {
    fwrite(STDERR, "Authenticated production smoke requires --allow-production-auth\n");
    exit(2);
}
if ($authenticated && parse_url($baseUrl, PHP_URL_SCHEME) !== 'https' && !in_array($host, ['localhost', '127.0.0.1'], true)) {
    fwrite(STDERR, "Refusing to send credentials over non-HTTPS\n");
    exit(2);
}

if (!extension_loaded('curl')) {
    fwrite(STDERR, "PHP cURL extension is required\n");
    exit(2);
}

$suite = new SmokeSuite();
$client = new SmokeHttpClient($baseUrl);

fwrite(STDOUT, "=== Anonymous baseline: {$baseUrl} ===\n");

$loginPage = $client->request('GET', '/login.php');
$suite->check('login page returns 200', $loginPage->status === 200, $loginPage);
$suite->check('login page contains login form', str_contains($loginPage->body, 'id="login-form"'), $loginPage);
$suite->assertSecurityHeaders('login page', $loginPage, $scheme === 'https');

$sessionCookies = array_values(array_filter(
    $loginPage->headerValues('set-cookie'),
    static fn(string $cookie): bool => preg_match('/^claara_session_[a-f0-9]{12}=/', $cookie) === 1,
));
$suite->check('login page starts a session cookie', $sessionCookies !== [], $loginPage);
if ($sessionCookies !== []) {
    $cookie = $sessionCookies[0];
    $lowerCookie = strtolower($cookie);
    $suite->check('session cookie is host-only', !str_contains($lowerCookie, 'domain='), $loginPage);
    $suite->check(
        $scheme === 'https' ? 'session cookie uses Secure' : 'local HTTP session cookie omits Secure',
        $scheme === 'https' ? str_contains($lowerCookie, 'secure') : !str_contains($lowerCookie, 'secure'),
        $loginPage,
    );
    $suite->check('session cookie uses HttpOnly', str_contains($lowerCookie, 'httponly'), $loginPage);
    $suite->check('session cookie uses SameSite=Lax', str_contains($lowerCookie, 'samesite=lax'), $loginPage);
}

$anonymousApp = $client->request('GET', '/app/');
$suite->check('anonymous app redirects', $anonymousApp->status === 302, $anonymousApp);
$suite->check('anonymous app redirects to login', $anonymousApp->header('location') === '/login.php', $anonymousApp);

$anonymousMe = $client->request('GET', '/api/auth/me.php');
$anonymousMeJson = $anonymousMe->json();
$suite->check('anonymous me returns 401', $anonymousMe->status === 401, $anonymousMe);
$suite->check('anonymous me uses stable unauthorized code', ($anonymousMeJson['error']['code'] ?? null) === 'unauthorized', $anonymousMe);
$suite->assertSecurityHeaders('anonymous API', $anonymousMe, $scheme === 'https');

$anonymousConversations = $client->request('GET', '/api/conversations/list.php');
$suite->check('anonymous conversation list is denied', $anonymousConversations->status === 401, $anonymousConversations);

$anonymousVoices = $client->request('GET', '/api/voices/catalog.php');
$suite->check('anonymous Voice catalog is denied', $anonymousVoices->status === 401, $anonymousVoices);

if (!$authenticated) {
    $suite->finish();
}

$envFile = (string)($options['env-file'] ?? (dirname(__DIR__) . '/.env'));
$env = loadEnvValues($envFile);
$email = (string)(getenv('SMOKE_EMAIL') ?: ($env['ADMIN_EMAIL'] ?? ''));
$password = (string)(getenv('SMOKE_PASSWORD') ?: ($env['ADMIN_PASSWORD'] ?? ''));
if ($email === '' || $password === '') {
    fwrite(STDERR, "Authenticated smoke requires SMOKE_EMAIL/SMOKE_PASSWORD or ADMIN_EMAIL/ADMIN_PASSWORD in .env\n");
    exit(2);
}

fwrite(STDOUT, "\n=== Authenticated read baseline (login updates last_login_at) ===\n");
$login = $client->request('POST', '/api/auth/login.php', [
    'email' => $email,
    'password' => $password,
    'remember' => false,
]);
$loginJson = $login->json();
$suite->check('login returns 200', $login->status === 200, $login);
$suite->check('login returns user payload', is_array($loginJson['user'] ?? null), $login);
$suite->check('login returns CSRF token', is_string($loginJson['csrf_token'] ?? null) && strlen($loginJson['csrf_token']) >= 32, $login);
$suite->assertSecurityHeaders('login API', $login, $scheme === 'https');

if ($login->status !== 200 || !is_array($loginJson['user'] ?? null)) {
    $suite->finish();
}

$expectedUserId = (int)($loginJson['user']['id'] ?? 0);
$isSuperadmin = (bool)($loginJson['user']['is_superadmin'] ?? false);

$me = $client->request('GET', '/api/auth/me.php');
$meJson = $me->json();
$suite->check('authenticated me returns 200', $me->status === 200, $me);
$suite->check('authenticated me keeps user identity', (int)($meJson['user']['id'] ?? 0) === $expectedUserId && $expectedUserId > 0, $me);

$app = $client->request('GET', '/app/');
$suite->check('authenticated app returns 200', $app->status === 200, $app);
$suite->check('authenticated app renders chat shell', str_contains($app->body, 'id="messages-container"'), $app);

$voices = $client->request('GET', '/api/voices/catalog.php');
$voicesJson = $voices->json();
$suite->check('authenticated Voice catalog returns 200', $voices->status === 200, $voices);
$suite->check('Voice catalog has expected shape', ($voicesJson['success'] ?? false) === true && is_array($voicesJson['voices'] ?? null), $voices);

$conversations = $client->request('GET', '/api/conversations/list.php?include_shared=1');
$conversationsJson = $conversations->json();
$suite->check('authenticated conversation list returns 200', $conversations->status === 200, $conversations);
$suite->check('conversation list has owned and shared shapes', is_array($conversationsJson['items'] ?? null) && is_array($conversationsJson['shared'] ?? null), $conversations);

$gestures = $client->request('GET', '/gestos/');
$suite->check('authenticated Gestures page returns 200', $gestures->status === 200, $gestures);

$account = $client->request('GET', '/account.php');
$suite->check('authenticated Account page returns 200', $account->status === 200, $account);

if ($isSuperadmin) {
    $adminUsers = $client->request('GET', '/admin/users.php');
    $suite->check('superadmin Users page returns 200', $adminUsers->status === 200, $adminUsers);

    $adminFeatures = $client->request('GET', '/api/admin/features/list.php');
    $adminFeaturesJson = $adminFeatures->json();
    $suite->check('superadmin feature catalog returns 200', $adminFeatures->status === 200, $adminFeatures);
    $suite->check('superadmin feature catalog returns JSON', is_array($adminFeaturesJson), $adminFeatures);
} else {
    $suite->observe('admin checks', 'skipped because smoke user is not a superadmin');
}

$suite->finish();
