<?php
/**
 * GET /api/admin/voices/profiles/list.php?slug=lex
 * Access profiles for a voice, with their granted folders and assigned-user count.
 */
require_once __DIR__ . '/../documents/_helpers.php';

use App\Response;
use Repos\VoiceProfilesRepo;

[, $voice] = require_voice_document_context();

$voiceId = (int)$voice['id'];
$repo = new VoiceProfilesRepo();
$counts = $repo->assignedUserCounts($voiceId);

$profiles = array_map(static function (array $p) use ($counts): array {
    return [
        'id' => (int)$p['id'],
        'name' => (string)$p['name'],
        'slug' => (string)$p['slug'],
        'description' => $p['description'],
        'is_default' => (int)$p['is_default'] === 1,
        'rank' => (int)($p['rank'] ?? 0),
        'assigned_users' => $counts[(int)$p['id']] ?? 0,
    ];
}, $repo->listByVoice($voiceId));

Response::json(['success' => true, 'profiles' => $profiles]);
