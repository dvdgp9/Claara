<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/escribir-articulo.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-write-article.js');
$failed = 0; $passed = 0;
function writeCheck(bool $condition, string $label): void { global $failed, $passed; echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n"; $condition ? $passed++ : $failed++; }
foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'write_ui.')) continue;
    writeCheck(isset($es[$key]), "Spanish Write key exists: {$key}");
}
writeCheck(str_contains($page, "javascriptCatalogPrefixJson('write_ui.')"), 'Write gesture emits browser catalog');
writeCheck(str_contains($page, 'I18n::htmlLang()'), 'Write gesture uses resolved HTML language');
writeCheck(str_contains($js, 'const writeT = '), 'Write gesture JavaScript uses translation helper');
foreach (['>Write content<', '>Generate content<', '>Generated content<', 'Please enter the article topic', 'Delete this content from history?'] as $literal) {
    writeCheck(!str_contains($page . $js, $literal), "Critical Write literal removed: {$literal}");
}
echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
