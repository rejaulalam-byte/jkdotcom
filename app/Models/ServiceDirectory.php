<?php

declare(strict_types=1);

namespace App\Models;

/**
 * "সেবা সমূহ" পেইজের জন্য সেবা সাজায়: নামের প্রথম অক্ষরের নিচে জড়ো করে (বাংলা বর্ণক্রমে,
 * আগে স্বরবর্ণ, তারপর ব্যঞ্জনবর্ণ) এবং প্রায় সমান উচ্চতার কলামে ভাগ করে।
 * একটা বর্ণের দল কখনও দুই কলামে ভাঙে না।
 */
final class ServiceDirectory
{
    /** ক্রমানুসারে শিরোনাম-বর্ণ। অ্যাডমিন পরে এই তালিকা বদলাতে পারবেন (সেটিং `services.letters`)। */
    public const LETTERS = [
        'অ', 'আ', 'ই', 'ঈ', 'উ', 'ঊ', 'ঋ', 'এ', 'ঐ', 'ও', 'ঔ',
        'ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ',
        'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ',
    ];

    /** একটা দলের শিরোনাম + ফাঁক প্রায় এতগুলো সেবা-সারির উচ্চতার সমান ধরা হয়। */
    private const HEADING_WEIGHT = 1.8;

    /**
     * @param list<array{name:string,slug:string,icon:?string}> $services
     * @return list<array{letter:string,items:list<array{name:string,slug:string,icon:?string}>}>
     */
    public static function group(array $services): array
    {
        $order = array_flip(self::LETTERS);
        $buckets = [];
        foreach ($services as $service) {
            $letter = mb_substr(trim($service['name']), 0, 1);
            if (!isset($order[$letter])) {
                $letter = '#'; // unknown first character: listed last
            }
            $buckets[$letter][] = $service;
        }

        $groups = [];
        foreach (array_merge(self::LETTERS, ['#']) as $letter) {
            if (empty($buckets[$letter])) {
                continue;
            }
            $items = $buckets[$letter];
            usort($items, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
            $groups[] = ['letter' => $letter === '#' ? 'অন্যান্য' : $letter, 'items' => $items];
        }

        return $groups;
    }

    /**
     * দলগুলোকে ক্রম ঠিক রেখে ৩ কলামে ভাগ করে, যাতে সবচেয়ে উঁচু কলামটা যতটা সম্ভব
     * ছোট হয় (কলামগুলো প্রায় সমান লম্বা হয়)।
     *
     * @param list<array{letter:string,items:list<mixed>}> $groups
     * @return list<list<array{letter:string,items:list<mixed>}>>
     */
    public static function columns(array $groups): array
    {
        $n = count($groups);
        if ($n === 0) {
            return [[], [], []];
        }

        $prefix = [0.0];
        foreach ($groups as $g) {
            $prefix[] = end($prefix) + count($g['items']) + self::HEADING_WEIGHT;
        }

        // কাটার অবস্থান a <= b (0..n): কলাম ১ = [0,a), কলাম ২ = [a,b), কলাম ৩ = [b,n)
        $best = [0, 0];
        $bestScore = [INF, INF];
        for ($a = 0; $a <= $n; $a++) {
            for ($b = $a; $b <= $n; $b++) {
                $heights = [$prefix[$a], $prefix[$b] - $prefix[$a], $prefix[$n] - $prefix[$b]];
                $score = [max($heights), array_sum(array_map(static fn (float $h): float => $h * $h, $heights))];
                if ($score < $bestScore) {
                    $bestScore = $score;
                    $best = [$a, $b];
                }
            }
        }

        return [
            array_slice($groups, 0, $best[0]),
            array_slice($groups, $best[0], $best[1] - $best[0]),
            array_slice($groups, $best[1]),
        ];
    }

    /**
     * যে সেবার নামে খোঁজার লেখার প্রতিটি শব্দ আছে।
     *
     * @param list<array{name:string,slug:string,icon:?string}> $services
     * @return list<array{name:string,slug:string,icon:?string}>
     */
    public static function filter(array $services, string $query): array
    {
        $words = preg_split('/\s+/u', mb_strtolower(trim($query)), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($words === []) {
            return $services;
        }

        return array_values(array_filter($services, static function (array $s) use ($words): bool {
            $name = mb_strtolower($s['name']);
            foreach ($words as $w) {
                if (!str_contains($name, $w)) {
                    return false;
                }
            }

            return true;
        }));
    }
}
