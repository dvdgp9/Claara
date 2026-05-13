<?php
// Simple migration runner: applies SQL files in docs/migrations in lexical order

require_once __DIR__ . '/../src/App/bootstrap.php';

use App\DB;
use App\Response;

$pdo = DB::pdo();
$pdo->exec("SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci");

$root = dirname(__DIR__);
$migrationsDir = $root . '/docs/migrations';
if (!is_dir($migrationsDir)) {
    fwrite(STDERR, "No migrations dir found: $migrationsDir\n");
    exit(1);
}

$files = glob($migrationsDir . '/*.sql');
sort($files, SORT_STRING);

// Ensure schema_migrations table exists (also created in 001)
$pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  filename VARCHAR(255) NOT NULL,
  executed_at DATETIME NOT NULL,
  UNIQUE KEY schema_migrations_filename_uq (filename)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

$stmt = $pdo->query('SELECT filename FROM schema_migrations');
$done = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
$doneSet = array_flip($done);

foreach ($files as $file) {
    $base = basename($file);
    if (isset($doneSet[$base])) {
        echo "= Skipping already applied: $base\n";
        continue;
    }
    $sql = file_get_contents($file);
    echo "+ Applying: $base\n";
    try {
        $pdo->exec($sql);
        $ins = $pdo->prepare('INSERT INTO schema_migrations (filename, executed_at) VALUES (?, NOW())');
        $ins->execute([$base]);
        $doneSet[$base] = true;
        echo "✔ Done: $base\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "! Failed on $base: " . $e->getMessage() . "\n");
        exit(1);
    }
}

echo "All migrations applied.\n";
