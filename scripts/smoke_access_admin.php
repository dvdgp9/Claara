<?php
/**
 * Smoke test for the global-access-level ADMIN operations (repos behind the
 * endpoints). Runs entirely inside a TRANSACTION that is rolled back, so prod
 * data is untouched.
 *
 * Usage:  php scripts/smoke_access_admin.php
 */

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;
use Repos\AccessLevelsRepo;
use Repos\VoiceAccessListRepo;
use Voices\VoiceAccessResolver;

$pdo = DB::pdo();
$pass = 0; $fail = 0;
function check(string $label, $got, $want) {
    global $pass, $fail;
    $ok = $got === $want;
    $ok ? $pass++ : $fail++;
    printf("[%s] %s  (got=%s want=%s)\n", $ok ? 'PASS' : 'FAIL', $label,
        json_encode($got), json_encode($want));
}

$pdo->beginTransaction();
try {
    $levels = new AccessLevelsRepo($pdo);

    // CRUD + ranks.
    $tech = $levels->create('AdmTech', 'adm-tech', 50, false);
    $mgr  = $levels->create('AdmManager', 'adm-manager', 100, false);
    $dir  = $levels->create('AdmDirector', 'adm-director', 150, false);
    check('create returns ids', $tech > 0 && $mgr > 0 && $dir > 0, true);

    // Reorder: director, manager, tech (highest first) -> ranks 30,20,10.
    $rank = 30;
    foreach ([$dir, $mgr, $tech] as $id) { $levels->setRank($id, $rank); $rank -= 10; }
    check('reorder: director rank > tech rank',
        (int)$levels->getById($dir)['rank'] > (int)$levels->getById($tech)['rank'], true);

    // Default toggling is exclusive.
    $levels->setDefault($mgr);
    check('setDefault marks manager', (int)$levels->getById($mgr)['is_default'], 1);
    $levels->setDefault($dir);
    check('setDefault is exclusive (manager cleared)', (int)$levels->getById($mgr)['is_default'], 0);
    check('setDefault marks director', (int)$levels->getById($dir)['is_default'], 1);

    // Assign a user a level, then delete that level -> user falls back to default.
    $levels->assignUser(11, $tech);
    check('assignUser sets level', (int)$levels->getUserLevel(11)['id'], $tech);

    // Point a voice minimum at the level, then delete -> minimum cleared.
    $pdo->prepare('UPDATE voices SET access_mode="level", min_access_level_id=? WHERE id=3')->execute([$tech]);

    // Emulate delete.php's reassignment logic.
    $defaultId = (int)$levels->getDefault()['id'];
    $pdo->prepare('UPDATE users SET access_level_id=? WHERE access_level_id=?')->execute([$defaultId, $tech]);
    $pdo->prepare('UPDATE voices SET min_access_level_id=NULL WHERE min_access_level_id=?')->execute([$tech]);
    $pdo->prepare('UPDATE voice_folders SET required_level_id=NULL WHERE required_level_id=?')->execute([$tech]);
    $levels->delete($tech);
    check('after delete: level gone', $levels->getById($tech), null);
    check('after delete: user 11 moved to default', (int)$levels->getUserLevel(11)['id'], $defaultId);
    check('after delete: voice minimum cleared',
        $pdo->query('SELECT min_access_level_id FROM voices WHERE id=3')->fetchColumn(), null);

    // Voice access-list add/remove.
    $list = new VoiceAccessListRepo($pdo);
    $pdo->prepare('UPDATE voices SET access_mode="list" WHERE id=3')->execute();
    $list->add(3, 11);
    check('list add -> isListed', $list->isListed(11, 3), true);
    $resolver = new VoiceAccessResolver($pdo);
    $conv = $pdo->query('SELECT * FROM voices WHERE id=3')->fetch();
    check('listed user 11 has access', $resolver->hasVoiceAccess(11, $conv), true);
    $list->remove(3, 11);
    check('list remove -> not listed', $list->isListed(11, 3), false);
    check('removed user 11 no access', $resolver->hasVoiceAccess(11, $conv), false);
} finally {
    $pdo->rollBack();
}

// Verify rollback left no residue.
$leftover = (new AccessLevelsRepo($pdo))->getBySlug('adm-manager');
check('rollback: synthetic levels gone', $leftover, null);

printf("\n==== %d passed, %d failed ====\n", $pass, $fail);
exit($fail === 0 ? 0 : 1);
