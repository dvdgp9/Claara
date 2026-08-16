<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/admin-proyectos.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-admin-proyectos.js');
$api = (string)file_get_contents($root . '/public/api/gestures/admin-proyectos.php');
$css = (string)file_get_contents($root . '/public/assets/css/styles.css');
$failed = 0;
$passed = 0;

function projectCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'project_ui.')) {
        projectCheck(isset($es[$key]), "Spanish Project key exists: {$key}");
    }
}

projectCheck(str_contains($page, "I18n::javascriptCatalogPrefixJson('project_ui.')"), 'Project analysis emits a scoped browser catalog');
projectCheck(str_contains($page, 'I18n::htmlLang()'), 'Project analysis uses resolved HTML language');
projectCheck(str_contains($js, 'CLAARA_PROJECT_I18N?.messages || {}'), 'Project analysis reads the browser catalog envelope');
projectCheck(str_contains($js, 'function projectT('), 'Project analysis dynamic states use translations');
projectCheck(str_contains($js, "'X-CSRF-Token': CSRF_TOKEN"), 'Project deletion sends CSRF protection');
projectCheck(str_contains($js, 'text = escapeHtml(text);'), 'Project AI output is escaped before Markdown rendering');
projectCheck(substr_count($api, "I18n::translate('project_ui.output_language')") >= 2, 'Both project analyses follow resolved locale');
projectCheck(!str_contains($page, '<style>'), 'Project analysis has no embedded style block');
projectCheck(str_contains($css, '.project-analysis-page .action-card'), 'Project styles are scoped in styles.css');
projectCheck(!preg_match('/[📋💰📦🔒⚠️📊💡⏱️👥📚👷📍]/u', $page . $js . $api), 'Project workflow contains no emoji icons');
foreach (['>Project Analysis<', 'No analyses yet', 'Delete this analysis from history?', 'Error desconocido'] as $literal) {
    projectCheck(!str_contains($page . $js, $literal), "Critical Project literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
