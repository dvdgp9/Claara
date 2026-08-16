<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/lead-finder.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-lead-finder.js');
$failed = 0;
$passed = 0;

function leadCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'lead_ui.')) {
        leadCheck(isset($es[$key]), "Spanish Lead Finder key exists: {$key}");
    }
}

leadCheck(str_contains($page, "javascriptCatalogPrefixJson('lead_ui.')"), 'Lead Finder emits browser catalog');
leadCheck(str_contains($page, 'I18n::htmlLang()'), 'Lead Finder uses resolved HTML language');
leadCheck(str_contains($js, 'const leadT = '), 'Lead Finder JavaScript uses translation helper');
leadCheck(str_contains($js, 'localizeProgress'), 'Lead Finder localizes background progress');
foreach (['>Find contacts from a plain request.<', '>Ready for a new search<', 'Delete this Lead Finder search?', 'No leads found for this search.'] as $literal) {
    leadCheck(!str_contains($page . $js, $literal), "Critical Lead Finder literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
