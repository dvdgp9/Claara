<?php
namespace Auth;

use App\DB;

/**
 * Rate limiter simple para proteger contra brute force
 * Usa tabla en BD para trackear intentos por IP
 */
class RateLimiter
{
    private int $maxAttempts;
    private int $windowSeconds;
    
    public function __construct(int $maxAttempts = 5, int $windowSeconds = 900)
    {
        $this->maxAttempts = $maxAttempts;
        $this->windowSeconds = $windowSeconds;
        $this->ensureTable();
    }
    
    /**
     * Verifica si la IP está bloqueada
     */
    public function isBlocked(string $ip): bool
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as attempts 
            FROM rate_limit_attempts 
            WHERE ip_address = ? 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $this->windowSeconds]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return ($row['attempts'] ?? 0) >= $this->maxAttempts;
    }
    
    /**
     * Registra un intento fallido
     */
    public function recordAttempt(string $ip): void
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            INSERT INTO rate_limit_attempts (ip_address, attempted_at) 
            VALUES (?, NOW())
        ");
        $stmt->execute([$ip]);
        
        // Limpiar intentos antiguos (más de 1 hora)
        $pdo->exec("DELETE FROM rate_limit_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");
    }
    
    /**
     * Limpia intentos de una IP (tras login exitoso)
     */
    public function clearAttempts(string $ip): void
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("DELETE FROM rate_limit_attempts WHERE ip_address = ?");
        $stmt->execute([$ip]);
    }
    
    /**
     * Obtiene segundos restantes de bloqueo
     */
    public function getBlockedSeconds(string $ip): int
    {
        $pdo = DB::get();
        $stmt = $pdo->prepare("
            SELECT MIN(attempted_at) as first_attempt 
            FROM rate_limit_attempts 
            WHERE ip_address = ? 
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? SECOND)
        ");
        $stmt->execute([$ip, $this->windowSeconds]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$row['first_attempt']) {
            return 0;
        }
        
        $firstAttempt = strtotime($row['first_attempt']);
        $unlockTime = $firstAttempt + $this->windowSeconds;
        $remaining = $unlockTime - time();
        
        return max(0, $remaining);
    }
    
    /**
     * Crea la tabla si no existe
     */
    private function ensureTable(): void
    {
        $pdo = DB::get();
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS rate_limit_attempts (
                id INT AUTO_INCREMENT PRIMARY KEY,
                ip_address VARCHAR(45) NOT NULL,
                attempted_at DATETIME NOT NULL,
                INDEX idx_ip_time (ip_address, attempted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
