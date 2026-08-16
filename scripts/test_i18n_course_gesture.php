<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$en = require $root . '/resources/i18n/en.php';
$es = require $root . '/resources/i18n/es.php';
$page = (string)file_get_contents($root . '/public/gestos/creador-cursos.php');
$js = (string)file_get_contents($root . '/public/assets/js/gesture-course-creator.js');
$generator = (string)file_get_contents($root . '/src/Content/CourseGenerator.php');
$apis = implode("\n", array_map(
    static fn(string $file): string => (string)file_get_contents($root . '/' . $file),
    ['public/api/gestures/course-creator.php', 'public/api/gestures/course-develop.php', 'public/api/gestures/course-materials.php']
));
$failed = 0;
$passed = 0;

function courseCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

foreach (array_keys($en) as $key) {
    if (str_starts_with($key, 'course_ui.')) {
        courseCheck(isset($es[$key]), "Spanish Course key exists: {$key}");
    }
}

courseCheck(str_contains($page, "javascriptCatalogPrefixJson('course_ui.')"), 'Course creator emits browser catalog');
courseCheck(str_contains($page, 'I18n::htmlLang()'), 'Course creator uses resolved HTML language');
courseCheck(str_contains($js, 'const courseT = '), 'Course creator JavaScript uses translation helper');
courseCheck(substr_count($js, "'X-CSRF-Token': window.CSRF_TOKEN") >= 5, 'Course mutations send CSRF protection');
courseCheck(substr_count($generator, '$this->outputLanguageInstruction()') >= 3, 'All Course generation phases follow resolved locale');
courseCheck(str_contains($apis, 'I18n::translate'), 'Course APIs localize validation states');
courseCheck(!preg_match('/[🌱🌿🌳🏫💻🔄📚]/u', $page . $js), 'Course interface contains no emoji icons');
foreach (['>Generate course outline<', 'Please select a PDF file', 'Delete this course from history?', 'You have not created courses yet'] as $literal) {
    courseCheck(!str_contains($page . $js, $literal), "Critical Course literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
