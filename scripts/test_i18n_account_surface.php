<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$english = require $root . '/resources/i18n/en.php';
$spanish = require $root . '/resources/i18n/es.php';
$passed = 0;
$failed = 0;

function accountCheck(bool $condition, string $label): void
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

$required = [
    'account.page_title',
    'account.title',
    'account.personal_information',
    'account.personal_information_help',
    'account.edit',
    'account.first_name',
    'account.last_name',
    'account.email',
    'account.job_title',
    'account.department',
    'account.not_set',
    'account.unassigned',
    'account.department_responsibility',
    'account.voice_responsibility',
    'account.voice_access',
    'account.save_changes',
    'account.saving',
    'account.security',
    'account.change_password',
    'account.recent_activity',
    'account.conversations_created',
    'account.messages_sent',
    'account.this_week_total',
    'account.last_login',
    'account.first_session',
    'account.created',
    'account.current_password',
    'account.new_password',
    'account.confirm_password',
    'account.password_minimum',
    'account.password_mismatch',
    'account.password_updated',
    'account.error_loading_stats',
    'account.error_updating_profile',
    'account.api.all_fields_required',
    'account.api.current_password_incorrect',
    'account.api.names_required',
];

foreach ($required as $key) {
    accountCheck(isset($english[$key], $spanish[$key]), "required account key exists: {$key}");
}

$files = [
    'public/account.php',
    'public/api/account/activity.php',
    'public/api/account/change_password.php',
    'public/api/account/update_profile.php',
];
$referenced = [];
foreach ($files as $relativePath) {
    $content = (string)file_get_contents($root . '/' . $relativePath);
    preg_match_all("/I18n::translate\('([^']+)'/", $content, $matches);
    foreach ($matches[1] ?? [] as $key) {
        $referenced[$key] = $relativePath;
    }
}
foreach ($referenced as $key => $relativePath) {
    accountCheck(isset($english[$key], $spanish[$key]), "referenced key is defined: {$key} ({$relativePath})");
}

foreach (array_keys($english) as $key) {
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$english[$key], $enMatches);
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$spanish[$key], $esMatches);
    $enPlaceholders = array_values(array_unique($enMatches[1] ?? []));
    $esPlaceholders = array_values(array_unique($esMatches[1] ?? []));
    sort($enPlaceholders);
    sort($esPlaceholders);
    accountCheck($enPlaceholders === $esPlaceholders, "placeholder parity: {$key}");
}

$accountSource = (string)file_get_contents($root . '/public/account.php');
accountCheck(str_contains($accountSource, 'I18n::htmlLang()'), 'Account HTML language is dynamic');
accountCheck(str_contains($accountSource, 'I18n::javascriptCatalogJson('), 'Account JavaScript uses the shared catalog');
accountCheck(!str_contains($accountSource, '<style>'), 'Account has no inline style block');

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
