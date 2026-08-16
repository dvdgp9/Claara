<?php
namespace App;

use Instances\InstanceContext;
use PDO;
use PDOException;

class DB {
    private static ?PDO $pdo = null;

    public static function pdo(): PDO {
        if (self::$pdo) return self::$pdo;
        $database = InstanceContext::current()->resources()->databaseConfig();
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $database['host'],
            $database['port'],
            $database['name']
        );
        $user = $database['user'];
        $pass = $database['password'];
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
