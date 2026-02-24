<?php
class DB {
    private static $pdo = null;

    public static function conn() {
        if (self::$pdo === null) {
            $host = getenv('DB_HOST') ?: 'db';
            $name = getenv('DB_NAME') ?: 'lms';
            $user = getenv('DB_USER') ?: 'lms';
            $pass = getenv('DB_PASS') ?: 'lms';
            $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }
        return self::$pdo;
    }
}
