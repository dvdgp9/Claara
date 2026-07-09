<?php
/**
 * Starts the OneDrive OAuth flow.
 * GET /api/connectors/onedrive/start.php — redirects to Microsoft's consent screen.
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/App/Session.php';

use App\Session;
use Connectors\MicrosoftOneDriveProvider;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

try {
    $provider = new MicrosoftOneDriveProvider();
} catch (\RuntimeException $e) {
    http_response_code(503);
    exit('OneDrive is not configured on this server.');
}

$state = bin2hex(random_bytes(32));
$_SESSION['connector_oauth'] = [
    'provider' => $provider->providerKey(),
    'state' => $state,
    'user_id' => (int)$user['id'],
    'created_at' => time(),
];

header('Location: ' . $provider->authorizationUrl($state));
exit;
