<?php
/**
 * Imports a Google Drive file (picked via the Google Picker) as a voice
 * knowledge document, mirroring documents/upload.php.
 * POST ?slug=<voice>  Body JSON: { drive_file_id: string, folder_id?: int, description?: string }
 */

require_once __DIR__ . '/_helpers.php';

use App\Response;
use App\Session;
use Connectors\ConnectorAccountsRepo;
use Connectors\ConnectorImportException;
use Connectors\ConnectorImportsRepo;
use Connectors\ConnectorItemsRepo;
use Connectors\GoogleDriveImporter;
use Repos\ContextDocsRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

[$user, $voice] = require_voice_document_context();
Session::requireCsrf();

$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$driveFileId = trim((string)($body['drive_file_id'] ?? ''));
if ($driveFileId === '') {
    Response::error('validation_error', 'drive_file_id is required', 400);
}

$accountsRepo = new ConnectorAccountsRepo();
$account = null;
foreach ($accountsRepo->listForUser((int)$user['id']) as $candidate) {
    if ($candidate['provider_key'] === 'google_drive' && $candidate['status'] === 'connected') {
        $account = $candidate;
        break;
    }
}
if (!$account) {
    Response::error('not_connected', 'No connected Google Drive account', 409);
}
$accountId = (int)$account['id'];

$itemsRepo = new ConnectorItemsRepo();
$importsRepo = new ConnectorImportsRepo();
$itemId = null;
$importId = null;

try {
    $importer = new GoogleDriveImporter();
    $fetched = $importer->fetchToTemp($accountId, $driveFileId, 'voice');

    $itemId = $itemsRepo->upsertSelected($accountId, 'google_drive', [
        'external_item_id' => $driveFileId,
        'item_type' => 'file',
        'name' => $fetched['name'],
        'mime_type' => $fetched['mime'],
        'source_url' => $fetched['metadata']['webViewLink'] ?? null,
        'external_version' => $fetched['metadata']['version'] ?? null,
        'checksum' => $fetched['metadata']['md5Checksum'] ?? null,
        'size_bytes' => $fetched['size'],
    ]);
    $importId = $importsRepo->create($itemId, $accountId, (int)$user['id'], 'voice:' . $voice['slug']);
    $importsRepo->markProcessing($importId);

    // Store exactly like documents/upload.php (unique filename in the voice dir).
    $repo = new ContextDocsRepo();
    $filename = ContextDocsRepo::sanitizeFilename($fetched['name']);
    $targetPath = voice_documents_path($voice['slug']);
    $baseName = pathinfo($filename, PATHINFO_FILENAME);
    $ext = pathinfo($filename, PATHINFO_EXTENSION) ?: $fetched['extension'];
    if (!str_contains($filename, '.')) {
        $filename = $baseName . '.' . $ext;
    }
    $counter = 1;
    while (file_exists($targetPath . '/' . $filename) && $counter < 100) {
        $filename = $baseName . '_' . $counter . '.' . $ext;
        $counter++;
    }
    $destPath = $targetPath . '/' . $filename;

    if (!rename($fetched['tmp_path'], $destPath)) {
        if (!copy($fetched['tmp_path'], $destPath)) {
            throw new \RuntimeException('Could not save the file');
        }
        @unlink($fetched['tmp_path']);
    }
    @chmod($destPath, 0644);

    // Resolve the target folder like upload.php: explicit folder_id or the root.
    $folders = new \Repos\VoiceFoldersRepo();
    $voiceId = (int)($voice['id'] ?? 0);
    $folderId = $folders->ensureRootFolder($voiceId);
    if (isset($body['folder_id']) && (int)$body['folder_id'] > 0) {
        $candidate = $folders->getById((int)$body['folder_id']);
        if ($candidate && (int)$candidate['voice_id'] === $voiceId) {
            $folderId = (int)$candidate['id'];
        }
    }

    try {
        $id = $repo->create([
            'target' => 'lex',
            'target_type' => 'voice',
            'target_slug' => $voice['slug'],
            'voice_id' => $voice['id'] ?? null,
            'folder_id' => $folderId,
            'filename' => $filename,
            'original_filename' => $fetched['name'],
            'file_extension' => strtolower($ext),
            'file_size' => $fetched['size'],
            'status' => 'active',
            'description' => trim((string)($body['description'] ?? '')) ?: null,
            'created_by' => $user['id'],
        ]);
    } catch (\Throwable $e) {
        @unlink($destPath);
        throw $e;
    }

    $itemsRepo->updateStatus($itemId, 'imported');
    $importsRepo->markCompleted($importId, $id, [
        'exported' => $fetched['exported'],
        'mime' => $fetched['mime'],
        'voice_slug' => $voice['slug'],
        'folder_id' => $folderId,
    ]);

    Response::json([
        'success' => true,
        'document' => $repo->getById($id),
        'drive' => true,
    ], 201);
} catch (ConnectorImportException $e) {
    if ($itemId) {
        $itemsRepo->updateStatus($itemId, 'error', $e->getMessage());
    }
    if ($importId) {
        $importsRepo->markFailed($importId, $e->getMessage());
    }
    Response::error($e->errorCode, $e->getMessage(), 400);
} catch (\Throwable $e) {
    error_log('[connectors] import-drive (voice) failed: ' . $e->getMessage());
    if ($itemId) {
        $itemsRepo->updateStatus($itemId, 'error', $e->getMessage());
    }
    if ($importId) {
        $importsRepo->markFailed($importId, $e->getMessage());
    }
    Response::error('import_failed', 'Could not import the file from Google Drive. Please try again or reconnect your account.', 502);
}
