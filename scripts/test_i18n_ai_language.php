<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$context = (string)file_get_contents($root . '/src/Chat/ContextBuilder.php');
$voices = (string)file_get_contents($root . '/src/Voices/VoiceContextBuilder.php');

$failed = 0;
function languageCheck(bool $condition, string $label): void
{
    global $failed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$condition) $failed++;
}

languageCheck(($en['ai.response_language_instruction'] ?? '') === 'Respond in English by default, unless the user explicitly asks for another language.', 'English instance instructs English output');
languageCheck(($es['ai.response_language_instruction'] ?? '') === 'Respond in Spanish by default, unless the user explicitly asks for another language.', 'Spanish instance instructs Spanish output');
languageCheck(str_contains($context, "I18n::translate('ai.response_language_instruction')"), 'General chat prompt uses instance language instruction');
languageCheck(substr_count($voices, "I18n::translate('ai.response_language_instruction')") >= 2, 'Static and RAG Voice prompts use instance language instruction');
languageCheck(!str_contains($voices, 'Respond in English by default'), 'Voice prompt no longer hardcodes English');

echo "RESULT " . (5 - $failed) . " passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
