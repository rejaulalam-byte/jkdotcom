<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * রিপোর্ট "চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য": অনুমোদিত পেশাজীবী কতজন, কোন এলাকায়, কোন সেবায়।
 * তারিখ-নির্ভর নয়; পাঁচটি দিকের (বিভাগ / জেলা / উপজেলা-থানা / সেবা / পেশাজীবীর নাম) যেকোনো একটা বেছে তার রিপোর্ট হয়।
 * একজন কর্মী একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে গোনা হয়; নিজে-লেখা পেশা (তালিকায় নেই) আলাদা সারিতে আসে।
 * ফল: ['head', 'rows' => [['cells','type'],...], 'summary', 'people'] — type = row | sub | sum।
 * 'sub' = গোষ্ঠীর (এলাকা/সেবা) নিচের মোট-সারি; 'sum' = টেবিলের একদম নিচের যোগফল-সারি (cells = [লেখা, পেশাজীবীর সংখ্যা])।
 * 'people' = ফলে আলাদা কতজন পেশাজীবী (একজন একাধিক পেশায় থাকলেও একবার)।
 */
final class ManpowerReport
{
    /** দিক => [ঘরের শিরোনাম, "সব"-এর লেখা]; ক্রম = ফর্মের ঘরের ক্রম */
    public const DIMENSIONS = [
        'division' => ['বিভাগ', 'সকল বিভাগ'],
        'district' => ['জেলা', 'সকল জেলা'],
        'upazila' => ['উপজেলা/থানা', 'সকল উপজেলা/থানা'],
        'service' => ['সেবাসমূহ', 'সকল সেবা'],
        'name' => ['পেশাজীবির নাম', 'সকল নাম'],
    ];

    /** ঘরের মান: ফাঁকা = "বাছাই করুন" (কিছু বাছা হয়নি), ALL = "সকল ..." */
    public const ALL = 'all';

    private const CUSTOM_LABEL = 'নিজের লেখা পেশা (তালিকায় নেই)';

    /**
     * একটা দিক বেছে রিপোর্ট। $by ফাঁকা বা অজানা হলে শুধু হেডিং + একটা খালি সারি + শূন্য যোগফল।
     *
     * @param string $by    দিক (DIMENSIONS-এর চাবি) বা ফাঁকা
     * @param string $value ফাঁকা বা ALL = সব; নয়তো আইডি / সেবার slug
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    public static function build(string $by, string $value): array
    {
        if (!isset(self::DIMENSIONS[$by])) {
            return self::blank([]);
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::blank(['ডাটাবেস সংযোগ নেই, তাই এই রিপোর্ট এখানে দেখানো যাচ্ছে না।']);
        }

        try {
            $people = self::rows($pdo);
            $report = $by === 'name' ? self::byName($people) : self::byCounts($people, $by, $value === self::ALL ? '' : $value);
            $total = count(array_unique(array_column($people, 'id')));
        } catch (Throwable $e) {
            Logger::error('ManpowerReport failed: ' . $e->getMessage());

            return self::blank(['রিপোর্ট তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }
        $report['summary'][] = 'সাইটে এখন মোট অনুমোদিত পেশাজীবী (আলাদা মানুষ): ' . bn($total) . ' জন। শুধু অনুমোদিত পেশাজীবী (যাঁরা সাইটে দেখান) গোনা হয়েছে। একজন একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে ধরা হয়, তাই সেবা অনুযায়ী সারির যোগফল মোট মানুষের চেয়ে বেশি হতে পারে; নিচের যোগফল-সারিতে আলাদা মানুষ গোনা হয়।';

        return $report;
    }

    /** সেবার slug => নাম (বন্ধ সেবাসহ), সেবার ক্রমে। @return array<string,string> */
    public static function serviceNames(): array
    {
        return array_column(ServiceAdmin::all(), 'name', 'slug');
    }

    /**
     * কিছু বাছা না হলে বা ত্রুটিতে: হেডিংসহ টেবিল, একটা খালি সারি আর শূন্য যোগফল।
     *
     * @param list<string> $summary
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    private static function blank(array $summary): array
    {
        return [
            'head' => ['নাম', 'সেবা', 'মোট সংখ্যা'],
            'rows' => [['cells' => ['', '', ''], 'type' => 'row'], self::sumRow([])],
            'summary' => $summary,
            'people' => 0,
        ];
    }

    /**
     * অনুমোদিত কর্মীর সারি: প্রধান পেশায় একটা, প্রতিটি "আরও পেশা"-য় একটা করে।
     *
     * @return list<array{id:int,name:string,phone:string,division_id:int,district_id:int,upazila_id:int,slug:string}>
     */
    private static function rows(\PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT u.id, u.name, u.phone, u.division_id, u.district_id, u.upazila_id, COALESCE(u.service_slug, '') AS slug
               FROM users u WHERE u.role = 'worker' AND u.status = 'approved'
             UNION ALL
             SELECT u.id, u.name, u.phone, u.division_id, u.district_id, u.upazila_id, ws.service_slug AS slug
               FROM users u JOIN worker_services ws ON ws.user_id = u.id
              WHERE u.role = 'worker' AND u.status = 'approved'"
        )->fetchAll();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'phone' => (string) ($r['phone'] ?? ''),
            'division_id' => (int) $r['division_id'], 'district_id' => (int) $r['district_id'], 'upazila_id' => (int) $r['upazila_id'],
            'slug' => (string) $r['slug'],
        ], $rows);
    }

    /**
     * টেবিলের একদম নিচের যোগফল-সারি: কতটা বিভাগ, জেলা, উপজেলা/থানা, সেবা ও কতজন পেশাজীবী (আলাদা মানুষ)।
     *
     * @param list<array{id:int,division_id:int,district_id:int,upazila_id:int,slug:string}> $scope এই রিপোর্টে যে সারিগুলো এসেছে
     * @return array{cells:list<string>,type:string}
     */
    private static function sumRow(array $scope): array
    {
        $count = static fn (string $key): int => count(array_filter(array_unique(array_column($scope, $key))));
        $people = count(array_unique(array_column($scope, 'id')));

        return ['cells' => [
            'যোগফল — ' . bn($count('division_id')) . 'টি বিভাগ, ' . bn($count('district_id')) . 'টি জেলা, ' . bn($count('upazila_id')) . 'টি উপজেলা/থানা, '
            . bn($count('slug')) . 'টি সেবা, ' . bn($people) . ' জন পেশাজীবী',
            bn($people),
        ], 'type' => 'sum'];
    }

    /**
     * বিভাগ / জেলা / উপজেলা / সেবা দিক: গোষ্ঠীর (এলাকা বা সেবা) ভেতরে সারি, প্রতি গোষ্ঠীর নিচে মোট, একদম শেষে যোগফল।
     *
     * @param list<array<string,mixed>> $people
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    private static function byCounts(array $people, string $by, string $value): array
    {
        $services = self::serviceNames();
        $geo = GeoRepository::all();
        $divisions = array_column($geo['divisions'], 'name', 'id');
        $districts = array_column($geo['districts'], 'name', 'id');
        $districtDivision = array_column($geo['districts'], 'parent', 'id');
        $upazilas = array_column($geo['upazilas'], 'name', 'id');
        $unknown = 'এলাকা অজানা';

        $serviceLabel = static fn (string $slug): string => $slug === '' ? self::CUSTOM_LABEL : ($services[$slug] ?? $slug);

        // গোষ্ঠী => [name, items => [চাবি => [label, n]]]
        $groups = [];
        $scope = [];
        foreach ($people as $r) {
            $slug = (string) $r['slug'];
            if ($by === 'service') {
                if ($value !== '' && $slug !== $value) {
                    continue;
                }
                $gKey = $slug;
                $gName = $serviceLabel($slug);
                $dId = (int) $r['district_id'];
                $iKey = (string) $dId;
                $iLabel = [$districts[$dId] ?? $unknown, $divisions[$districtDivision[$dId] ?? (int) $r['division_id']] ?? ''];
            } else {
                $id = (int) $r[$by . '_id'];
                if ($value !== '' && (string) $id !== $value) {
                    continue;
                }
                $names = ['division' => $divisions, 'district' => $districts, 'upazila' => $upazilas][$by];
                $gKey = (string) $id;
                $gName = $names[$id] ?? $unknown;
                $iKey = $slug;
                $iLabel = [$serviceLabel($slug)];
            }
            $scope[] = $r;
            $groups[$gKey]['name'] = $gName;
            $groups[$gKey]['items'][$iKey]['label'] = $iLabel;
            $groups[$gKey]['items'][$iKey]['n'] = ($groups[$gKey]['items'][$iKey]['n'] ?? 0) + 1;
        }

        // বাছা এলাকা/সেবায় কেউ না থাকলেও গোষ্ঠীটা শূন্য দিয়ে দেখানো হয়
        if ($value !== '' && !isset($groups[$value])) {
            $groups[$value] = ['name' => match ($by) {
                'service' => $serviceLabel($value),
                'division' => $divisions[(int) $value] ?? $unknown,
                'district' => $districts[(int) $value] ?? $unknown,
                default => $upazilas[(int) $value] ?? $unknown,
            }, 'items' => []];
        }

        // ক্রম: সেবা = সেবার তালিকার ক্রমে; এলাকা = নামের বর্ণানুক্রমে
        $order = array_flip(array_keys($services));
        if ($by === 'service') {
            uksort($groups, static fn ($a, $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        } else {
            uasort($groups, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        }

        $head = $by === 'service' ? ['সেবা', 'জেলা', 'বিভাগ', 'মোট সংখ্যা'] : [self::DIMENSIONS[$by][0], 'সেবা', 'মোট সংখ্যা'];
        $last = count($head) - 1;
        $out = [];
        foreach ($groups as $g) {
            $items = $g['items'] ?? [];
            if ($by === 'service') {
                uasort($items, static fn (array $a, array $b): int => $b['n'] <=> $a['n'] ?: strcmp($a['label'][0], $b['label'][0]));
            } else {
                uksort($items, static fn ($a, $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            }
            $sum = 0;
            foreach ($items as $it) {
                $out[] = ['cells' => $by === 'service' ? [$g['name'], $it['label'][0], $it['label'][1] ?? '', bn($it['n'])] : [$g['name'], $it['label'][0], bn($it['n'])], 'type' => 'row'];
                $sum += $it['n'];
            }
            if ($items === []) {
                $out[] = ['cells' => $by === 'service' ? [$g['name'], 'কেউ নেই', '', bn(0)] : [$g['name'], 'কেউ নেই', bn(0)], 'type' => 'row'];
            }
            $out[] = ['cells' => array_replace(array_fill(0, $last + 1, ''), [0 => 'মোট — ' . $g['name'], $last => bn($sum)]), 'type' => 'sub'];
        }
        $out[] = self::sumRow($scope);

        return ['head' => $head, 'rows' => $out, 'summary' => [], 'people' => count(array_unique(array_column($scope, 'id')))];
    }

    /**
     * "সকল নাম": প্রতিটি সেবার নিচে সেই সেবার সব পেশাজীবীর নাম (ঠিকানাসহ), প্রতি সেবার নিচে মোট, একদম শেষে যোগফল।
     *
     * @param list<array<string,mixed>> $people
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    private static function byName(array $people): array
    {
        $services = self::serviceNames();
        $geo = GeoRepository::all();
        $divisions = array_column($geo['divisions'], 'name', 'id');
        $districts = array_column($geo['districts'], 'name', 'id');
        $upazilas = array_column($geo['upazilas'], 'short', 'id');

        usort($people, static fn (array $a, array $b): int => strcmp($a['slug'], $b['slug']) ?: strcmp($a['name'], $b['name']));
        $groups = [];
        foreach ($people as $r) {
            $groups[$r['slug']][] = $r;
        }
        $order = array_flip(array_keys($services));
        uksort($groups, static fn ($a, $b): int => ($a === '' ? PHP_INT_MAX : ($order[$a] ?? PHP_INT_MAX - 1)) <=> ($b === '' ? PHP_INT_MAX : ($order[$b] ?? PHP_INT_MAX - 1)));

        $head = ['সেবা', 'ক্রমিক', 'নাম', 'উপজেলা/থানা', 'জেলা', 'বিভাগ', 'মোবাইল', 'মোট সংখ্যা'];
        $out = [];
        foreach ($groups as $slug => $list) {
            $label = $slug === '' ? self::CUSTOM_LABEL : ($services[$slug] ?? $slug);
            foreach ($list as $i => $p) {
                $out[] = ['cells' => [
                    $label, bn($i + 1), $p['name'],
                    $upazilas[$p['upazila_id']] ?? '—', $districts[$p['district_id']] ?? '—', $divisions[$p['division_id']] ?? '—',
                    $p['phone'] !== '' ? format_phone($p['phone']) : '—', '',
                ], 'type' => 'row'];
            }
            $out[] = ['cells' => ['মোট — ' . $label, '', '', '', '', '', '', bn(count($list))], 'type' => 'sub'];
        }
        $out[] = self::sumRow($people);

        return ['head' => $head, 'rows' => $out, 'summary' => [], 'people' => count(array_unique(array_column($people, 'id')))];
    }
}
