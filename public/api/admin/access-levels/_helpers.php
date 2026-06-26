<?php
use App\Response;
use Auth\AuthService;

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';

/** Global access-level admin endpoints are superadmin-only. */
function require_superadmin(): array
{
    $user = AuthService::requireAuth();
    $isSuperadmin = !empty($user['is_superadmin'])
        || in_array('admin', $user['roles'] ?? [], true);
    if (!$isSuperadmin) {
        Response::error('forbidden', 'Superadmin only', 403);
    }
    return $user;
}

function level_read_json_body(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function level_clean_name(array $input, string $key = 'name'): string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($value === '') {
        Response::error('validation_error', "{$key} is required", 400);
    }
    if (strlen($value) > 120) {
        Response::error('validation_error', "{$key} is too long", 400);
    }
    return $value;
}

function level_slugify(string $name, callable $exists): string
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($name)) ?? '', '-');
    if ($base === '') {
        $base = 'level';
    }
    $slug = $base;
    $n = 1;
    while ($exists($slug)) {
        $n += 1;
        $slug = $base . '-' . $n;
    }
    return $slug;
}
