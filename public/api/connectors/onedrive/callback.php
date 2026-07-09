<?php
/**
 * Microsoft OAuth callback: exchanges the code, stores the account and its
 * encrypted tokens, then redirects back to /connectors.php.
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/App/Session.php';

use App\Session;
use Connectors\ConnectorAccountsRepo;
use Connectors\ConnectorTokensRepo;
use Connectors\MicrosoftOneDriveProvider;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

function connectors_redirect(string $status, string $detail = ''): void
{
    $query = ['connect' => $status];
    if ($detail !== '') {
        $query['detail'] = mb_substr($detail, 0, 120);
    }
    header('Location: /connectors.php?' . http_build_query($query));
    exit;
}

$pending = $_SESSION['connector_oauth'] ?? null;
unset($_SESSION['connector_oauth']);

$state = (string)($_GET['state'] ?? '');
if (
    !is_array($pending)
    || $pending['provider'] !== 'onedrive'
    || (int)$pending['user_id'] !== (int)$user['id']
    || $state === ''
    || !hash_equals((string)$pending['state'], $state)
    || (time() - (int)$pending['created_at']) > 600
) {
    connectors_redirect('error', 'Invalid or expired authorization state');
}

if (!empty($_GET['error'])) {
    connectors_redirect($_GET['error'] === 'access_denied' ? 'cancelled' : 'error', (string)$_GET['error']);
}

$code = (string)($_GET['code'] ?? '');
if ($code === '') {
    connectors_redirect('error', 'Missing authorization code');
}

try {
    $provider = new MicrosoftOneDriveProvider();
    $tokenData = $provider->exchangeCode($code);
    $profile = $provider->fetchAccountProfile((string)$tokenData['access_token']);

    $grantedScopes = preg_split('/\s+/', trim((string)($tokenData['scope'] ?? ''))) ?: [];
    $hasFilesRead = false;
    foreach ($grantedScopes as $scope) {
        if (strcasecmp($scope, 'Files.Read') === 0 || str_ends_with($scope, '/Files.Read')) {
            $hasFilesRead = true;
            break;
        }
    }
    if (!$hasFilesRead) {
        connectors_redirect('error', 'File access was not granted');
    }

    $accountsRepo = new ConnectorAccountsRepo();
    $accountId = $accountsRepo->createOrUpdate((int)$user['id'], $provider->providerKey(), $profile, $grantedScopes);
    (new ConnectorTokensRepo())->saveForAccount($accountId, $tokenData);
} catch (\Throwable $e) {
    error_log('[connectors] OneDrive callback failed: ' . $e->getMessage());
    connectors_redirect('error', 'Could not complete the OneDrive connection');
}

connectors_redirect('success');
