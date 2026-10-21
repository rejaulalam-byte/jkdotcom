<?php

declare(strict_types=1);

namespace App\Core;

/** গোপন .env ফাইল থেকে সেটিং (ডাটাবেস পাসওয়ার্ড, মেইল ঠিকানা ইত্যাদি) পড়ে। */
final class Env
{
    /** @var array<string,string> */
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $value = trim($value);
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
                $value = substr($value, 1, -1);
            }
            self::$vars[trim($key)] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        if (isset(self::$vars[$key]) && self::$vars[$key] !== '') {
            return self::$vars[$key];
        }

        return $default;
    }
}
