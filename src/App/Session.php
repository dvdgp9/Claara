<?php
namespace App;

class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;

        // Evitar cache para respuestas con sesión
        session_cache_limiter('nocache');

        // Configurar duración de sesión en servidor (30 días máximo)
        // Esto evita que el garbage collector borre sesiones activas prematuramente
        ini_set('session.gc_maxlifetime', 30 * 86400); // 30 días
        ini_set('session.cookie_lifetime', 0); // Por defecto, expira al cerrar navegador (se modifica si remember=true)

        $sessionPath = Storage::path('sessions');
        if (!is_dir($sessionPath) && !mkdir($sessionPath, 0770, true) && !is_dir($sessionPath)) {
            throw new \RuntimeException('Could not initialize instance session storage');
        }
        ini_set('session.save_path', $sessionPath);

        CookieScope::clearLegacyCookies();
        session_set_cookie_params(CookieScope::sessionOptions(0));
        session_name(CookieScope::sessionName());
        session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Si no hay usuario en sesión pero hay cookie de remember, intentar restaurar
        if (empty($_SESSION['user']) && !empty($_COOKIE[CookieScope::rememberName()])) {
            self::tryRestoreFromRemember();
        }
    }
    
    /**
     * Intentar restaurar la sesión desde un token de "Recordarme".
     */
    private static function tryRestoreFromRemember(): void {
        // Cargar RememberService solo cuando sea necesario
        require_once __DIR__ . '/../Auth/RememberService.php';
        
        $user = \Auth\RememberService::validateAndRestore();
        if ($user) {
            $_SESSION['user'] = $user;
        }
    }

    public static function requireCsrf(): void {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$token || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
            Response::error('csrf_invalid', \I18n\I18n::translate('auth.error.csrf_invalid'), 403);
        }
    }

    public static function user(): ?array {
        return $_SESSION['user'] ?? null;
    }

    public static function login(array $user): void {
        // Regenerar session ID para prevenir session fixation
        session_regenerate_id(true);
        $_SESSION['user'] = $user;
    }

    // Persistir la cookie de sesión durante N días (Recordarme)
    public static function rememberDays(int $days): void {
        if (session_status() !== PHP_SESSION_ACTIVE) return;
        $seconds = max(1, $days) * 86400;
        $lifetime = $seconds;

        // Guardar datos de sesión
        $sessionData = $_SESSION;
        
        // Destruir sesión actual
        session_destroy();
        
        // Reconfigurar parámetros de cookie CON lifetime
        session_set_cookie_params(CookieScope::sessionOptions($lifetime));
        
        // Necesitamos setear el nombre antes de iniciar
        session_name(CookieScope::sessionName());
        
        // Reiniciar sesión
        session_start();
        
        // Regenerar ID para forzar envío de la cookie con los nuevos parámetros
        // Esto es CRÍTICO: sin esto, el navegador mantiene la cookie antigua con lifetime=0
        session_regenerate_id(true);
        
        // Restaurar datos de sesión
        $_SESSION = $sessionData;
    }

    public static function logout(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', CookieScope::expiredOptions());
        }
        session_destroy();
    }
}
