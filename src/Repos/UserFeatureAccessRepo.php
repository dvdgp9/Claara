<?php
namespace Repos;

use App\DB;
use PDO;

class UserFeatureAccessRepo
{
    public const DEFAULT_NEW_USER_ACCESS = [
        'feature:image-generation' => true,
        'voice:lex' => true,
        'gesture:podcast-from-article' => true,
        'gesture:image-editor' => true,
        'gesture:content-repurposer' => true,
        'gesture:sop-generator' => true,
        'gesture:lead-finder' => true,
    ];

    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? DB::pdo();
    }

    /**
     * Verifica si un usuario tiene acceso a una feature específica
     * Superadmins tienen acceso a todo por defecto
     */
    public function hasAccess(int $userId, string $featureType, string $featureSlug): bool
    {
        // Primero verificar si es superadmin
        $stmt = $this->pdo->prepare('SELECT is_superadmin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && $user['is_superadmin']) {
            return true;
        }

        // Verificar acceso específico
        $stmt = $this->pdo->prepare('
            SELECT enabled 
            FROM user_feature_access 
            WHERE user_id = ? AND feature_type = ? AND feature_slug = ?
        ');
        $stmt->execute([$userId, $featureType, $featureSlug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row && $row['enabled'] == 1;
    }

    /**
     * Verifica acceso a un gesto
     */
    public function hasGestureAccess(int $userId, string $gestureSlug): bool
    {
        return $this->hasAccess($userId, 'gesture', $gestureSlug);
    }

    /**
     * Verifica acceso a una voz
     */
    public function hasVoiceAccess(int $userId, string $voiceSlug): bool
    {
        return $this->hasAccess($userId, 'voice', $voiceSlug);
    }

    /**
     * Verifica acceso a generación de imágenes
     */
    public function hasImageGenerationAccess(int $userId): bool
    {
        return $this->hasAccess($userId, 'feature', 'image-generation');
    }

    /**
     * Obtiene todas las features disponibles
     */
    public function getAvailableFeatures(): array
    {
        $stmt = $this->pdo->query('
            SELECT id, feature_type, feature_slug, name, description, icon, sort_order
            FROM available_features
            WHERE is_active = 1
            ORDER BY feature_type, sort_order
        ');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Obtiene las features disponibles agrupadas por tipo
     */
    public function getAvailableFeaturesGrouped(): array
    {
        $features = $this->getAvailableFeatures();
        $grouped = [
            'gesture' => [],
            'voice' => [],
            'feature' => []
        ];

        foreach ($features as $f) {
            $grouped[$f['feature_type']][] = $f;
        }

        return $grouped;
    }

    /**
     * Obtiene los permisos de un usuario específico
     */
    public function getUserAccess(int $userId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT feature_type, feature_slug, enabled
            FROM user_feature_access
            WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Convertir a mapa para fácil acceso
        $access = [];
        foreach ($rows as $row) {
            $key = $row['feature_type'] . ':' . $row['feature_slug'];
            $access[$key] = $row['enabled'] == 1;
        }

        return $access;
    }

    /**
     * Obtiene todos los usuarios con sus permisos para la UI de admin
     */
    public function getAllUsersWithAccess(): array
    {
        // Obtener usuarios (no superadmins, ellos tienen todo)
        $stmt = $this->pdo->query('
            SELECT id, email, first_name, last_name, is_superadmin, status
            FROM users
            WHERE status = "active"
            ORDER BY first_name, last_name
        ');
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Obtener permisos de cada usuario
        foreach ($users as &$user) {
            $user['access'] = $this->getUserAccess((int)$user['id']);
            $user['is_superadmin'] = (bool)$user['is_superadmin'];
        }

        return $users;
    }

    /**
     * Actualiza el acceso de un usuario a una feature
     */
    public function setAccess(int $userId, string $featureType, string $featureSlug, bool $enabled): bool
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO user_feature_access (user_id, feature_type, feature_slug, enabled)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE enabled = VALUES(enabled), updated_at = NOW()
        ');
        return $stmt->execute([$userId, $featureType, $featureSlug, $enabled ? 1 : 0]);
    }

    /**
     * Actualiza múltiples permisos de un usuario a la vez
     * $permissions = ['gesture:write-article' => true, 'voice:lex' => false, ...]
     */
    public function setMultipleAccess(int $userId, array $permissions): bool
    {
        $startedTransaction = !$this->pdo->inTransaction();
        if ($startedTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach ($permissions as $key => $enabled) {
                [$featureType, $featureSlug] = explode(':', $key, 2);
                $this->setAccess($userId, $featureType, $featureSlug, $enabled);
            }
            if ($startedTransaction) {
                $this->pdo->commit();
            }
            return true;
        } catch (\Throwable $e) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return false;
        }
    }

    /**
     * Aplica el paquete de permisos inicial para usuarios creados desde admin.
     */
    public function grantDefaultAccessForNewUser(int $userId): bool
    {
        return $this->setMultipleAccess($userId, self::DEFAULT_NEW_USER_ACCESS);
    }

    /**
     * Activa todas las features de un tipo para un usuario
     */
    public function enableAllOfType(int $userId, string $featureType): bool
    {
        $features = $this->getAvailableFeatures();
        $this->pdo->beginTransaction();
        try {
            foreach ($features as $f) {
                if ($f['feature_type'] === $featureType) {
                    $this->setAccess($userId, $featureType, $f['feature_slug'], true);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Desactiva todas las features de un tipo para un usuario
     */
    public function disableAllOfType(int $userId, string $featureType): bool
    {
        $features = $this->getAvailableFeatures();
        $this->pdo->beginTransaction();
        try {
            foreach ($features as $f) {
                if ($f['feature_type'] === $featureType) {
                    $this->setAccess($userId, $featureType, $f['feature_slug'], false);
                }
            }
            $this->pdo->commit();
            return true;
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            return false;
        }
    }

    /**
     * Obtiene los gestos accesibles para un usuario
     */
    public function getAccessibleGestures(int $userId): array
    {
        // Superadmin tiene todo
        $stmt = $this->pdo->prepare('SELECT is_superadmin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $allGestures = $this->pdo->query('
            SELECT feature_slug, name, description, icon
            FROM available_features
            WHERE feature_type = "gesture" AND is_active = 1
            ORDER BY sort_order
        ')->fetchAll(PDO::FETCH_ASSOC);

        if ($user && $user['is_superadmin']) {
            return $allGestures;
        }

        // Filtrar por acceso
        $stmt = $this->pdo->prepare('
            SELECT feature_slug
            FROM user_feature_access
            WHERE user_id = ? AND feature_type = "gesture" AND enabled = 1
        ');
        $stmt->execute([$userId]);
        $allowed = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'feature_slug');

        return array_filter($allGestures, fn($g) => in_array($g['feature_slug'], $allowed));
    }

    /**
     * Obtiene las voces accesibles para un usuario
     */
    public function getAccessibleVoices(int $userId): array
    {
        // Superadmin tiene todo
        $stmt = $this->pdo->prepare('SELECT is_superadmin FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $allVoices = $this->pdo->query('
            SELECT feature_slug, name, description, icon
            FROM available_features
            WHERE feature_type = "voice" AND is_active = 1
            ORDER BY sort_order
        ')->fetchAll(PDO::FETCH_ASSOC);

        if ($user && $user['is_superadmin']) {
            return $allVoices;
        }

        // Filtrar por acceso
        $stmt = $this->pdo->prepare('
            SELECT feature_slug
            FROM user_feature_access
            WHERE user_id = ? AND feature_type = "voice" AND enabled = 1
        ');
        $stmt->execute([$userId]);
        $allowed = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'feature_slug');

        return array_filter($allVoices, fn($v) => in_array($v['feature_slug'], $allowed));
    }
}
