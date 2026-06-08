<?php
namespace Auth;

use App\DB;

/**
 * Servicio para manejar tokens de "Recordarme" persistentes.
 * 
 * Esto soluciona el problema de que las sesiones PHP se borren
 * antes de que expire la cookie del navegador.
 */
class RememberService
{
    private const COOKIE_NAME = 'claara_remember';
    private const TOKEN_DAYS = 30;
    
    /**
     * Crear un token de remember para el usuario y setear la cookie.
     */
    public static function createToken(int $userId): void
    {
        // Generar token seguro
        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:s', time() + self::TOKEN_DAYS * 86400);
        
        // Guardar en BD
        $db = DB::pdo();
        $stmt = $db->prepare('
            INSERT INTO remember_tokens (user_id, token_hash, expires_at, created_at)
            VALUES (?, ?, ?, NOW())
        ');
        $stmt->execute([$userId, $tokenHash, $expiresAt]);
        
        // Setear cookie
        self::setCookie($userId . ':' . $token, self::TOKEN_DAYS * 86400);
    }
    
    /**
     * Validar token de remember y restaurar sesión si es válido.
     * Retorna el usuario si es válido, null si no.
     */
    public static function validateAndRestore(): ?array
    {
        $cookieValue = $_COOKIE[self::COOKIE_NAME] ?? '';
        if (!$cookieValue) {
            return null;
        }
        
        // Parsear cookie (formato: userId:token)
        $parts = explode(':', $cookieValue, 2);
        if (count($parts) !== 2) {
            self::clearCookie();
            return null;
        }
        
        [$userId, $token] = $parts;
        $userId = (int)$userId;
        $tokenHash = hash('sha256', $token);
        
        // Buscar token válido en BD
        $db = DB::pdo();
        $stmt = $db->prepare('
            SELECT rt.id, rt.user_id, 
                   u.id as uid, u.email, u.first_name, u.last_name, u.job_title,
                   u.department_id, u.is_superadmin, u.status,
                   d.name as department_name
            FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE rt.user_id = ? 
              AND rt.token_hash = ? 
              AND rt.expires_at > NOW()
              AND u.status = "active"
            LIMIT 1
        ');
        $stmt->execute([$userId, $tokenHash]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row) {
            // Token inválido o expirado
            self::clearCookie();
            return null;
        }
        
        // Obtener roles del usuario
        require_once __DIR__ . '/../Repos/UsersRepo.php';
        $repo = new \Repos\UsersRepo();
        $roles = $repo->getRoles((int)$row['uid']);
        if ($row['is_superadmin']) {
            $roles = array_values(array_unique(array_merge(['admin'], $roles)));
        }
        
        // Token válido - restaurar sesión (mismo formato que AuthService::login)
        $user = [
            'id' => (int)$row['uid'],
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'job_title' => $row['job_title'] ?? null,
            'department_id' => $row['department_id'] ? (int)$row['department_id'] : null,
            'department_name' => $row['department_name'] ?? null,
            'is_superadmin' => (bool)$row['is_superadmin'],
            'roles' => $roles,
        ];
        
        // Rotar token por seguridad (invalidar el anterior, crear uno nuevo)
        self::deleteToken($row['id']);
        self::createToken($user['id']);
        
        return $user;
    }
    
    /**
     * Eliminar todos los tokens del usuario (al hacer logout).
     */
    public static function clearAllForUser(int $userId): void
    {
        $db = DB::pdo();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE user_id = ?');
        $stmt->execute([$userId]);
        
        self::clearCookie();
    }
    
    /**
     * Limpiar tokens expirados (ejecutar periódicamente).
     */
    public static function cleanupExpired(): int
    {
        $db = DB::pdo();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE expires_at < NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }
    
    /**
     * Eliminar un token específico.
     */
    private static function deleteToken(int $tokenId): void
    {
        $db = DB::pdo();
        $stmt = $db->prepare('DELETE FROM remember_tokens WHERE id = ?');
        $stmt->execute([$tokenId]);
    }
    
    /**
     * Setear cookie de remember.
     */
    private static function setCookie(string $value, int $lifetime): void
    {
        $secure = self::isHttps();
        $domain = self::getCookieDomain();
        
        setcookie(self::COOKIE_NAME, $value, [
            'expires' => time() + $lifetime,
            'path' => '/',
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    /**
     * Borrar cookie de remember.
     */
    private static function clearCookie(): void
    {
        $domain = self::getCookieDomain();
        
        setcookie(self::COOKIE_NAME, '', [
            'expires' => time() - 86400,
            'path' => '/',
            'domain' => $domain,
            'secure' => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    
    /**
     * Detectar si estamos en HTTPS.
     */
    private static function isHttps(): bool
    {
        $xfp = $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '';
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        $schemeFromEnv = '';
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            $schemeFromEnv = $parsed['scheme'] ?? '';
        }
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || strtolower($xfp) === 'https'
            || strtolower($schemeFromEnv) === 'https';
    }
    
    /**
     * Obtener dominio para cookie.
     */
    private static function getCookieDomain(): string
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $appUrl = $_ENV['APP_URL'] ?? getenv('APP_URL') ?: '';
        if (!$host && $appUrl) {
            $parsed = parse_url($appUrl);
            $host = $parsed['host'] ?? '';
        }
        
        if ($host && !preg_match('/^(localhost|127\.|192\.|10\.|172\.)/', $host)) {
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                return $parts[count($parts)-2] . '.' . $parts[count($parts)-1];
            }
        }
        return '';
    }
}
