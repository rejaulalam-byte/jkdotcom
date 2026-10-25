<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

/**
 * সম্পাদনাযোগ্য ওয়েবসাইটের লেখা।
 * সাইটের প্রতিটি লেখার একটা কী (key) আছে (যেমন "home.hero.title_1")।
 * ডিফল্ট লেখা app/Content/defaults.php-তে; অ্যাডমিন প্যানেলে বদলালে "content_blocks"
 * টেবিলে জমা হয় এবং সেটাই প্রাধান্য পায়। ডাটাবেস না থাকলে (লোকাল প্রিভিউ) storage/demo-content.json।
 */
final class Content
{
    private const DEMO_FILE = '/storage/demo-content.json';

    /** @var array<string,string>|null */
    private static ?array $defaults = null;
    /** @var array<string,string>|null */
    private static ?array $overrides = null;

    public static function get(string $key, string $fallback = ''): string
    {
        self::$defaults ??= require BASE_PATH . '/app/Content/defaults.php';

        return self::overrides()[$key] ?? self::$defaults[$key] ?? $fallback;
    }

    /** একটা লেখা/সুইচের মান সংরক্ষণ করে (অ্যাডমিন প্যানেল থেকে)। সফল হলে true। */
    public static function set(string $key, string $value): bool
    {
        self::$overrides = null;
        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $data = is_array($data) ? $data : [];
            $data[$key] = $value;

            return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
        }

        try {
            $pdo->prepare(
                'INSERT INTO content_blocks (content_key, content_value) VALUES (:k, :v)
                 ON DUPLICATE KEY UPDATE content_value = VALUES(content_value)'
            )->execute([':k' => $key, ':v' => $value]);

            return true;
        } catch (Throwable $e) {
            Logger::error('Content::set failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @return array<string,string> */
    private static function overrides(): array
    {
        if (self::$overrides !== null) {
            return self::$overrides;
        }
        self::$overrides = [];

        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            self::$overrides = is_array($data) ? array_map('strval', $data) : [];
        }
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
