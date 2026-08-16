<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/editor-imagenes.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-image-editor.js');
$api = (string)file_get_contents($root . '/public/api/gestures/generate-image.php');
$css = (string)file_get_contents($root . '/public/assets/css/styles.css');
$failed = 0;
$passed = 0;

function imageEditorCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

$imageKeys = array_values(array_filter(
    array_keys($en),
    static fn(string $key): bool => str_starts_with($key, 'image_ui.')
));
imageEditorCheck(count($imageKeys) >= 80, 'Image editor has complete English translation coverage');
foreach ($imageKeys as $key) {
    imageEditorCheck(isset($es[$key]), "Spanish Image editor key exists: {$key}");
}

imageEditorCheck(str_contains($page, "I18n::javascriptCatalogPrefixJson('image_ui.')"), 'Image editor emits a scoped browser catalog');
imageEditorCheck(str_contains($page, 'I18n::htmlLang()'), 'Image editor uses resolved HTML language');
imageEditorCheck(str_contains($js, 'CLAARA_IMAGE_I18N?.messages || {}'), 'Image editor reads the browser catalog envelope');
imageEditorCheck(str_contains($js, 'function imageT('), 'Image editor dynamic states use translations');
imageEditorCheck(str_contains($api, 'I18n::translate'), 'Image generation API localizes validation and errors');
imageEditorCheck(str_contains($api, "I18n::translate('image_ui.output_language')"), 'Image generation response text follows the resolved locale');
imageEditorCheck(!str_contains($page, '<style>'), 'Image editor has no embedded style block');
imageEditorCheck(str_contains($css, '.image-editor-page .intent-card'), 'Image editor styles are scoped in styles.css');
imageEditorCheck(str_contains($page, 'h-[100dvh]'), 'Image editor uses stable mobile viewport height');

foreach ([
    '>Create from scratch<',
    '>Generate your first image<',
    '>Parameters<',
    '>Image parameters<',
    'Delete this image from history?',
    'Connection error al cargar la imagen.',
] as $literal) {
    imageEditorCheck(!str_contains($page . $js, $literal), "Critical Image editor literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
