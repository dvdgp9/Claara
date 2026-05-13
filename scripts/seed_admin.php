<?php
require_once __DIR__ . '/../src/App/bootstrap.php';
require_once __DIR__ . '/../src/Auth/Passwords.php';

use App\DB;
use Auth\Passwords;

$pdo = DB::pdo();
$adminEmail = getenv('ADMIN_EMAIL') ?: null;
$adminPass = getenv('ADMIN_PASSWORD') ?: null;
if (!$adminEmail || !$adminPass) {
    fwrite(STDERR, "ADMIN_EMAIL/ADMIN_PASSWORD are not defined in .env\n");
    exit(1);
}

$now = date('Y-m-d H:i:s');
$hash = Passwords::hash($adminPass);

// Create user if it does not exist.
$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$adminEmail]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        echo "= Admin user already exists: $adminEmail (id=$existing)\n";
    } else {
        $ins = $pdo->prepare('INSERT INTO users (company_id, department_id, email, password_hash, first_name, last_name, is_superadmin, status, created_at, updated_at) VALUES (NULL, NULL, ?, ?, ?, ?, 1, "active", ?, ?)');
        $ins->execute([$adminEmail, $hash, 'Admin', 'iaiaPRO', $now, $now]);
        $userId = (int)$pdo->lastInsertId();
        echo "+ Admin user created: $adminEmail (id=$userId)\n";

        // Ensure admin role.
        // Get admin role_id.
        $roleId = (int)($pdo->query("SELECT id FROM roles WHERE slug='admin' LIMIT 1")->fetchColumn());
        if ($roleId) {
            $pdo->prepare('INSERT IGNORE INTO user_roles (user_id, role_id, created_at) VALUES (?,?,?)')
                ->execute([$userId, $roleId, $now]);
            echo "+ Admin role assigned\n";
        }
    }
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "! Error: " . $e->getMessage() . "\n");
    exit(1);
}

echo "Admin seed completed.\n";
