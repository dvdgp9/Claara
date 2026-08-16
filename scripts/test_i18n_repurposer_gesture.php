<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/transformador-contenido.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-repurposer.js');
$api = (string)file_get_contents($root . '/public/api/gestures/repurposer.php');
$service = (string)file_get_contents($root . '/src/Content/ContentRepurposer.php');
$failed = 0;
$passed = 0;

function repurposeCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'repurpose_ui.')) {
        continue;
    }
    repurposeCheck(isset($es[$key]), "Spanish Repurposer key exists: {$key}");
}

repurposeCheck(str_contains($page, "javascriptCatalogPrefixJson('repurpose_ui.')"), 'Repurposer emits browser catalog');
repurposeCheck(str_contains($page, 'I18n::htmlLang()'), 'Repurposer uses resolved HTML language');
repurposeCheck(str_contains($js, 'const repurposeT = '), 'Repurposer JavaScript uses translation helper');
repurposeCheck(str_contains($js, "language: repurposeI18n.locale"), 'Repurposer submits its resolved language');
repurposeCheck(str_contains($api, "I18n::locale()"), 'Repurposer API enforces the authenticated locale');
repurposeCheck(str_contains($service, 'IDIOMA DE SALIDA: {$languageLabel}'), 'Repurposer prompt follows the requested locale');
repurposeCheck(!str_contains($page, '<style>'), 'Repurposer has no inline CSS block');

foreach (['>Transform content<', '>Generated content<', 'Please enter a URL', 'Delete this transformation from history?'] as $literal) {
    repurposeCheck(!str_contains($page . $js, $literal), "Critical Repurposer literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
