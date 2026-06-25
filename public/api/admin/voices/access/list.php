<?php
/**
 * GET /api/admin/voices/access/list.php?slug=lex
 * Users and their assigned access profile in this voice, plus the profile list.
 */
require_once __DIR__ . '/../documents/_helpers.php';
require_once __DIR__ . '/../../../../../src/Repos/UsersRepo.php';

use App\Response;
use Repos\VoiceProfilesRepo;
use Repos\UsersRepo;

[, $voice] = require_voice_document_context();

$voiceId = (int)$voice['id'];
$profiles = new VoiceProfilesRepo();
$assignments = $profiles->assignmentsForVoice($voiceId);

$profileList = array_map(static fn(array $p): array => [
    'id' => (int)$p['id'],
    'name' => (string)$p['name'],
], $profiles->listByVoice($voiceId));

$users = array_map(static function (array $u) use ($assignments): array {
    $uid = (int)$u['id'];
    return [
        'id' => $uid,
        'name' => trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? '')),
        'email' => (string)($u['email'] ?? ''),
        'is_superadmin' => (int)($u['is_superadmin'] ?? 0) === 1,
        'status' => (string)($u['status'] ?? ''),
        'profile_id' => $assignments[$uid] ?? null,
    ];
}, (new UsersRepo())->listAll());

Response::json([
    'success' => true,
    'profiles' => $profileList,
    'users' => $users,
]);
