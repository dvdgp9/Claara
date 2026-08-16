<?php
declare(strict_types=1);

$root = dirname(__DIR__);
require_once $root . '/src/I18n/I18n.php';

$failed = 0;
$passed = 0;

function pageContractCheck(bool $condition, string $label): void
{
    global $failed, $passed;
    echo ($condition ? 'PASS ' : 'FAIL ') . $label . "\n";
    $condition ? $passed++ : $failed++;
}

$pages = [
    'Project analysis' => $root . '/public/gestos/admin-proyectos.php',
    'Audio transcriber' => $root . '/public/gestos/transcriptor-audio.php',
    'Podcast' => $root . '/public/gestos/podcast-articulo.php',
    'Image editor' => $root . '/public/gestos/editor-imagenes.php',
];

foreach ($pages as $label => $path) {
    $source = (string)file_get_contents($path);
    preg_match_all('/I18n::([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches);
    $methods = array_values(array_unique($matches[1] ?? []));
    pageContractCheck($methods !== [], "{$label} references the I18n API");
    foreach ($methods as $method) {
        pageContractCheck(method_exists(\I18n\I18n::class, $method), "{$label} I18n::{$method} exists");
    }
}

$catalogConsumers = [
    'Project analysis' => $root . '/public/assets/js/gesture-admin-proyectos.js',
    'Audio transcriber' => $root . '/public/gestos/transcriptor-audio.php',
    'Podcast' => $root . '/public/assets/js/gesture-podcast.js',
    'Image editor' => $root . '/public/assets/js/gesture-image-editor.js',
];

foreach ($catalogConsumers as $label => $path) {
    $source = (string)file_get_contents($path);
    pageContractCheck(
        str_contains($source, '.messages || {}'),
        "{$label} reads messages from the browser catalog envelope"
    );
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
