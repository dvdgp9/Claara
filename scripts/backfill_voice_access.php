<?php
/**
 * Backfill for migration 024 (voice access profiles & document folders).
 *
 * Idempotent. For every voice it ensures:
 *   1. a root folder ("General"),
 *   2. a default "Full access" profile granted on that root folder,
 *   3. every existing document is placed into the root folder,
 *   4. existing binary voice grants (user_feature_access, feature_type='voice')
 *      are mirrored into user_voice_profiles as the Full access profile, so
 *      nobody loses access when profiles become the source of truth.
 *
 * Existing user_feature_access rows are left untouched (Phase E reconciles them).
 * Safe to run multiple times.
 *
 * Usage:  php scripts/backfill_voice_access.php
 */

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;
use Repos\VoiceFoldersRepo;
use Repos\VoiceProfilesRepo;

$pdo = DB::pdo();
$folders = new VoiceFoldersRepo($pdo);
$profiles = new VoiceProfilesRepo($pdo);

$voices = $pdo->query('SELECT id, slug, name FROM voices ORDER BY id')->fetchAll();
if (!$voices) {
    echo "No voices found. Nothing to backfill.\n";
    exit(0);
}

$totalDocsMoved = 0;
$totalAssignments = 0;

foreach ($voices as $voice) {
    $voiceId = (int)$voice['id'];
    $slug = (string)$voice['slug'];
    echo "Voice #{$voiceId} ({$slug} — {$voice['name']})\n";

    // 1 + 2 + 3: root folder, default profile, grant.
    $rootId = $folders->ensureRootFolder($voiceId);
    $profileId = $profiles->ensureDefaultProfile($voiceId);
    $profiles->grantFolder($rootId, $profileId);
    echo "  root folder #{$rootId}, full-access profile #{$profileId} granted\n";

    // 4: place existing documents into the root folder.
    $move = $pdo->prepare(
        'UPDATE context_documents
            SET folder_id = :root
          WHERE folder_id IS NULL
            AND (voice_id = :vid OR (voice_id IS NULL AND target_slug = :slug))'
    );
    $move->execute([':root' => $rootId, ':vid' => $voiceId, ':slug' => $slug]);
    $moved = $move->rowCount();
    $totalDocsMoved += $moved;
    echo "  documents placed into root: {$moved}\n";

    // 5: mirror binary voice grants into user_voice_profiles.
    $grants = $pdo->prepare(
        "SELECT user_id FROM user_feature_access
          WHERE feature_type = 'voice' AND feature_slug = ? AND enabled = 1"
    );
    $grants->execute([$slug]);
    $userIds = array_map('intval', $grants->fetchAll(\PDO::FETCH_COLUMN) ?: []);
    $assigned = 0;
    foreach ($userIds as $uid) {
        if ($profiles->getUserProfile($uid, $voiceId) === null) {
            $profiles->assignUser($uid, $voiceId, $profileId);
            $assigned++;
        }
    }
    $totalAssignments += $assigned;
    echo "  voice grants mirrored to profiles: {$assigned} new (of " . count($userIds) . " granted users)\n";
}

echo "\nDone. Documents placed: {$totalDocsMoved}. New profile assignments: {$totalAssignments}.\n";
