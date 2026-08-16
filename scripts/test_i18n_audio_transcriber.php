<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/transcriptor-audio.php');
$api = (string)file_get_contents($root . '/public/api/gestures/transcribe.php');
$jobs = (string)file_get_contents($root . '/public/api/jobs/process.php');
$css = (string)file_get_contents($root . '/public/assets/css/styles.css');
$failed = 0;
$passed = 0;

function transcriberCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'transcribe_ui.')) {
        transcriberCheck(isset($es[$key]), "Spanish Transcriber key exists: {$key}");
    }
}

transcriberCheck(str_contains($page, "I18n::javascriptCatalogPrefixJson('transcribe_ui.')"), 'Transcriber emits a scoped browser catalog');
transcriberCheck(str_contains($page, 'I18n::htmlLang()'), 'Transcriber uses resolved HTML language');
transcriberCheck(str_contains($page, 'CLAARA_TRANSCRIBE_I18N?.messages || {}'), 'Transcriber reads the browser catalog envelope');
transcriberCheck(str_contains($page, 'function transcribeT('), 'Transcriber dynamic states use translations');
transcriberCheck(str_contains($api, 'I18n::translate'), 'Transcriber API localizes validation');
transcriberCheck(str_contains($api, "'locale' => I18n::locale()"), 'Transcriber captures locale for its background job');
transcriberCheck(str_contains($jobs, "I18n::translate('transcribe_ui.api_processing')"), 'Transcriber background progress uses captured locale');
transcriberCheck(str_contains($page, 'h-[100dvh]'), 'Transcriber uses stable mobile viewport height');
transcriberCheck(!str_contains($page, '<style>'), 'Transcriber has no embedded style block');
transcriberCheck(str_contains($css, '.transcriber-page .audio-drop-zone'), 'Transcriber styles are scoped in styles.css');
foreach (['>Audio transcriber<', 'No transcriptions yet', 'Delete this transcription from history?', 'Unsupported format. Use:'] as $literal) {
    transcriberCheck(!str_contains($page, $literal), "Critical Transcriber literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
