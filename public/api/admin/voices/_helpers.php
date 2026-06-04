<?php
use App\Response;
use Auth\AuthService;
use Auth\VoiceEditorGuard;

require_once __DIR__ . '/../../../../src/App/bootstrap.php';
require_once __DIR__ . '/../../../../src/Auth/AuthService.php';
require_once __DIR__ . '/../../../../src/Auth/VoiceEditorGuard.php';
require_once __DIR__ . '/../../../../src/Repos/VoicesRepo.php';

function require_voice_editor(): array
{
    $user = AuthService::requireAuth();
    VoiceEditorGuard::requireCanEdit($user);
    return $user;
}

function read_json_body(): array
{
    $input = json_decode(file_get_contents('php://input'), true);
    return is_array($input) ? $input : [];
}

function clean_text(array $input, string $key, int $maxLength, bool $required = false): ?string
{
    $value = trim((string)($input[$key] ?? ''));
    if ($required && $value === '') {
        Response::error('validation_error', "{$key} requerido", 400);
    }
    if ($value !== '' && strlen($value) > $maxLength) {
        Response::error('validation_error', "{$key} demasiado largo", 400);
    }
    return $value === '' ? null : $value;
}

function clean_voice_slug(array $input, string $key = 'slug'): string
{
    $slug = strtolower(trim((string)($input[$key] ?? '')));
    if ($slug === '') {
        Response::error('validation_error', "{$key} requerido", 400);
    }
    if (strlen($slug) > 50 || !preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $slug)) {
        Response::error('validation_error', 'slug inválido: usa minúsculas, números y guiones', 400);
    }
    return $slug;
}

function clean_voice_payload(array $input, bool $creating): array
{
    if (!$creating && array_key_exists('name', $input) && trim((string)$input['name']) === '') {
        Response::error('validation_error', 'name requerido', 400);
    }

    $name = clean_text($input, 'name', 100, $creating);
    $role = clean_text($input, 'role', 120, false);
    $description = clean_text($input, 'description', 255, false);
    $instructions = clean_text($input, 'instructions', 4000, false);
    $triggerGuidance = clean_text($input, 'trigger_guidance', 1000, false);
    $icon = clean_text($input, 'icon', 50, false);
    $color = clean_text($input, 'color', 40, false);
    $model = clean_text($input, 'model', 120, false);
    $provider = clean_text($input, 'provider', 60, false);

    $payload = [];
    foreach ([
        'name' => $name,
        'role' => $role,
        'description' => $description,
        'instructions' => $instructions,
        'trigger_guidance' => $triggerGuidance,
        'icon' => $icon,
        'color' => $color,
        'model' => $model,
        'provider' => $provider,
    ] as $key => $value) {
        if ($value !== null || array_key_exists($key, $input)) {
            $payload[$key] = $value;
        }
    }
    if (array_key_exists('instructions', $input) && !array_key_exists('system_prompt', $payload)) {
        $payload['system_prompt'] = $instructions;
    }

    return $payload;
}

function respond_voice_not_found(): void
{
    Response::error('voice_not_found', 'Voz no encontrada', 404);
}
