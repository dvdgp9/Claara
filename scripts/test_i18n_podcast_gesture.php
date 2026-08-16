<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/podcast-articulo.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-podcast.js');
$api = (string)file_get_contents($root . '/public/api/gestures/podcast.php');
$generator = (string)file_get_contents($root . '/src/Audio/PodcastScriptGenerator.php');
$jobCreate = (string)file_get_contents($root . '/public/api/jobs/create.php');
$jobProcess = (string)file_get_contents($root . '/public/api/jobs/process.php');
$css = (string)file_get_contents($root . '/public/assets/css/styles.css');
$failed = 0;
$passed = 0;

function podcastCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'podcast_ui.')) {
        podcastCheck(isset($es[$key]), "Spanish Podcast key exists: {$key}");
    }
}

podcastCheck(str_contains($page, "I18n::javascriptCatalogPrefixJson('podcast_ui.')"), 'Podcast emits a scoped browser catalog');
podcastCheck(str_contains($page, 'I18n::htmlLang()'), 'Podcast uses resolved HTML language');
podcastCheck(str_contains($js, 'CLAARA_PODCAST_I18N?.messages || {}'), 'Podcast reads the browser catalog envelope');
podcastCheck(str_contains($js, 'function podcastT('), 'Podcast dynamic states use translations');
podcastCheck(substr_count($js, "'X-CSRF-Token':") >= 3, 'Podcast mutations send CSRF protection');
podcastCheck(str_contains($api, 'I18n::translate'), 'Podcast API localizes validation');
podcastCheck(substr_count($generator, "I18n::locale() === 'es'") >= 2, 'Podcast script and speech follow resolved locale');
podcastCheck(str_contains($jobCreate, "\$inputData['locale'] = I18n::locale()"), 'Podcast captures locale when queued');
podcastCheck(str_contains($jobProcess, 'I18n::boot(') && str_contains($jobProcess, "I18n::translate('podcast_ui.extracting')"), 'Podcast worker restores locale for progress and generation');
podcastCheck(!str_contains($page, '<style>') && !str_contains($page, 'style='), 'Podcast has no inline styles');
podcastCheck(str_contains($css, '.podcast-page .audio-player-warm'), 'Podcast styles are scoped in styles.css');
podcastCheck(!preg_match('/[📄]/u', $page . $js), 'Podcast interface contains no emoji icons');
foreach (['>Turn text into audio<', 'Please select a PDF file', 'Delete this podcast from history?', 'You have not created podcasts yet'] as $literal) {
    podcastCheck(!str_contains($page . $js, $literal), "Critical Podcast literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
