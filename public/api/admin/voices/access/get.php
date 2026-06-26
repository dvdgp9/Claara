<?php
/**
 * GET /api/admin/voices/access/get.php?slug=lex
 * Returns the voice access policy (mode + minimum level), the global access
 * levels, and every user with their global level and allow-list membership.
 */
require_once __DIR__ . '/../documents/_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/UsersRepo.php';
require_once __DIR__ . '/../../../../../src/Repos/AccessLevelsRepo.php';
require_once __DIR__ . '/../../../../../src/Repos/VoiceAccessListRepo.php';

use App\Response;
use Repos\UsersRepo;
use Repos\AccessLevelsRepo;
use Repos\VoiceAccessListRepo;

[, $voice] = require_voice_document_context();
$voiceId = (int)$voice['id'];

$levelsRepo = new AccessLevelsRepo();
$levels = array_map(static fn(array $l): array => [
    'id' => (int)$l['id'],
    'name' => (string)$l['name'],
    'rank' => (int)$l['rank'],
], $levelsRepo->listAll());
$levelNames = [];
foreach ($levels as $l) {
    $levelNames[$l['id']] = $l['name'];
}

$listed = array_fill_keys((new VoiceAccessListRepo())->userIds($voiceId), true);

$users = array_map(static function (array $u) use ($listed, $levelNames): array {
    $uid = (int)$u['id'];
    $levelId = isset($u['access_level_id']) && $u['access_level_id'] !== null
        ? (int)$u['access_level_id'] : null;
    return [
        'id' => $uid,
        'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
        'email' => (string)($u['email'] ?? ''),
        'is_superadmin' => (int)($u['is_superadmin'] ?? 0) === 1,
        'level_id' => $levelId,
        'level_name' => $levelId !== null ? ($levelNames[$levelId] ?? null) : null,
        'listed' => isset($listed[$uid]),
    ];
}, (new UsersRepo())->listAll());

Response::json([
    'success' => true,
    'access_mode' => (string)($voice['access_mode'] ?? 'level'),
    'min_access_level_id' => isset($voice['min_access_level_id']) && $voice['min_access_level_id'] !== null
        ? (int)$voice['min_access_level_id'] : null,
    'levels' => $levels,
    'users' => $users,
]);
