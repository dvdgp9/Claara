<?php
/**
 * Backfill `folder_id` into the payload of existing Qdrant chunks.
 *
 * New uploads stamp folder_id at index time (RagProcessor), but chunks indexed
 * before migration 024 have no folder_id and would be excluded by the access
 * filter (fail closed). This script sets folder_id on every existing chunk based
 * on its document's folder assignment in the database.
 *
 * Idempotent. Run after scripts/backfill_voice_access.php has placed documents.
 *
 * Usage:  php scripts/backfill_qdrant_folders.php
 */

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;
use App\Env;
use Rag\QdrantClient;
use Repos\ContextDocsRepo;

$pdo = DB::pdo();
$docsRepo = new ContextDocsRepo();
$qdrant = new QdrantClient(
    Env::get('QDRANT_HOST', 'localhost'),
    (int)Env::get('QDRANT_PORT', 6333)
);

if (!$qdrant->health()) {
    fwrite(STDERR, "Qdrant is not available. Aborting.\n");
    exit(1);
}

$voices = $pdo->query(
    "SELECT slug, rag_collection FROM voices
      WHERE rag_collection IS NOT NULL AND rag_collection <> ''
      ORDER BY id"
)->fetchAll();

$totalUpdated = 0;

foreach ($voices as $voice) {
    $slug = (string)$voice['slug'];
    $collection = (string)$voice['rag_collection'];

    echo "Voice {$slug} -> collection {$collection}\n";
    if (!$qdrant->collectionExists($collection)) {
        echo "  collection missing, skipping\n";
        continue;
    }

    foreach ($docsRepo->listByVoice($slug) as $doc) {
        $folderId = isset($doc['folder_id']) ? (int)$doc['folder_id'] : 0;
        if ($folderId <= 0) {
            echo "  - {$doc['filename']}: no folder_id in DB, skipped\n";
            continue;
        }

        // Match on document_name (== the stored filename), which is robust across
        // both the prefixed ("{slug}_{base}") and legacy unprefixed document_id
        // schemes present in older collections.
        $filename = (string)$doc['filename'];
        $filter = ['must' => [['key' => 'document_name', 'match' => ['value' => $filename]]]];

        $points = $qdrant->countPointsByFilter($collection, $filter);
        if ($points === 0) {
            echo "  - {$filename}: 0 chunks in Qdrant, skipped\n";
            continue;
        }

        $qdrant->setPayloadByFilter($collection, ['folder_id' => $folderId], $filter);
        $totalUpdated += $points;
        echo "  - {$filename}: folder_id={$folderId} set on {$points} chunks\n";
    }
}

echo "\nDone. Chunks updated: {$totalUpdated}.\n";
