<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * One shared MySQL connection (PDO).
 * RULE FOR EVERY DEVELOPER: all SQL must use prepared statements with bound
 * parameters — never put user input directly into an SQL string.
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $tried = false;

    /** Returns null when no database is configured (demo mode) or it is unreachable. */
    public static function connection(): ?PDO
    {
        if (self::$tried) {
            return self::$pdo;
        }
        self::$tried = true;

        $name = Env::get('DB_NAME');
        if ($name === null) {
            return null;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                Env::get('DB_HOST', 'localhost'),
                Env::get('DB_PORT', '3306'),
                $name
            );
            self::$pdo = new PDO($dsn, Env::get('DB_USER', '') ?? '', Env::get('DB_PASS', '') ?? '', [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            self::$pdo->exec("SET time_zone = '+06:00'");
        } catch (Throwable $e) {
            // Log the reason, but never show connection details to visitors
            Logger::error('Database connection failed: ' . $e->getMessage());
            self::$pdo = null;
        }

        return self::$pdo;
    }
}
