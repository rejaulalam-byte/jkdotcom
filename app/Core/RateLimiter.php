<?php

declare(strict_types=1);

namespace App\Core;

/**
 * দর্শক-প্রতি সহজ সীমা (যেমন ঘণ্টায় সর্বোচ্চ ৫টি মতামত): স্প্যাম ও জোর-খাটানো চেষ্টা ঠেকায়।
 * tooMany() প্রতিটি চেষ্টা গোনে; শুধু ব্যর্থ চেষ্টা গুনতে হলে আগে blocked() দিয়ে দেখে ব্যর্থ হলে hit() ডাকুন
 * (যেমন লগইনে ভুল পাসওয়ার্ড: সঠিক লগইন কখনও সীমায় পড়ে না)।
 * ফাইলে বালতির নাম ও সময়গুলো রাখা হয়, যাতে অ্যাডমিন প্যানেল আটকে-থাকা লগইন খুঁজে খুলে দিতে পারে (ফাইলের নাম হ্যাশ)।
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

    /** মেয়াদের-ভেতরে এই বালতিতে এখন কতটি চেষ্টা গোনা আছে। */
    public static function count(string $bucket, int $windowSeconds): int
    {
        return count(self::hits($bucket, $windowSeconds)[1]);
    }

    /** এই দর্শকের এই বালতির হিসাব মুছে দেয় (যেমন সঠিক লগইনের পর ব্যর্থ-চেষ্টার গোনা শূন্য)। */
    public static function clear(string $bucket): void
    {
        [$file] = self::hits($bucket, 1);
        if (is_file($file)) {
            @unlink($file);
        }
    }

    /** একটা চেষ্টা গুনে রাখে। */
    public static function hit(string $bucket, int $windowSeconds): void
    {
        [$file, $hits] = self::hits($bucket, $windowSeconds);
        $hits[] = time();
        @file_put_contents($file, json_encode(['bucket' => $bucket, 'hits' => $hits]), LOCK_EX);
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
                // নতুন ধাঁচ: ['bucket' => ..., 'hits' => [...]]; পুরনো ধাঁচ: শুধু সময়ের তালিকা
                $stored = isset($stored['hits']) && is_array($stored['hits']) ? $stored['hits'] : $stored;
                $hits = array_values(array_filter(
                    $stored,
                    static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds
                ));
            }
        }

        return [$file, $hits];
    }

    /**
     * নামের শুরু (বা পুরো নাম) মিলে এমন বালতিগুলোর এখন-চলতি চেষ্টা, সব দর্শক-ডিভাইস মিলিয়ে; অ্যাডমিনের "আটকানো লগইন" পেইজের জন্য।
     * শুধু নতুন ধাঁচের ফাইল চেনা যায় (পুরনো ধাঁচের ফাইল মেয়াদ শেষে নিজেই চলে যায়)।
     *
     * @return list<array{bucket:string,file:string,hits:list<int>}> প্রতিটি ফাইল (বালতি + ডিভাইস) আলাদা সারি
     */
    public static function find(string $prefix, int $windowSeconds): array
    {
        $out = [];
        $now = time();
        foreach (glob(BASE_PATH . '/storage/ratelimit/*.json') ?: [] as $file) {
            $stored = json_decode((string) @file_get_contents($file), true);
            if (!is_array($stored) || !isset($stored['bucket'], $stored['hits']) || !is_string($stored['bucket']) || !str_starts_with($stored['bucket'], $prefix)) {
                continue;
            }
            $hits = array_values(array_filter((array) $stored['hits'], static fn ($t): bool => is_int($t) && $t > $now - $windowSeconds));
            if ($hits !== []) {
                sort($hits);
                $out[] = ['bucket' => $stored['bucket'], 'file' => basename($file, '.json'), 'hits' => $hits];
            }
        }

        return $out;
    }

    /** একটা বালতির হিসাব সব ডিভাইস থেকে মুছে দেয় (অ্যাডমিনের "আটকানো খুলুন")। কতগুলো ফাইল মুছল সেটা ফেরত দেয়। */
    public static function clearAll(string $bucket): int
    {
        $removed = 0;
        foreach (glob(BASE_PATH . '/storage/ratelimit/*.json') ?: [] as $file) {
            $stored = json_decode((string) @file_get_contents($file), true);
            if (is_array($stored) && ($stored['bucket'] ?? null) === $bucket && @unlink($file)) {
                $removed++;
            }
        }

        return $removed;
    }

    /** একটা নির্দিষ্ট ফাইলের (ডিভাইসের) হিসাব মোছে; $name ৬৪-অক্ষরের হ্যাশ হতে হবে। সফল হলে true। */
    public static function clearFile(string $name): bool
    {
        if (preg_match('/^[0-9a-f]{64}$/', $name) !== 1) {
            return false;
        }
        $file = BASE_PATH . '/storage/ratelimit/' . $name . '.json';

        return is_file($file) && @unlink($file);
    }
}
