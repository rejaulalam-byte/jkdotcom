<?php

declare(strict_types=1);

namespace App\Core;

/**
 * দর্শক-প্রতি সহজ সীমা (যেমন ঘণ্টায় সর্বোচ্চ ৫টি মতামত): স্প্যাম ও জোর-খাটানো চেষ্টা ঠেকায়।
 * tooMany() প্রতিটি চেষ্টা গোনে; শুধু ব্যর্থ চেষ্টা গুনতে হলে আগে blocked() দিয়ে দেখে ব্যর্থ হলে hit() ডাকুন
 * (যেমন লগইনে ভুল পাসওয়ার্ড: সঠিক লগইন কখনও সীমায় পড়ে না)।
 */
final class RateLimiter
{
    /** দর্শক অনুমোদিত চেষ্টা শেষ করে ফেললে true দেয় (নইলে এই চেষ্টাটা গুনে রাখে)। */
    public static function tooMany(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        if (self::blocked($bucket, $maxAttempts, $windowSeconds)) {
            return true;
        }
        self::hit($bucket, $windowSeconds);

        return false;
    }

    /** চেষ্টা না গুনে শুধু দেখে: সীমা শেষ হয়ে গেছে কি না। */
    public static function blocked(string $bucket, int $maxAttempts, int $windowSeconds): bool
    {
        return count(self::hits($bucket, $windowSeconds)[1]) >= $maxAttempts;
    }

    /** একটা চেষ্টা গুনে রাখে। */
    public static function hit(string $bucket, int $windowSeconds): void
    {
        [$file, $hits] = self::hits($bucket, $windowSeconds);
        $hits[] = time();
        @file_put_contents($file, json_encode($hits), LOCK_EX);
    }

    /**
     * এই দর্শক ও বালতির মেয়াদের-ভেতরের চেষ্টার সময়গুলো।
     *
     * @return array{0:string,1:list<int>} [ফাইলের পাথ, সময়ের তালিকা]
     */
    private static function hits(string $bucket, int $windowSeconds): array
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

        return [$file, $hits];
    }
}
