<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$english = require $root . '/resources/i18n/en.php';
$spanish = require $root . '/resources/i18n/es.php';
$source = (string)file_get_contents($root . '/public/app.php');
$passed = 0;
$failed = 0;

function chatCheck(bool $condition, string $label): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "PASS {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$required = [
    'chat.long_conversation', 'chat.long_conversation_help', 'chat.new_activity', 'chat.refresh',
    'chat.teammate_busy', 'chat.ready', 'chat.greeting.morning', 'chat.greeting.afternoon',
    'chat.greeting.evening', 'chat.start_help', 'chat.ask_anything', 'chat.write_message',
    'chat.send', 'chat.attach_file', 'chat.generate_image', 'chat.search_web', 'chat.remove_all',
    'chat.files_disabled_image_mode', 'chat.choose_option', 'chat.quick_actions', 'chat.drop_files',
    'chat.scroll_latest', 'chat.edit', 'chat.regenerate', 'chat.edit_selection', 'chat.apply_changes',
    'chat.report.title', 'chat.report.send', 'chat.share.title', 'chat.share.people',
    'chat.share.departments', 'chat.share.can_view', 'chat.share.can_chat', 'chat.share.save',
    'chat.move.title', 'chat.move.root', 'chat.source.device', 'chat.source.drive',
    'chat.source.onedrive', 'chat.copy_code', 'chat.copy_response', 'chat.download',
    'chat.download_pdf', 'chat.download_word', 'chat.sources', 'chat.folder_name',
    'chat.rename', 'chat.delete', 'chat.empty_shared', 'chat.read_only', 'chat.error.generic',
];
foreach ($required as $key) {
    chatCheck(isset($english[$key], $spanish[$key]), "required chat key exists: {$key}");
}

preg_match_all("/I18n::translate\('([^']+)'/", $source, $phpMatches);
foreach (array_unique($phpMatches[1] ?? []) as $key) {
    chatCheck(isset($english[$key], $spanish[$key]), "PHP chat key is defined: {$key}");
}

foreach (array_keys($english) as $key) {
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$english[$key], $enMatches);
    preg_match_all('/\{([a-z][a-z0-9_]*)\}/i', (string)$spanish[$key], $esMatches);
    $en = array_values(array_unique($enMatches[1] ?? []));
    $es = array_values(array_unique($esMatches[1] ?? []));
    sort($en);
    sort($es);
    chatCheck($en === $es, "placeholder parity: {$key}");
}

chatCheck(str_contains($source, 'I18n::javascriptCatalogPrefixJson('), 'Chat JavaScript catalog is emitted');
chatCheck(str_contains($source, 'const chatT = '), 'Chat JavaScript uses a translation helper');

$forbiddenLiterals = [
    '>Long conversation<', '>Ready to help<', 'placeholder="Ask Claara anything"',
    '>Share conversation<', '>Move conversation<', '>Apply changes<', '>Send report<',
];
foreach ($forbiddenLiterals as $literal) {
    chatCheck(!str_contains($source, $literal), "critical literal removed: {$literal}");
}

echo "RESULT {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
