<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$bootstrap = (string)file_get_contents($root . '/src/App/bootstrap.php');
$usersRepo = (string)file_get_contents($root . '/src/Repos/UsersRepo.php');
$auth = (string)file_get_contents($root . '/src/Auth/AuthService.php');
$remember = (string)file_get_contents($root . '/src/Auth/RememberService.php');
$account = (string)file_get_contents($root . '/public/account.php');
$api = is_file($root . '/public/api/account/update_locale.php') ? (string)file_get_contents($root . '/public/api/account/update_locale.php') : '';
$migration = is_file($root . '/docs/migrations/027_user_locale.sql') ? (string)file_get_contents($root . '/docs/migrations/027_user_locale.sql') : '';
$failed = 0; $passed = 0;
function localeCheck(bool $condition, string $label): void {
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}
localeCheck(str_contains($migration, 'ADD COLUMN IF NOT EXISTS locale'), 'migration adds nullable locale safely');
localeCheck(str_contains($bootstrap, "\$_SESSION['user']['locale'] ?? null"), 'bootstrap passes session preference to locale resolver');
localeCheck(substr_count($usersRepo, 'u.locale') >= 2, 'login and account user reads include locale');
localeCheck(str_contains($usersRepo, 'function updateLocale'), 'repository persists locale');
localeCheck(str_contains($auth, "'locale' => \$row['locale']"), 'login session includes locale');
localeCheck(str_contains($remember, 'u.locale') && str_contains($remember, "'locale' => \$row['locale']"), 'remember-session restoration includes locale');
localeCheck(str_contains($account, "I18n::translate('account.language')"), 'Account exposes language preference');
localeCheck(str_contains($account, '/api/account/update_locale.php'), 'Account saves language through dedicated API');
localeCheck(str_contains($api, 'allowedLocales()'), 'locale API enforces instance allowlist');
localeCheck(str_contains($api, "\$_SESSION['user']['locale']"), 'locale API updates current session');
echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
