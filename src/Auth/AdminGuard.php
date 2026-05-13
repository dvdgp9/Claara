<?php
namespace Auth;

use App\Response;
use App\Session;

class AdminGuard {
    public static function requireSuperadmin(): array {
        $user = Session::user();
        if (!$user) {
            Response::error('unauthorized', 'Not authenticated', 401);
        }
        
        // Verificar si tiene rol admin o es superadmin
        $isSuperadmin = in_array('admin', $user['roles'] ?? [], true);
        
        if (!$isSuperadmin) {
            Response::error('forbidden', 'Acceso denegado. Solo superadministradores.', 403);
        }
        
        return $user;
    }
}
