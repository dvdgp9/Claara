<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$source = (string)file_get_contents($root . '/public/gestos/index.php');
$failed = 0; $passed = 0;
function gestureCheck(bool $condition, string $label): void {
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}
foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'gestures_catalog.')) continue;
    gestureCheck(isset($es[$key]), "Spanish Gesture key exists: {$key}");
    gestureCheck(str_contains($source, "I18n::translate('{$key}')"), "Gesture catalog references: {$key}");
}
gestureCheck(str_contains($source, 'I18n::htmlLang()'), 'Gesture catalog uses resolved HTML language');
foreach (['>Gestures<', '>Use gesture<', '>Coming soon<', '🍌'] as $literal) {
    gestureCheck(!str_contains($source, $literal), "Catalog literal removed: {$literal}");
}
echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
