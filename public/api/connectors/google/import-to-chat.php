<?php
/**
 * Imports a Google Drive file (picked via the Google Picker) as a chat
 * attachment, mirroring /api/files/upload.php storage and response shape.
 * POST { drive_file_id: string, conversation_id?: int }
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Repos/ChatFilesRepo.php';
require_once __DIR__ . '/../../../../src/Repos/ConversationAccessRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Connectors\ConnectorAccountsRepo;
use Connectors\ConnectorImportException;
use Connectors\ConnectorImportsRepo;
use Connectors\ConnectorItemsRepo;
use Connectors\GoogleDriveImporter;
use Repos\ChatFilesRepo;
use Repos\ConversationAccessRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$body = json_decode(file_get_contents('php://input') ?: '[]', true) ?: [];
$driveFileId = trim((string)($body['drive_file_id'] ?? ''));
$conversationId = isset($body['conversation_id']) ? (int)$body['conversation_id'] : null;

if ($driveFileId === '') {
    Response::error('validation_error', 'drive_file_id is required', 400);
}
if ($conversationId && !(new ConversationAccessRepo())->canChat($conversationId, $user)) {
    Response::error('forbidden', 'You do not have permission to upload files to this conversation', 403);
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
    $fetched = $importer->fetchToTemp($accountId, $driveFileId, 'chat');

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
    $importId = $importsRepo->create($itemId, $accountId, (int)$user['id'], 'chat');
    $importsRepo->markProcessing($importId);

    // Store exactly like /api/files/upload.php
    $storagePath = ChatFilesRepo::getStoragePath();
    if (!is_dir($storagePath)) {
        mkdir($storagePath, 0755, true);
    }
    $storedName = bin2hex(random_bytes(16)) . '.' . $fetched['extension'];
    $destPath = $storagePath . '/' . $storedName;
    if (!rename($fetched['tmp_path'], $destPath)) {
        if (!copy($fetched['tmp_path'], $destPath)) {
            throw new \RuntimeException('Could not store the imported file');
        }
        @unlink($fetched['tmp_path']);
    }
    @chmod($destPath, 0644);

    $repo = new ChatFilesRepo();
    try {
        $fileId = $repo->create([
            'user_id' => (int)$user['id'],
            'conversation_id' => $conversationId,
            'original_name' => $fetched['name'],
            'stored_name' => $storedName,
            'mime_type' => $fetched['mime'],
            'size_bytes' => $fetched['size'],
        ]);
    } catch (\Throwable $e) {
        @unlink($destPath);
        throw $e;
    }

    $itemsRepo->updateStatus($itemId, 'imported');
    $importsRepo->markCompleted($importId, null, [
        'chat_file_id' => $fileId,
        'exported' => $fetched['exported'],
        'mime' => $fetched['mime'],
    ]);

    Response::json([
        'success' => true,
        'file_id' => $fileId,
        'url' => '/api/files/serve.php?id=' . $fileId,
        'name' => $fetched['name'],
        'mime_type' => $fetched['mime'],
        'size' => $fetched['size'],
        'drive' => true,
    ]);
} catch (ConnectorImportException $e) {
    if ($itemId) {
        $itemsRepo->updateStatus($itemId, 'error', $e->getMessage());
    }
    if ($importId) {
        $importsRepo->markFailed($importId, $e->getMessage());
    }
    Response::error($e->errorCode, $e->getMessage(), 400);
} catch (\Throwable $e) {
    error_log('[connectors] import-to-chat failed: ' . $e->getMessage());
    if ($itemId) {
        $itemsRepo->updateStatus($itemId, 'error', $e->getMessage());
    }
    if ($importId) {
        $importsRepo->markFailed($importId, $e->getMessage());
    }
    Response::error('import_failed', 'Could not import the file from Google Drive. Please try again or reconnect your account.', 502);
}
