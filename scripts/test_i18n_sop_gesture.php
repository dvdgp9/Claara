<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/sop-generator.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-sop.js');
$api = (string)file_get_contents($root . '/public/api/gestures/sop.php');
$generator = (string)file_get_contents($root . '/src/Sop/SopGenerator.php');
$failed = 0;
$passed = 0;

function sopCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'sop_ui.')) {
        sopCheck(isset($es[$key]), "Spanish SOP key exists: {$key}");
    }
}

sopCheck(str_contains($page, "javascriptCatalogPrefixJson('sop_ui.')"), 'SOP emits browser catalog');
sopCheck(str_contains($page, 'I18n::htmlLang()'), 'SOP uses resolved HTML language');
sopCheck(str_contains($js, 'const sopT = '), 'SOP JavaScript uses translation helper');
sopCheck(str_contains($api, "I18n::translate('sop_ui.source_required')"), 'SOP API localizes validation');
sopCheck(substr_count($generator, 'I18n::locale()') >= 3, 'SOP document and diagram follow resolved locale');
foreach (['>Process generator<', '>Generate SOP<', 'Could not access the microphone. Please grant permission.', 'Delete this SOP from history?'] as $literal) {
    sopCheck(!str_contains($page . $js, $literal), "Critical SOP literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
