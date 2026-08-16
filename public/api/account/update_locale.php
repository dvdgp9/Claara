<?php
require_once __DIR__ . '/../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../src/Repos/UsersRepo.php';

use App\Response;
use App\Session;
use Auth\AuthService;
use I18n\I18n;
use Instances\InstanceContext;
use Repos\UsersRepo;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('method_not_allowed', I18n::translate('auth.error.method_not_allowed'), 405);
}

Session::requireCsrf();
$user = AuthService::requireAuth();
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$requested = trim((string)($input['locale'] ?? ''));
$locale = $requested === '' ? null : $requested;

if ($locale !== null && !in_array($locale, InstanceContext::current()->allowedLocales(), true)) {
    Response::error('invalid_locale', I18n::translate('account.api.invalid_locale'), 400);
}

(new UsersRepo())->updateLocale((int)$user['id'], $locale);
$_SESSION['user']['locale'] = $locale;

Response::json(['success' => true, 'locale' => $locale]);
