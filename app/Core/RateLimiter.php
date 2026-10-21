<?php

declare(strict_types=1);

namespace App\Core;

/** দর্শক-প্রতি সহজ সীমা (যেমন ঘণ্টায় সর্বোচ্চ ৫টি মতামত): স্প্যাম ও জোর-খাটানো চেষ্টা ঠেকায়। */
final class RateLimiter
{
    /** দর্শক অনুমোদিত চেষ্টা শেষ করে ফেললে true দেয় (নইলে এই চেষ্টাটা গুনে রাখে)। */
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
