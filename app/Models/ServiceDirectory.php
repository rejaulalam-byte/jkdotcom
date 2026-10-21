<?php

declare(strict_types=1);

namespace App\Models;

/**
 * Arranges services for the "সেবা সমূহ" page: grouped under the first letter of
 * their name (Bangla alphabet order, vowels first, then consonants) and split into
 * columns of about the same height. A letter group is never split across columns.
 */
final class ServiceDirectory
{
    /** Heading letters in order. The admin will be able to edit this list later (setting `services.letters`). */
    public const LETTERS = [
        'অ', 'আ', 'ই', 'ঈ', 'উ', 'ঊ', 'ঋ', 'এ', 'ঐ', 'ও', 'ঔ',
        'ক', 'খ', 'গ', 'ঘ', 'ঙ', 'চ', 'ছ', 'জ', 'ঝ', 'ঞ', 'ট', 'ঠ', 'ড', 'ঢ', 'ণ',
        'ত', 'থ', 'দ', 'ধ', 'ন', 'প', 'ফ', 'ব', 'ভ', 'ম', 'য', 'র', 'ল', 'শ', 'ষ', 'স', 'হ',
    ];

    /** A group's heading + gap count about this many service rows of height. */
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
     * Splits the groups, in order, into 3 columns so the tallest column is as
     * short as possible (the columns end up about equally long).
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

        // cut positions a <= b (0..n): column 1 = [0,a), column 2 = [a,b), column 3 = [b,n)
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
     * Services whose name contains every word of the search text.
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
