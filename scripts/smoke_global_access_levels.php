<?php
/**
 * Smoke test for the GLOBAL ACCESS LEVELS resolver (migration 026).
 *
 * Part A asserts the live (backfilled) state with the new resolver: 'list' mode
 * preserves exactly the pre-cutover access.
 * Part B opens a TRANSACTION, builds a synthetic 'level' mode scenario, asserts
 * rank gating + folder minimums, then ROLLS BACK so prod data is untouched.
 *
 * Read-only against real data; only Part B writes, inside a rolled-back tx.
 *
 * Usage:  php scripts/smoke_global_access_levels.php
 */

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;
use Repos\AccessLevelsRepo;
use Voices\VoiceAccessResolver;
use Repos\VoicesRepo;

$pdo = DB::pdo();
$resolver = new VoiceAccessResolver($pdo);
$voices = new VoicesRepo();

$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    $ok = $got === $want;
    $ok ? $pass++ : $fail++;
    printf("[%s] %s  (got=%s want=%s)\n", $ok ? 'PASS' : 'FAIL', $label,
        json_encode($got), json_encode($want));
}

$lex = $voices->findBySlug('lex');
$testv = $voices->findBySlug('test-voice');
$conv = $voices->findBySlug('conveniex');

echo "=== Part A: live 'list' mode (cutover preserved access) ===\n";
// Lex: list {1,9}; user 11 is a responsible (bypass) -> still in.
check('lex: user 1 (superadmin) access', $resolver->hasVoiceAccess(1, $lex), true);
check('lex: user 9 (listed) access', $resolver->hasVoiceAccess(9, $lex), true);
check('lex: user 11 (responsible) access', $resolver->hasVoiceAccess(11, $lex), true);
check('lex: ghost user 99999 no access', $resolver->hasVoiceAccess(99999, $lex), false);
check('lex: user 11 sees all folders (responsible bypass)',
    count($resolver->resolveAccessibleFolderIds(11, $lex)) > 0, true);

// test-voice / conveniex: empty list -> only superadmins.
check('test-voice: user 11 no access', $resolver->hasVoiceAccess(11, $testv), false);
check('test-voice: user 1 (superadmin) access', $resolver->hasVoiceAccess(1, $testv), true);
check('conveniex: user 11 no access', $resolver->hasVoiceAccess(11, $conv), false);
check('conveniex: user 11 no folders', $resolver->resolveAccessibleFolderIds(11, $conv), []);

echo "\n=== Part B: synthetic 'level' mode (transaction, rolled back) ===\n";
$pdo->beginTransaction();
try {
    $levels = new AccessLevelsRepo($pdo);
    $techId = $levels->create('SmokeTech', 'smoke-tech', 50, false);
    $dirId  = $levels->create('SmokeDirector', 'smoke-director', 200, false);

    // conveniex -> level mode, minimum = Tech (rank 50).
    $convId = (int)$conv['id'];
    $pdo->prepare('UPDATE voices SET access_mode = "level", min_access_level_id = ? WHERE id = ?')
        ->execute([$techId, $convId]);

    // Gate the "CEOs" folder (id 8) to Director (rank 200).
    $pdo->prepare('UPDATE voice_folders SET required_level_id = ? WHERE id = 8')
        ->execute([$dirId]);

    // User 11 at Tech: enters conveniex, sees General (NULL) but NOT CEOs.
    $levels->assignUser(11, $techId);
    check('level: tech user 11 enters conveniex', $resolver->hasVoiceAccess(11, $conv), true);
    $folders = $resolver->resolveAccessibleFolderIds(11, $conv);
    check('level: tech user 11 sees General folder 3', in_array(3, $folders, true), true);
    check('level: tech user 11 does NOT see CEOs folder 8', in_array(8, $folders, true), false);

    // Promote user 11 to Director: now sees CEOs too.
    $levels->assignUser(11, $dirId);
    $folders2 = $resolver->resolveAccessibleFolderIds(11, $conv);
    check('level: director user 11 sees CEOs folder 8', in_array(8, $folders2, true), true);

    // A below-minimum user (no level, rank 0) cannot enter a min=Tech voice.
    $pdo->prepare('UPDATE users SET access_level_id = NULL WHERE id = 11')->execute();
    check('level: no-level user 11 blocked from min=Tech voice',
        $resolver->hasVoiceAccess(11, $conv), false);

    // Everyone-minimum (NULL): no-level user can enter.
    $pdo->prepare('UPDATE voices SET min_access_level_id = NULL WHERE id = ?')->execute([$convId]);
    check('level: everyone-min lets no-level user 11 in',
        $resolver->hasVoiceAccess(11, $conv), true);
} finally {
    $pdo->rollBack();
}

echo "\n=== verify rollback left prod untouched ===\n";
$conv2 = $voices->findBySlug('conveniex');
check('conveniex back to list mode', $conv2['access_mode'] ?? null, 'list');
check('SmokeTech level gone', (new AccessLevelsRepo($pdo))->getBySlug('smoke-tech'), null);

printf("\n==== %d passed, %d failed ====\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
