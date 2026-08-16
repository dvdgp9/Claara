<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$english = require $root . '/resources/i18n/en.php';
$spanish = require $root . '/resources/i18n/es.php';
$passed = 0;
$failed = 0;

function surfaceCheck(bool $condition, string $label): void
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

$englishKeys = array_keys($english);
$spanishKeys = array_keys($spanish);
sort($englishKeys);
sort($spanishKeys);
surfaceCheck($englishKeys === $spanishKeys, 'English and Spanish dictionaries have identical keys');

$required = [
    'meta.workspace_title',
    'auth.login.title',
    'auth.login.email',
    'auth.login.password',
    'auth.login.remember',
    'auth.login.submit',
    'auth.login.submitting',
    'auth.error.invalid_credentials',
    'auth.error.user_locked',
    'auth.error.rate_limited',
    'nav.chat',
    'nav.voices',
    'nav.gestures',
    'nav.sources',
    'nav.account',
    'nav.logout',
    'nav.conversations',
    'nav.recent_conversations',
    'nav.available_voices',
    'nav.available_gestures',
    'nav.manage_sources',
    'header.share',
    'header.search_coming_soon',
    'header.ask_claara',
    'header.my_account',
    'header.connectors',
    'header.reports',
    'header.voice_studio',
    'header.user_management',
    'header.departments',
    'header.dashboard',
    'header.context_manager',
    'header.connector_overview',
    'header.chat_models',
    'sidebar.new_conversation',
    'sidebar.folders',
    'sidebar.all',
    'sidebar.no_folder',
    'sidebar.recent',
    'sidebar.favorites',
    'sidebar.created',
    'sidebar.alphabetical',
    'sidebar.empty',
    'drawer.history',
    'drawer.loading',
];
foreach ($required as $key) {
    surfaceCheck(isset($english[$key], $spanish[$key]), "required shared key exists: {$key}");
}

foreach ($englishKeys as $key) {
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$english[$key], $enMatches);
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$spanish[$key], $esMatches);
    $enPlaceholders = array_values(array_unique($enMatches[1] ?? []));
    $esPlaceholders = array_values(array_unique($esMatches[1] ?? []));
    sort($enPlaceholders);
    sort($esPlaceholders);
    surfaceCheck($enPlaceholders === $esPlaceholders, "placeholder parity: {$key}");
}

$surfaceFiles = [
    'public/login.php',
    'public/api/auth/login.php',
    'public/app.php',
    'public/includes/head.php',
    'public/includes/header-unified.php',
    'public/includes/left-tabs.php',
    'public/includes/bottom-nav.php',
    'public/includes/mobile-drawer.php',
    'public/includes/sidebar.php',
    'src/Auth/AuthService.php',
];
$referenced = [];
foreach ($surfaceFiles as $relativePath) {
    $content = (string)file_get_contents($root . '/' . $relativePath);
    preg_match_all("/I18n::translate\('([^']+)'/", $content, $matches);
    foreach ($matches[1] ?? [] as $key) {
        $referenced[$key] = $relativePath;
    }
}
foreach ($referenced as $key => $relativePath) {
    surfaceCheck(isset($english[$key], $spanish[$key]), "referenced key is defined: {$key} ({$relativePath})");
}

$loginSource = (string)file_get_contents($root . '/public/login.php');
$appSource = (string)file_get_contents($root . '/public/app.php');
surfaceCheck(str_contains($loginSource, 'I18n::htmlLang()'), 'login HTML language is dynamic');
surfaceCheck(str_contains($appSource, 'I18n::htmlLang()'), 'workspace HTML language is dynamic');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
