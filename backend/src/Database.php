<?php

declare(strict_types=1);

namespace Clockwork;

use PDO;

/**
 * Lazily-created, process-shared PDO connection to MariaDB.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host = Config::get('DB_HOST', 'db');
        $port = Config::get('DB_PORT', '3306');
        $name = Config::get('DB_NAME', 'clockwork');
        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

        self::$pdo = new PDO(
            $dsn,
            Config::require('DB_USER'),
            Config::require('DB_PASSWORD'),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$pdo;
    }
}
