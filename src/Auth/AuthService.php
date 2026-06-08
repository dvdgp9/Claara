<?php
namespace Auth;

use App\Response;
use App\Session;
use Repos\UsersRepo;

class AuthService {
    public static function login(string $email, string $password): array {
        // Cargar dependencias sin autoloader
        require_once __DIR__ . '/../Repos/UsersRepo.php';
        require_once __DIR__ . '/Passwords.php';

        $repo = new UsersRepo();
        $row = $repo->findByEmail($email);
        if (!$row) {
            Response::error('invalid_credentials', 'Invalid credentials', 401);
        }
        if (($row['status'] ?? 'active') !== 'active') {
            Response::error('user_locked', 'User is disabled', 423);
        }
        if (!Passwords::verify($password, $row['password_hash'])) {
            Response::error('invalid_credentials', 'Invalid credentials', 401);
        }
        // OK: actualizar último acceso y preparar payload de sesión
        $repo->updateLastLoginAt((int)$row['id']);
        $roles = $repo->getRoles((int)$row['id']);
        if ($row['is_superadmin']) {
            $roles = array_values(array_unique(array_merge(['admin'], $roles)));
        }
        $user = [
            'id' => (int)$row['id'],
            'email' => $row['email'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'job_title' => $row['job_title'] ?? null,
            'department_id' => $row['department_id'] ? (int)$row['department_id'] : null,
            'department_name' => $row['department_name'] ?? null,
            'is_superadmin' => (bool)$row['is_superadmin'],
            'roles' => $roles,
        ];
        Session::login($user);
        return $user;
    }

    public static function requireAuth(): array {
        $user = Session::user();
        if (!$user) {
            Response::error('unauthorized', 'Not authenticated', 401);
        }
        return $user;
    }
}
