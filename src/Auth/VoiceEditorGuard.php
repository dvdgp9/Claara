<?php
namespace Auth;

use App\Response;
use Repos\UserFeatureAccessRepo;

class VoiceEditorGuard
{
    public static function requireCanEdit(array $user): void
    {
        if (!empty($user['is_superadmin'])) {
            return;
        }

        $userId = isset($user['id']) ? (int)$user['id'] : 0;
        if ($userId <= 0) {
            Response::error('forbidden', 'Acceso denegado', 403);
        }

        $accessRepo = new UserFeatureAccessRepo();
        if (!$accessRepo->hasAccess($userId, 'feature', 'voice-editor')) {
            Response::error('forbidden', 'No tienes permiso para editar voces', 403);
        }
    }
}
