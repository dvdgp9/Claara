<?php
/**
 * Starts the Google Drive OAuth flow.
 * GET /api/connectors/google/start.php — redirects to Google's consent screen.
 */

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/App/Session.php';

use App\Session;
use Connectors\GoogleDriveProvider;

Session::start();
$user = Session::user();
if (!$user) {
    header('Location: /login.php');
    exit;
}

try {
    $provider = new GoogleDriveProvider();
} catch (\RuntimeException $e) {
    http_response_code(503);
    exit('Google Drive is not configured on this server.');
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
