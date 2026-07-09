<?php
/**
 * Lists a OneDrive folder for Claara's own picker modal. The Microsoft token
 * stays server-side; the browser only ever sees file/folder metadata.
 * GET /api/connectors/onedrive/browse.php[?item_id=<folder id>]
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';

use App\Response;
use Auth\AuthService;
use Connectors\ConnectorAccountsRepo;
use Connectors\ConnectorTokenService;
use Connectors\MicrosoftOneDriveProvider;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();

$accountsRepo = new ConnectorAccountsRepo();
$account = null;
foreach ($accountsRepo->listForUser((int)$user['id']) as $candidate) {
    if ($candidate['provider_key'] === 'onedrive' && $candidate['status'] === 'connected') {
        $account = $candidate;
        break;
    }
}
if (!$account) {
    Response::error('not_connected', 'No connected OneDrive account. Connect one in Connectors first.', 409);
}

$itemId = trim((string)($_GET['item_id'] ?? ''));
if ($itemId !== '' && !preg_match('/^[A-Za-z0-9!._-]{5,250}$/', $itemId)) {
    Response::error('validation_error', 'Invalid item id', 400);
}

try {
    $token = (new ConnectorTokenService(new MicrosoftOneDriveProvider()))
        ->freshAccessToken((int)$account['id'])['access_token'];

    $base = $itemId === ''
        ? 'https://graph.microsoft.com/v1.0/me/drive/root/children'
        : 'https://graph.microsoft.com/v1.0/me/drive/items/' . rawurlencode($itemId) . '/children';
    $url = $base . '?' . http_build_query([
        '$select' => 'id,name,size,file,folder,lastModifiedDateTime',
        '$orderby' => 'name',
        '$top' => 200,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
    ]);
    $response = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    $data = $response !== false ? json_decode((string)$response, true) : null;
    if ($status !== 200 || !is_array($data)) {
        throw new \RuntimeException('Graph children request failed with HTTP ' . $status);
    }

    $items = [];
    foreach (($data['value'] ?? []) as $entry) {
        $isFolder = isset($entry['folder']);
        $items[] = [
            'id' => (string)$entry['id'],
            'name' => (string)($entry['name'] ?? ''),
            'type' => $isFolder ? 'folder' : 'file',
            'size' => isset($entry['size']) ? (int)$entry['size'] : 0,
            'mime' => $isFolder ? null : ($entry['file']['mimeType'] ?? null),
            'extension' => $isFolder ? null : strtolower((string)pathinfo((string)($entry['name'] ?? ''), PATHINFO_EXTENSION)),
            'child_count' => $isFolder ? (int)($entry['folder']['childCount'] ?? 0) : null,
            'modified_at' => $entry['lastModifiedDateTime'] ?? null,
        ];
    }

    // Folders first, then files, both alphabetically (Graph already sorts by name).
    usort($items, static fn($a, $b) => ($a['type'] === $b['type']) ? strcasecmp($a['name'], $b['name']) : ($a['type'] === 'folder' ? -1 : 1));

    Response::json(['items' => $items]);
} catch (\Throwable $e) {
    error_log('[connectors] OneDrive browse failed: ' . $e->getMessage());
    Response::error('browse_failed', 'Could not load your OneDrive files. Try reconnecting your account.', 502);
}
