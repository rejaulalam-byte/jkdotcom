<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * সম্পাদনাযোগ্য ওয়েবসাইটের লেখা।
 * সাইটের প্রতিটি লেখার একটা কী (key) আছে (যেমন "home.hero.title_1")।
 * ডিফল্ট লেখা app/Content/defaults.php-তে; অ্যাডমিন প্যানেলে বদলালে "content_blocks"
 * টেবিলে জমা হয় এবং সেটাই প্রাধান্য পায়।
 */
final class Content
{
    /** @var array<string,string>|null */
    private static ?array $defaults = null;
    /** @var array<string,string>|null */
    private static ?array $overrides = null;

    public static function get(string $key, string $fallback = ''): string
    {
        self::$defaults ??= require BASE_PATH . '/app/Content/defaults.php';

        return self::overrides()[$key] ?? self::$defaults[$key] ?? $fallback;
    }

    /** @return array<string,string> */
    private static function overrides(): array
    {
        if (self::$overrides !== null) {
            return self::$overrides;
        }
        self::$overrides = [];

        $pdo = Database::connection();
        if ($pdo !== null) {
            try {
                foreach ($pdo->query('SELECT content_key, content_value FROM content_blocks') as $row) {
                    self::$overrides[(string) $row['content_key']] = (string) $row['content_value'];
                }
            } catch (Throwable $e) {
                Logger::error('Content load failed: ' . $e->getMessage());
            }
        }

        return self::$overrides;
    }
}
