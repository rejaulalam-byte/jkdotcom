<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * সবার জন্য একটাই MySQL সংযোগ (PDO)।
 * প্রতিটি ডেভেলপারের জন্য নিয়ম: সব SQL-এ prepared statement ও bound parameter ব্যবহার করতে হবে —
 * ব্যবহারকারীর দেওয়া কিছু কখনও সরাসরি SQL লেখার ভেতরে বসানো যাবে না।
 */
final class Database
{
    private static ?PDO $pdo = null;
    private static bool $tried = false;

    /** ডাটাবেস সেট করা না থাকলে (ডেমো মোড) বা না-পাওয়া গেলে null দেয়। */
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
            // কারণ লগে লেখা হয়, কিন্তু সংযোগের তথ্য দর্শককে কখনও দেখানো হয় না
            Logger::error('Database connection failed: ' . $e->getMessage());
            self::$pdo = null;
        }

        return self::$pdo;
    }
}
