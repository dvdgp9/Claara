<?php
namespace App;

use PDO;
use PDOException;

class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            Env::get('DB_HOST', 'localhost'),
            Env::get('DB_PORT', '3306'),
            Env::get('DB_NAME', '')
        );
        $user = Env::get('DB_USER', 'root');
        $pass = Env::get('DB_PASS', '');
        try {
            $pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            self::$pdo = $pdo;
            return $pdo;
        } catch (PDOException $e) {
            Response::error('db_connection_failed', 'Could not connect to the database', 500);
        }
    }
}
