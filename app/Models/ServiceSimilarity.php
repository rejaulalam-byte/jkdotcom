<?php

declare(strict_types=1);

namespace App\Models;

/**
 * নতুন সেবার নাম আগের কোনো সেবার সঙ্গে মিলে যাচ্ছে কি না দেখে, যাতে একই সেবা দুবার না বসে:
 *  - same: ফাঁক, চিহ্ন, বড়-ছোট হাত ও "করা / করার লোক / এর লোক / সেবা" জাতীয় শেষাংশ বাদ দিলে নামটা হুবহু এক (আটকে দেওয়া হয়);
 *  - near: একটা নাম অন্যটার ভেতরে আছে, অথবা বানান ৭৫%-এর বেশি মেলে (সতর্ক করা হয়, অ্যাডমিন চাইলে তবুও যোগ করতে পারেন)।
 */
final class ServiceSimilarity
{
    /** নামের শেষের এই অংশগুলো তুলনায় ধরা হয় না (লম্বা আগে)। */
    private const ENDINGS = ['করার লোক', 'এর লোক', 'মিস্ত্রি', 'করা', 'করার', 'লোক', 'সেবা'];

    /** নাম সাদামাটা করা: চিহ্ন-ফাঁক-অদৃশ্য অক্ষর বাদ, ছোট হাত, শেষের সাধারণ শব্দ বাদ। */
    public static function normalize(string $name): string
    {
        // দীর্ঘ-হ্রস্ব (ি/ী, ু/ূ) এক ধরা, যেমন মিস্ত্রি/মিস্ত্রী
        $name = strtr(mb_strtolower(trim($name)), ['ী' => 'ি', 'ূ' => 'ু']);
        // শেষাংশ আগে (ফাঁকসহ থাকতেই) কাটা, নইলে "ঠিক করার লোক" আর "ঠিক করা" আলাদা থাকত
        do {
            $before = $name;
            foreach (self::ENDINGS as $ending) {
                if (str_ends_with($name, ' ' . $ending)) {
                    $name = rtrim(mb_substr($name, 0, -(mb_strlen($ending) + 1)));
                }
            }
        } while ($name !== $before);

        // অক্ষরের সঙ্গে কার-চিহ্ন ও হসন্ত রাখা (\p{M}), নইলে বাংলা নামের অর্থই হারায়
        return (string) preg_replace('/[^\p{L}\p{M}\p{N}]+/u', '', $name);
    }

    /**
     * @param list<array{id:int,name:string}> $services আগের সেবা
     * @return array{same:list<string>,near:list<string>} মিলে-যাওয়া সেবার নাম
     */
    public static function find(string $name, array $services, ?int $exceptId = null): array
    {
        $mine = self::normalize($name);
        $out = ['same' => [], 'near' => []];
        if (mb_strlen($mine) < 2) {
            return $out;
        }
        foreach ($services as $s) {
            if ($exceptId !== null && (int) $s['id'] === $exceptId) {
                continue;
            }
            $other = self::normalize($s['name']);
            if ($other === '') {
                continue;
            }
            if ($other === $mine) {
                $out['same'][] = $s['name'];
            } elseif (self::near($mine, $other)) {
                $out['near'][] = $s['name'];
            }
        }

        return $out;
    }

    private static function near(string $a, string $b): bool
    {
        $short = min(mb_strlen($a), mb_strlen($b));
        if ($short >= 4 && (str_contains($a, $b) || str_contains($b, $a))) {
            return true;
        }

        return $short >= 4 && self::ratio($a, $b) >= 0.75;
    }

    /** দুই নামের বানান কতটা মেলে (০ থেকে ১), অক্ষর ধরে, বাইট ধরে নয় (বাংলা অক্ষর ৩ বাইট, তাই সরাসরি levenshtein ভুল দিত)। */
    private static function ratio(string $a, string $b): float
    {
        $map = [];
        $encode = static function (string $s) use (&$map): string {
            $out = '';
            foreach (mb_str_split($s) as $ch) {
                $map[$ch] ??= chr(1 + count($map) % 250);
                $out .= $map[$ch];
            }

            return $out;
        };
        $x = $encode($a);
        $y = $encode($b);
        $longest = max(strlen($x), strlen($y));

        return $longest === 0 ? 1.0 : 1 - levenshtein($x, $y) / $longest;
    }
}
