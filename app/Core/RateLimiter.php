<?php

declare(strict_types=1);

namespace App\Core;

/** Simple per-visitor limit (e.g. max 5 feedback messages per hour) to stop spam and brute force. */
final class RateLimiter
{
    /** Returns true when the visitor already used up the allowed attempts (and records this attempt otherwise). */
    public static function tooMany(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        $dir = BASE_PATH . '/storage/ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = $dir . '/' . hash('sha256', $bucket . '|' . Security::ipHash()) . '.json';
        $now = time();
        $hits = [];

        if (is_file($file)) {
            $stored = json_decode((string) file_get_contents($file), true);
            if (is_array($stored)) {
                $hits = array_values(array_filter(
                    $stored,
                    static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds
                ));
            }
        }

        if (count($hits) >= $maxAttempts) {
            return true;
        }

        $hits[] = $now;
        @file_put_contents($file, json_encode($hits), LOCK_EX);

        return false;
    }
}
