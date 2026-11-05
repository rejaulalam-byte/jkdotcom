<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * রিপোর্ট "একজন পেশাজীবির একাধিক স্থান ও সেবার নামের তালিকা": প্রতি পেশাজীবীর একটাই সারি; বিভাগ, জেলা, উপজেলা/থানা ও সেবার
 * নামগুলো ঘরের ভেতরে নিচে নিচে (প্রতি লাইনে একটা, শেষে কমা)। স্থান = নিজের ঠিকানার জেলা/উপজেলা + "কাজ করতে আগ্রহী" জেলাগুলো
 * ("সব জেলা" হলে লেখা থাকে "সব জেলা"); বিভাগ জেলা থেকে আসে। সেবা = প্রধান পেশা (আগে, "(প্রধান)" চিহ্নসহ) + "আরও পেশা"।
 * শুধু যাঁর একাধিক জেলা বা একাধিক সেবা আছে তিনি আসেন; অনুমোদিত পেশাজীবী। একদম নিচে যোগফল-সারি।
 * ফল ReportData-র ছাঁচে: ['head', 'rows' => [['cells','type'],...], 'summary'] — type = row | sum; ঘরের ভেতরের লাইন-ভাঙা "\n"।
 */
final class MultiPlaceReport
{
    /** সর্বোচ্চ কতজনের সারি দেখানো হয় (বেশি হলে কেটে জানানো হয়) */
    public const MAX_PEOPLE = 400;

    private const HEAD = ['ক্রমিক নং', 'পেশাজীবির নাম', 'মোবাইল নাম্বার', 'বিভাগ (নাম)', 'জেলা (নাম)', 'উপজেলা/থানা (নাম)', 'এলাকার নাম', 'সেবার নাম (প্রধান আগে)'];

    /** @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} */
    public static function build(): array
    {
        $empty = ['head' => self::HEAD, 'rows' => [['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'], self::sumRow([], 0)], 'summary' => []];
        $pdo = Database::connection();
        if ($pdo === null) {
            $empty['summary'][] = 'ডাটাবেস সংযোগ নেই, তাই এই রিপোর্ট এখানে দেখানো যাচ্ছে না।';

            return $empty;
        }

        try {
            return self::make($pdo, $empty);
        } catch (Throwable $e) {
            Logger::error('MultiPlaceReport failed: ' . $e->getMessage());
            $empty['summary'][] = 'রিপোর্ট তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';

            return $empty;
        }
    }

    /** নামের তালিকা ঘরের ভেতরে নিচে নিচে: শেষটা ছাড়া প্রতিটির শেষে কমা। @param list<string> $names */
    private static function lines(array $names): string
    {
        $last = count($names) - 1;

        return implode("\n", array_map(static fn (string $n, int $i): string => $i < $last ? $n . ',' : $n, $names, array_keys($names)));
    }

    /**
     * @param array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $empty
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    private static function make(\PDO $pdo, array $empty): array
    {
        $services = ManpowerReport::serviceNames();
        $order = array_flip(array_keys($services));
        $geo = GeoRepository::all();
        $divisionName = array_column($geo['divisions'], 'name', 'id');
        $districtName = array_column($geo['districts'], 'name', 'id');
        $districtDivision = array_column($geo['districts'], 'parent', 'id');
        $upazilaName = array_column($geo['upazilas'], 'short', 'id');

        $users = $pdo->query(
            "SELECT id, name, phone, area_name, service_slug, service_custom, division_id, district_id, upazila_id, work_all_districts
               FROM users WHERE role = 'worker' AND status = 'approved'"
        )->fetchAll();
        $work = [];
        foreach ($pdo->query('SELECT user_id, district_id FROM worker_work_districts')->fetchAll() as $r) {
            $work[(int) $r['user_id']][] = (int) $r['district_id'];
        }
        $extra = [];
        foreach ($pdo->query('SELECT user_id, service_slug FROM worker_services')->fetchAll() as $r) {
            $extra[(int) $r['user_id']][] = (string) $r['service_slug'];
        }

        $people = [];
        foreach ($users as $u) {
            $id = (int) $u['id'];
            $all = (int) $u['work_all_districts'] === 1;
            $districts = $all ? array_keys($districtName) : array_values(array_unique(array_filter(array_merge([(int) $u['district_id']], $work[$id] ?? []))));
            $divisions = array_values(array_unique(array_filter(array_merge([(int) $u['division_id']], array_map(static fn (int $d): int => (int) ($districtDivision[$d] ?? 0), $districts)))));

            $names = [];
            $main = (string) ($u['service_slug'] ?? '') !== '' ? ($services[(string) $u['service_slug']] ?? (string) $u['service_slug']) : trim((string) ($u['service_custom'] ?? ''));
            if ($main !== '') {
                $names[] = $main . ' (প্রধান)';
            }
            $others = $extra[$id] ?? [];
            usort($others, static fn (string $a, string $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            foreach ($others as $slug) {
                $names[] = $services[$slug] ?? $slug;
            }
            if (count($districts) < 2 && count($names) < 2) {
                continue;
            }

            $upazila = (int) $u['upazila_id'];
            $people[] = [
                'name' => (string) $u['name'], 'phone' => (string) ($u['phone'] ?? ''), 'all' => $all,
                'divisions' => $divisions, 'districts' => $districts, 'upazila' => $upazila,
                'divisionNames' => $all ? ['সব বিভাগ'] : array_map(static fn (int $d): string => $divisionName[$d] ?? '—', $divisions),
                'districtNames' => $all ? ['সব জেলা'] : array_map(static fn (int $d): string => $districtName[$d] ?? '—', $districts),
                'upazilaNames' => [$upazila > 0 ? ($upazilaName[$upazila] ?? '—') : '—'],
                'areaName' => (string) ($u['area_name'] ?? '') !== '' ? (string) $u['area_name'] : '—',
                'services' => $names,
            ];
        }

        // বেশি সেবা, তারপর বেশি জেলা, তারপর নাম
        usort($people, static fn (array $a, array $b): int => [count($b['services']), count($b['districts']), $a['name']] <=> [count($a['services']), count($a['districts']), $b['name']]);
        $summary = [];
        if (count($people) > self::MAX_PEOPLE) {
            $summary[] = 'মোট ' . bn(count($people)) . ' জন, তাই প্রথম ' . bn(self::MAX_PEOPLE) . ' জন দেখানো হলো।';
            $people = array_slice($people, 0, self::MAX_PEOPLE);
        }
        $summary[] = 'শুধু অনুমোদিত পেশাজীবীদের মধ্যে যাঁর একাধিক জেলা (নিজের জেলা + কাজ করতে আগ্রহী জেলা) অথবা একাধিক সেবা আছে তাঁরাই এখানে আসেন। উপজেলা/থানা বলতে নিজের ঠিকানার উপজেলা। সেবার নামে প্রধান পেশা আগে, তারপর "আরও পেশা"; ঘরের ভেতরে নামগুলো নিচে নিচে, প্রতিটির শেষে কমা।';

        $rows = [];
        foreach ($people as $i => $p) {
            $rows[] = ['cells' => [
                bn($i + 1), $p['name'], $p['phone'] !== '' ? format_phone($p['phone']) : '—',
                self::lines($p['divisionNames']), self::lines($p['districtNames']), self::lines($p['upazilaNames']), $p['areaName'], self::lines($p['services']),
            ], 'type' => 'row'];
        }
        if ($rows === []) {
            $rows[] = ['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'];
        }
        $rows[] = self::sumRow($people, count($people));

        return ['head' => self::HEAD, 'rows' => $rows, 'summary' => $summary];
    }

    /**
     * একদম নিচের যোগফল-সারি: cells = [লেখা, পেশাজীবীর সংখ্যা]; লেখায় তালিকার সব মিলিয়ে আলাদা কতটি বিভাগ, জেলা, উপজেলা/থানা, সেবা।
     *
     * @param list<array<string,mixed>> $people
     */
    private static function sumRow(array $people, int $count): array
    {
        $distinct = static fn (callable $pick): int => count(array_unique(array_merge([], ...array_map($pick, $people))));
        $divisions = $distinct(static fn (array $p): array => $p['divisions']);
        $districts = $distinct(static fn (array $p): array => $p['districts']);
        $upazilas = $distinct(static fn (array $p): array => $p['upazila'] > 0 ? [$p['upazila']] : []);
        $services = $distinct(static fn (array $p): array => array_map(static fn (string $s): string => preg_replace('/ \(প্রধান\)$/u', '', $s), $p['services']));

        return ['cells' => [
            'যোগফল — ' . bn($count) . ' জন পেশাজীবী; আলাদা ' . bn($divisions) . 'টি বিভাগ, ' . bn($districts) . 'টি জেলা, ' . bn($upazilas) . 'টি উপজেলা/থানা, ' . bn($services) . 'টি সেবা',
            bn($count),
        ], 'type' => 'sum'];
    }
}
