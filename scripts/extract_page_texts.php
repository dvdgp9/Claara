<?php
declare(strict_types=1);

/**
 * Extract likely user-facing strings from public PHP pages.
 *
 * Outputs:
 * - docs/translations/pages/*.md (one file per page)
 * - docs/translations/app_texts_en.csv (global index)
 * - docs/translations/app_texts_en.json (global index)
 *
 * Usage:
 *   php scripts/extract_page_texts.php
 *   php scripts/extract_page_texts.php --include-admin
 */

$root = dirname(__DIR__);
$publicDir = $root . '/public';
$outDir = $root . '/docs/translations';
$pagesDir = $outDir . '/pages';

if (!is_dir($publicDir)) {
    fwrite(STDERR, "Public directory not found: $publicDir\n");
    exit(1);
}

@mkdir($outDir, 0775, true);
@mkdir($pagesDir, 0775, true);

$includeAdmin = in_array('--include-admin', $argv, true);
$pageFiles = collectPageFiles($publicDir, $includeAdmin);
$globalRows = [];

cleanupPageDocs($pagesDir);

foreach ($pageFiles as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        continue;
    }

    $relative = ltrim(str_replace($root, '', $file), '/');
    $displayPath = '/' . str_replace('\\', '/', $relative);
    $masked = maskPhpBlocks($content);

    $rows = [];
    addMatches($rows, extractHtmlText($masked), 'html_text');
    addMatches($rows, extractHtmlAttributes($masked), 'attribute');
    addMatches($rows, extractScriptStrings($masked), 'script_string');
    addMatches($rows, extractPhpEchoStrings($content), 'php_string');

    $rows = dedupeRows($rows);
    usort($rows, static function (array $a, array $b): int {
        if ($a['line'] === $b['line']) {
            return strcmp($a['type'], $b['type']);
        }
        return $a['line'] <=> $b['line'];
    });

    $slug = fileToSlug($relative);
    $mdPath = $pagesDir . '/' . $slug . '.md';
    file_put_contents($mdPath, buildPageMarkdown($displayPath, $rows));

    foreach ($rows as $row) {
        $globalRows[] = [
            'file' => $displayPath,
            'line' => $row['line'],
            'type' => $row['type'],
            'text' => $row['text'],
        ];
    }
}

usort($globalRows, static function (array $a, array $b): int {
    $cmp = strcmp((string)$a['file'], (string)$b['file']);
    if ($cmp !== 0) {
        return $cmp;
    }
    if ($a['line'] === $b['line']) {
        return strcmp((string)$a['type'], (string)$b['type']);
    }
    return $a['line'] <=> $b['line'];
});

file_put_contents($outDir . '/app_texts_en.csv', buildCsv($globalRows));
file_put_contents(
    $outDir . '/app_texts_en.json',
    json_encode($globalRows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n"
);

echo "Extracted texts from " . count($pageFiles) . " pages.\n";
echo "Include admin pages: " . ($includeAdmin ? 'yes' : 'no') . "\n";
echo "Per-page docs: $pagesDir\n";
echo "Global CSV: $outDir/app_texts_en.csv\n";
echo "Global JSON: $outDir/app_texts_en.json\n";

/**
 * @return array<int, string>
 */
function collectPageFiles(string $publicDir, bool $includeAdmin): array
{
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($publicDir));
    $files = [];
    foreach ($it as $entry) {
        if (!$entry->isFile()) {
            continue;
        }
        $path = (string)$entry->getPathname();
        if (pathinfo($path, PATHINFO_EXTENSION) !== 'php') {
            continue;
        }
        $norm = str_replace('\\', '/', $path);
        if (str_contains($norm, '/public/api/')) {
            continue;
        }
        if (str_contains($norm, '/public/includes/')) {
            continue;
        }
        if (!$includeAdmin && str_contains($norm, '/public/admin/')) {
            continue;
        }
        $files[] = $path;
    }
    sort($files, SORT_STRING);
    return $files;
}

function cleanupPageDocs(string $pagesDir): void
{
    $files = glob($pagesDir . '/*.md');
    if (!is_array($files)) {
        return;
    }
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
        }
    }
}

function maskPhpBlocks(string $content): string
{
    return (string)preg_replace_callback('/<\?(?:php|=)?[\s\S]*?\?>/i', static function (array $m): string {
        return str_repeat("\n", substr_count($m[0], "\n"));
    }, $content);
}

/**
 * @return array<int, array{line:int,text:string}>
 */
function extractHtmlText(string $masked): array
{
    $rows = [];
    if (!preg_match_all('/>([^<]+)</u', $masked, $matches, PREG_OFFSET_CAPTURE)) {
        return $rows;
    }
    foreach ($matches[1] as $match) {
        $text = normalizeText($match[0]);
        if (!isTextCandidate($text)) {
            continue;
        }
        $rows[] = ['line' => lineFromOffset($masked, (int)$match[1]), 'text' => $text];
    }
    return $rows;
}

/**
 * @return array<int, array{line:int,text:string}>
 */
function extractHtmlAttributes(string $masked): array
{
    $rows = [];
    if (!preg_match_all('/\b(?:placeholder|title|aria-label|alt|value)\s*=\s*["\']([^"\']+)["\']/ui', $masked, $matches, PREG_OFFSET_CAPTURE)) {
        return $rows;
    }
    foreach ($matches[1] as $match) {
        $text = normalizeText($match[0]);
        if (!isTextCandidate($text)) {
            continue;
        }
        $rows[] = ['line' => lineFromOffset($masked, (int)$match[1]), 'text' => $text];
    }
    return $rows;
}

/**
 * @return array<int, array{line:int,text:string}>
 */
function extractScriptStrings(string $masked): array
{
    $rows = [];
    if (!preg_match_all('/<script\b[^>]*>([\s\S]*?)<\/script>/ui', $masked, $scripts, PREG_OFFSET_CAPTURE)) {
        return $rows;
    }
    foreach ($scripts[1] as $scriptMatch) {
        $script = (string)$scriptMatch[0];
        $scriptOffset = (int)$scriptMatch[1];
        if (!preg_match_all('/([\'"])((?:\\\\.|(?!\1).)*)\1/u', $script, $stringMatches, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        foreach ($stringMatches[2] as $str) {
            $text = normalizeText(stripcslashes((string)$str[0]));
            if (!isScriptStringCandidate($text)) {
                continue;
            }
            $rows[] = ['line' => lineFromOffset($masked, $scriptOffset + (int)$str[1]), 'text' => $text];
        }
    }
    return $rows;
}

/**
 * @return array<int, array{line:int,text:string}>
 */
function extractPhpEchoStrings(string $content): array
{
    $rows = [];
    if (!preg_match_all('/\b(?:echo|print)\s+([\'"])((?:\\\\.|(?!\1).)*)\1/u', $content, $matches, PREG_OFFSET_CAPTURE)) {
        return $rows;
    }
    foreach ($matches[2] as $match) {
        $text = normalizeText(stripcslashes((string)$match[0]));
        if (!isTextCandidate($text)) {
            continue;
        }
        $rows[] = ['line' => lineFromOffset($content, (int)$match[1]), 'text' => $text];
    }
    return $rows;
}

/**
 * @param array<int, array{line:int,text:string,type?:string}> $target
 * @param array<int, array{line:int,text:string}> $source
 */
function addMatches(array &$target, array $source, string $type): void
{
    foreach ($source as $row) {
        $target[] = [
            'line' => $row['line'],
            'text' => $row['text'],
            'type' => $type,
        ];
    }
}

/**
 * @param array<int, array{line:int,text:string,type:string}> $rows
 * @return array<int, array{line:int,text:string,type:string}>
 */
function dedupeRows(array $rows): array
{
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $key = $row['line'] . '|' . $row['type'] . '|' . mb_strtolower($row['text']);
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $out[] = $row;
    }
    return $out;
}

function normalizeText(string $text): string
{
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
    return trim($text);
}

function isTextCandidate(string $text): bool
{
    if ($text === '' || mb_strlen($text) < 2) {
        return false;
    }
    if (!preg_match('/\p{L}/u', $text)) {
        return false;
    }
    if (preg_match('/^(?:https?:\/\/|\/[A-Za-z0-9_\-\/.]+$)/u', $text)) {
        return false;
    }
    if (preg_match('/^[A-Za-z0-9_.:-]+$/u', $text)) {
        return false;
    }
    return true;
}

function isScriptStringCandidate(string $text): bool
{
    if (!isTextCandidate($text)) {
        return false;
    }
    if (preg_match('/^(?:GET|POST|PUT|DELETE|PATCH|OPTIONS)$/u', $text)) {
        return false;
    }
    if (preg_match('/^(?:text\/|application\/|image\/)/u', $text)) {
        return false;
    }
    return true;
}

function lineFromOffset(string $text, int $offset): int
{
    return substr_count(substr($text, 0, max(0, $offset)), "\n") + 1;
}

function fileToSlug(string $relativePath): string
{
    $slug = str_replace(['\\', '/'], '__', $relativePath);
    return preg_replace('/[^a-zA-Z0-9_.-]+/', '_', $slug) ?? $slug;
}

/**
 * @param array<int, array{line:int,text:string,type:string}> $rows
 */
function buildPageMarkdown(string $pagePath, array $rows): string
{
    $out = "# Page Text Extraction\n\n";
    $out .= "- Source: `" . $pagePath . "`\n";
    $out .= "- Extracted strings: " . count($rows) . "\n\n";
    $out .= "| Line | Type | Text |\n";
    $out .= "|---:|---|---|\n";

    foreach ($rows as $row) {
        $text = str_replace('|', '\|', $row['text']);
        $out .= "| " . $row['line'] . " | " . $row['type'] . " | " . $text . " |\n";
    }

    return $out;
}

/**
 * @param array<int, array{file:string,line:int,type:string,text:string}> $rows
 */
function buildCsv(array $rows): string
{
    $fh = fopen('php://temp', 'r+');
    fputcsv($fh, ['file', 'line', 'type', 'text'], ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($fh, [$row['file'], $row['line'], $row['type'], $row['text']], ',', '"', '');
    }
    rewind($fh);
    $csv = stream_get_contents($fh);
    fclose($fh);
    return (string)$csv;
}
