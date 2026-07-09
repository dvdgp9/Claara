<?php
/**
 * Disconnects the session user's Google Drive account.
 * POST /api/connectors/google/disconnect.php  Body JSON: { account_id: int }
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use Connectors\ConnectorAccountsRepo;
use Connectors\ConnectorTokensRepo;
use Connectors\GoogleDriveProvider;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', 'POST only', 405);
}

$user = AuthService::requireAuth();
Session::requireCsrf();

$body = json_decode(file_get_contents('php://input') ?: '[]', true);
$accountId = (int)($body['account_id'] ?? 0);
if ($accountId <= 0) {
    Response::error('invalid_request', 'account_id is required', 400);
}

$accountsRepo = new ConnectorAccountsRepo();
$account = $accountsRepo->findForUser($accountId, (int)$user['id']);
if (!$account || $account['provider_key'] !== 'google_drive') {
    Response::error('not_found', 'Connected account not found', 404);
}

$tokensRepo = new ConnectorTokensRepo();

// Best effort: revoke the grant at Google so it disappears from the user's
// third-party access list. Local cleanup happens regardless.
try {
    $tokens = $tokensRepo->findDecryptedForAccount($accountId);
    $tokenToRevoke = $tokens['refresh_token'] ?? $tokens['access_token'] ?? null;
    if ($tokenToRevoke) {
        (new GoogleDriveProvider())->revokeToken($tokenToRevoke);
    }
} catch (\Throwable $e) {
    error_log('[connectors] Google revoke failed for account ' . $accountId . ': ' . $e->getMessage());
}

$tokensRepo->deleteForAccount($accountId);
$accountsRepo->markDisconnected($accountId, (int)$user['id']);

Response::json(['success' => true]);
