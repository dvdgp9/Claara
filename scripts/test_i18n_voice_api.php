<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$files = glob($root . '/public/api/voices/*.php') ?: [];
$failed = 0; $passed = 0;
function voiceApiCheck(bool $condition, string $label): void {
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}
foreach ($files as $file) {
    $source = (string)file_get_contents($file);
    preg_match_all("/I18n::translate\('([^']+)'/", $source, $matches);
    foreach (array_unique($matches[1] ?? []) as $key) {
        voiceApiCheck(isset($en[$key], $es[$key]), basename($file) . " translation exists: {$key}");
    }
}
foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'voice_api.')) continue;
    voiceApiCheck(isset($es[$key]), "Spanish Voice API key exists: {$key}");
}
$combined = implode("\n", array_map(static fn(string $file): string => (string)file_get_contents($file), $files));
foreach (['Invalid session', 'Invalid CSRF token', 'Voice not found', 'You do not have access to this voice', 'Document not found', 'Error reading document'] as $literal) {
    voiceApiCheck(!str_contains($combined, "Response::error('unauthorized', '{$literal}'") && !str_contains($combined, "Response::error('invalid_voice', '{$literal}'") && !str_contains($combined, "Response::error('forbidden', '{$literal}'") && !str_contains($combined, "Response::error('not_found', '{$literal}'") && !str_contains($combined, "Response::error('read_error', '{$literal}'"), "API literal removed: {$literal}");
}
echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
