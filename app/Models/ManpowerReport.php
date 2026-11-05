<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * রিপোর্ট "চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য": অনুমোদিত পেশাজীবী কতজন, কোন এলাকায়, কোন সেবায়।
 * তারিখ-নির্ভর নয়। পাঁচটি ঘরের বাছাই একসঙ্গে খাটে ("এবং"): বিভাগ → জেলা → উপজেলা/থানা → সেবা, আর পেশাজীবীর নামের ঘর
 * ঠিক করে নামসহ তালিকা হবে, নাকি শুধু সংখ্যা। একজন একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে গোনা হয়;
 * নিজে-লেখা পেশা (তালিকায় নেই) আলাদা সারিতে আসে।
 * ফল: ['head', 'rows' => [['cells','type'],...], 'summary', 'people'] — type = row | sub | sum।
 * 'sub' = প্রতি উপজেলা/থানার নিচের মোট-সারি; 'sum' = টেবিলের একদম নিচের যোগফল-সারি (cells = [লেখা, পেশাজীবীর সংখ্যা])।
 * 'people' = ফলে আলাদা কতজন পেশাজীবী (একজন একাধিক পেশায় থাকলেও একবার)।
 */
final class ManpowerReport
{
    /** ঘর => [শিরোনাম, "সব"-এর লেখা]; ক্রম = ফর্মের ঘরের ক্রম */
    public const DIMENSIONS = [
        'division' => ['বিভাগ', 'সকল বিভাগ'],
        'district' => ['জেলা', 'সকল জেলা'],
        'upazila' => ['উপজেলা/থানা', 'সকল উপজেলা/থানা'],
        'service' => ['সেবাসমূহ', 'সকল সেবা'],
        'name' => ['পেশাজীবির নাম', 'সকল নাম'],
    ];

    /** ঘরের মান: ফাঁকা = "বাছাই করুন" (বাছা হয়নি), ALL = "সকল ..." */
    public const ALL = 'all';

    private const CUSTOM_LABEL = 'নিজের লেখা পেশা (তালিকায় নেই)';

    /**
     * ঘরগুলোর বাছাই মিলিয়ে রিপোর্ট। কিছুই না বাছলে শুধু হেডিং + একটা খালি সারি + শূন্য যোগফল।
     * বিভাগ/জেলা/উপজেলা/সেবার ঘরে ফাঁকা বা ALL = ওই ঘরে বাধা নেই; নামের ঘরে ALL হলে নামসহ তালিকা, নয়তো শুধু সংখ্যা।
     *
     * @param array<string,string> $filters চাবি = DIMENSIONS-এর চাবি; মান ফাঁকা / ALL / আইডি / সেবার slug
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    public static function build(array $filters): array
    {
        $filters += array_fill_keys(array_keys(self::DIMENSIONS), '');
        if (count(array_filter($filters, static fn (string $v): bool => $v !== '')) === 0) {
            return self::blank([]);
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::blank(['ডাটাবেস সংযোগ নেই, তাই এই রিপোর্ট এখানে দেখানো যাচ্ছে না।']);
        }

        try {
            $people = self::rows($pdo);
            $report = self::table($people, $filters);
            $total = count(array_unique(array_column($people, 'id')));
        } catch (Throwable $e) {
            Logger::error('ManpowerReport failed: ' . $e->getMessage());

            return self::blank(['রিপোর্ট তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }
        $report['summary'][] = 'সাইটে এখন মোট অনুমোদিত পেশাজীবী (আলাদা মানুষ): ' . bn($total) . ' জন। শুধু অনুমোদিত পেশাজীবী (যাঁরা সাইটে দেখান) গোনা হয়েছে। একজন একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে ধরা হয়, তাই উপজেলার মোট-সারির যোগফল মোট মানুষের চেয়ে বেশি হতে পারে; নিচের যোগফল-সারিতে আলাদা মানুষ গোনা হয়।';

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
            'head' => self::HEAD,
            'rows' => [['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'], self::sumRow([])],
            'summary' => $summary,
            'people' => 0,
        ];
    }

    private const HEAD = ['ক্রমিক নং', 'বিভাগ', 'জেলা', 'উপজেলা/থানা', 'এলাকার নাম', 'সেবা', 'পেশাজীবির নাম ও মোবাইল', 'মোট সংখ্যা'];

    /**
     * অনুমোদিত কর্মীর সারি: প্রধান পেশায় একটা, প্রতিটি "আরও পেশা"-য় একটা করে।
     *
     * @return list<array{id:int,name:string,phone:string,division_id:int,district_id:int,upazila_id:int,slug:string}>
     */
    private static function rows(\PDO $pdo): array
    {
        $rows = $pdo->query(
            "SELECT u.id, u.name, u.phone, u.area_name, u.division_id, u.district_id, u.upazila_id, COALESCE(u.service_slug, '') AS slug
               FROM users u WHERE u.role = 'worker' AND u.status = 'approved'
             UNION ALL
             SELECT u.id, u.name, u.phone, u.area_name, u.division_id, u.district_id, u.upazila_id, ws.service_slug AS slug
               FROM users u JOIN worker_services ws ON ws.user_id = u.id
              WHERE u.role = 'worker' AND u.status = 'approved'"
        )->fetchAll();

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'phone' => (string) ($r['phone'] ?? ''),
            'area_name' => (string) ($r['area_name'] ?? ''), 'division_id' => (int) $r['division_id'], 'district_id' => (int) $r['district_id'], 'upazila_id' => (int) $r['upazila_id'],
            'slug' => (string) $r['slug'],
        ], $rows);
    }

    /**
     * টেবিলের একদম নিচের যোগফল-সারি: কতটা বিভাগ, জেলা, উপজেলা/থানা, সেবা ও কতজন পেশাজীবী (আলাদা মানুষ)।
     *
     * @param list<array<string,mixed>> $scope এই রিপোর্টে যে সারিগুলো এসেছে
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
     * বাছাই মিলিয়ে সারি ছেঁকে টেবিল: উপজেলা/থানা ধরে গোষ্ঠী, প্রতি গোষ্ঠীর নিচে মোট-সারি, একদম শেষে যোগফল-সারি।
     * নাম "সকল নাম" না হলে প্রতি (উপজেলা, সেবা)-র একটা সারি, নাম-মোবাইল ঘর ফাঁকা, শেষ ঘরে সংখ্যা।
     *
     * @param list<array<string,mixed>> $people
     * @param array<string,string> $f
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>,people:int}
     */
    private static function table(array $people, array $f): array
    {
        $services = self::serviceNames();
        $geo = GeoRepository::all();
        $divisions = array_column($geo['divisions'], 'name', 'id');
        $districts = array_column($geo['districts'], 'name', 'id');
        $upazilas = array_column($geo['upazilas'], 'short', 'id');
        $unknown = 'এলাকা অজানা';
        $withNames = $f['name'] === self::ALL;

        $scope = array_values(array_filter($people, static function (array $r) use ($f): bool {
            foreach (['division', 'district', 'upazila'] as $dim) {
                if ($f[$dim] !== '' && $f[$dim] !== self::ALL && (string) $r[$dim . '_id'] !== $f[$dim]) {
                    return false;
                }
            }

            return $f['service'] === '' || $f['service'] === self::ALL || $r['slug'] === $f['service'];
        }));

        $serviceLabel = static fn (string $slug): string => $slug === '' ? self::CUSTOM_LABEL : ($services[$slug] ?? $slug);
        $order = array_flip(array_keys($services));
        $rank = static fn (string $slug): int => $slug === '' ? PHP_INT_MAX : ($order[$slug] ?? PHP_INT_MAX - 1);
        $key = static fn (array $r): array => [
            $divisions[$r['division_id']] ?? $unknown, $districts[$r['district_id']] ?? $unknown, $upazilas[$r['upazila_id']] ?? $unknown,
            $r['upazila_id'], $r['area_name'], $rank($r['slug']), $r['name'],
        ];
        usort($scope, static fn (array $a, array $b): int => $key($a) <=> $key($b));

        // উপজেলা (আইডি) ধরে গোষ্ঠী
        $groups = [];
        foreach ($scope as $r) {
            $groups[$r['upazila_id']][] = $r;
        }

        $out = [];
        $no = 0;
        foreach ($groups as $upazilaId => $list) {
            $area = [$divisions[$list[0]['division_id']] ?? $unknown, $districts[$list[0]['district_id']] ?? $unknown, $upazilas[$upazilaId] ?? $unknown];
            $count = 0;
            if ($withNames) {
                foreach ($list as $p) {
                    $out[] = ['cells' => [bn(++$no), ...$area, $p['area_name'] !== '' ? $p['area_name'] : '—', $serviceLabel($p['slug']), $p['name'] . ($p['phone'] !== '' ? "\n" . format_phone($p['phone']) : ''), ''], 'type' => 'row'];
                    ++$count;
                }
            } else {
                // (এলাকার নাম, সেবা) ধরে গোনা: ছোট এলাকাভিত্তিক সারি
                $byAreaSlug = [];
                foreach ($list as $p) {
                    $k = $p['area_name'] . "\0" . $p['slug'];
                    $byAreaSlug[$k] = [$p['area_name'], $p['slug'], ($byAreaSlug[$k][2] ?? 0) + 1];
                }
                uasort($byAreaSlug, static fn (array $a, array $b): int => [$a[0], $rank($a[1])] <=> [$b[0], $rank($b[1])]);
                foreach ($byAreaSlug as [$areaName, $slug, $n]) {
                    $out[] = ['cells' => [bn(++$no), ...$area, $areaName !== '' ? $areaName : '—', $serviceLabel((string) $slug), '', bn($n)], 'type' => 'row'];
                    $count += $n;
                }
            }
            $out[] = ['cells' => ['মোট — ' . $area[2], '', '', '', '', '', '', bn($count)], 'type' => 'sub'];
        }
        if ($out === []) {
            $out[] = ['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'];
        }
        $out[] = self::sumRow($scope);

        return ['head' => self::HEAD, 'rows' => $out, 'summary' => [], 'people' => count(array_unique(array_column($scope, 'id')))];
    }
}
