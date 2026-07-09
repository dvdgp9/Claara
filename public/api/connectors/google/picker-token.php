<?php
/**
 * Returns a short-lived Google access token plus the browser keys the Google
 * Picker needs. The refresh token never leaves the server.
 * GET /api/connectors/google/picker-token.php
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';

use App\Env;
use App\Response;
use Auth\AuthService;
use Connectors\ConnectorAccountsRepo;
use Connectors\GoogleTokenService;

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    Response::error('method_not_allowed', 'GET only', 405);
}

$user = AuthService::requireAuth();

$accountsRepo = new ConnectorAccountsRepo();
$account = null;
foreach ($accountsRepo->listForUser((int)$user['id']) as $candidate) {
    if ($candidate['provider_key'] === 'google_drive' && $candidate['status'] === 'connected') {
        $account = $candidate;
        break;
    }
}

if (!$account) {
    Response::error('not_connected', 'No connected Google Drive account. Connect one in Connectors first.', 409);
}

$clientId = trim((string)(Env::get('GOOGLE_OAUTH_CLIENT_ID') ?? ''));
$apiKey = trim((string)(Env::get('GOOGLE_PICKER_API_KEY') ?? ''));
if ($clientId === '' || $apiKey === '') {
    Response::error('not_configured', 'Google Drive is not configured on this server', 503);
}

try {
    $token = (new GoogleTokenService())->freshAccessToken((int)$account['id']);
} catch (\Throwable $e) {
    error_log('[connectors] picker-token refresh failed: ' . $e->getMessage());
    Response::error('token_refresh_failed', 'Could not refresh Google access. Please reconnect your account.', 409);
}

$expiresIn = null;
if (!empty($token['expires_at'])) {
    $expiresIn = max(0, strtotime((string)$token['expires_at']) - time());
}

Response::json([
    'account_id' => (int)$account['id'],
    'access_token' => $token['access_token'],
    'expires_in' => $expiresIn,
    'api_key' => $apiKey,
    // The GCP project number the Picker needs as appId so drive.file grants
    // apply to picked files (numeric prefix of the OAuth client id).
    'app_id' => explode('-', $clientId, 2)[0],
]);
