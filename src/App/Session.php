<?php
namespace App;

class Session {
    public static function start(): void {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        // Detectar HTTPS correctamente detrás de proxy/CDN
        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $schemeFromEnv = '';
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            $schemeFromEnv = $parsed['scheme'] ?? '';
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($xfp) === 'https'
            || strtolower($schemeFromEnv) === 'https';

        // Determinar dominio (opcional) para abarcar subdominios en prod
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!$host && $appUrl) {
            $parsed = parse_url($appUrl);
            $host = $parsed['host'] ?? '';
        }
        // Solo usar dominio si no es localhost/IP (para que funcione en desarrollo)
        $cookieDomain = '';
        if ($host && !preg_match('/^(localhost|127\.|192\.|10\.|172\.)/', $host)) {
            // Extraer dominio base (e.g., ebonia.es) si hay subdominio
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $cookieDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            }
        }

        // Evitar cache para respuestas con sesión
        session_cache_limiter('nocache');

        // Configurar duración de sesión en servidor (30 días máximo)
        // Esto evita que el garbage collector borre sesiones activas prematuramente
        ini_set('session.gc_maxlifetime', 30 * 86400); // 30 días
        ini_set('session.cookie_lifetime', 0); // Por defecto, expira al cerrar navegador (se modifica si remember=true)

        // Fijar path global
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            // Fijar dominio base si se pudo calcular (permite www. y subdominios)
            'domain' => $cookieDomain ?: '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_name('ebonia_session');
        session_start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        // Si no hay usuario en sesión pero hay cookie de remember, intentar restaurar
        if (empty($_SESSION['user']) && !empty($_COOKIE['ebonia_remember'])) {
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
            Response::error('csrf_invalid', 'CSRF token inválido o ausente', 403);
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

        // Recalcular flags como en start()
        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $schemeFromEnv = '';
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            $schemeFromEnv = $parsed['scheme'] ?? '';
        }
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($xfp) === 'https'
            || strtolower($schemeFromEnv) === 'https';

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!$host && $appUrl) {
            $parsed = parse_url($appUrl);
            $host = $parsed['host'] ?? '';
        }
        // Solo usar dominio si no es localhost/IP
        $cookieDomain = '';
        if ($host && !preg_match('/^(localhost|127\.|192\.|10\.|172\.)/', $host)) {
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                $cookieDomain = $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            }
        }

        // Guardar datos de sesión
        $sessionData = $_SESSION;
        
        // Destruir sesión actual
        session_destroy();
        
        // Reconfigurar parámetros de cookie CON lifetime
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/',
            'domain' => $cookieDomain,
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        
        // Necesitamos setear el nombre antes de iniciar
        session_name('ebonia_session');
        
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
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? true);
        }
        session_destroy();
    }
}
