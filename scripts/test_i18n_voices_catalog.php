<?php
declare(strict_types=1);
$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$source = (string)file_get_contents($root . '/public/voices/index.php');
$keys = ['voices.page_title', 'voices.subtitle', 'voices.company_assistants', 'voices.hero', 'voices.manage', 'voices.none', 'voices.none_help', 'voices.published', 'voices.specialized_assistant', 'voices.knowledge_help', 'voices.rag', 'voices.open'];
$failed = 0;
foreach ($keys as $key) {
    $ok = isset($en[$key], $es[$key]) && str_contains($source, "I18n::translate('{$key}')");
    echo ($ok ? 'PASS ' : 'FAIL ') . $key . "\n";
    if (!$ok) $failed++;
}
$langOk = str_contains($source, 'I18n::htmlLang()');
echo ($langOk ? 'PASS ' : 'FAIL ') . "localized html language\n";
if (!$langOk) $failed++;
echo "RESULT " . (13 - $failed) . " passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
