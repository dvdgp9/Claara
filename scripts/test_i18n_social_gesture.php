<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/redes-sociales.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-social-media.js');
$failed = 0;
$passed = 0;

function socialCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'social_ui.')) {
        continue;
    }
    socialCheck(isset($es[$key]), "Spanish Social key exists: {$key}");
}

socialCheck(str_contains($page, "javascriptCatalogPrefixJson('social_ui.')"), 'Social gesture emits browser catalog');
socialCheck(str_contains($page, 'I18n::htmlLang()'), 'Social gesture uses resolved HTML language');
socialCheck(str_contains($js, 'const socialT = '), 'Social gesture JavaScript uses translation helper');
socialCheck(str_contains($js, 'OUTPUT LANGUAGE:'), 'Social prompt follows the resolved locale');

foreach (['>Generate post<', '>Your post will appear here<', 'Please describe what the post is about', 'Delete this post from history?', "return 'yesterday'"] as $literal) {
    socialCheck(!str_contains($page . $js, $literal), "Critical Social literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
