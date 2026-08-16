<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$sources = [
    (string)file_get_contents($root . '/public/voices/lex.php'),
    (string)file_get_contents($root . '/public/voices/view.php'),
];
$js = (string)file_get_contents($root . '/public/assets/js/voice-lex.js');
$failed = 0; $passed = 0;
function voiceCheck(bool $condition, string $label): void {
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}
foreach (array_keys($en) as $key) {
    if (!str_starts_with($key, 'voice_ui.')) continue;
    voiceCheck(array_key_exists($key, $es), "Spanish Voice key exists: {$key}");
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$en[$key], $a);
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$es[$key], $b);
    sort($a[1]); sort($b[1]);
    voiceCheck($a[1] === $b[1], "Voice placeholder parity: {$key}");
}
voiceCheck(str_contains($sources[0], "javascriptCatalogPrefixJson('voice_ui.')"), 'Lex emits Voice browser catalog');
voiceCheck(str_contains($sources[1], "javascriptCatalogPrefixJson('voice_ui.')"), 'Generic Voice emits Voice browser catalog');
voiceCheck(substr_count(implode("\n", $sources), 'I18n::htmlLang()') === 2, 'Both Voice workspaces use resolved HTML language');
voiceCheck(str_contains($js, 'const voiceT = '), 'Voice JavaScript uses translation helper');
foreach (['>History<', '>Send<', 'placeholder="Write your legal question..."', '>Loading documents...<'] as $literal) {
    voiceCheck(!str_contains(implode("\n", $sources), $literal), "Static Voice literal removed: {$literal}");
}
echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
