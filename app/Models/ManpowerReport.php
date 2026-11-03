<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * রিপোর্ট "চাহিদাভিত্তিক বিভিন্ন সেবার লোকবলের তথ্য": অনুমোদিত পেশাজীবী কতজন, কোন এলাকায়, কোন সেবায়।
 * তারিখ-নির্ভর নয়; পাঁচটি দিকের যেকোনো একটা বেছে (বিভাগ / জেলা / উপজেলা-থানা / সেবা / পেশাজীবীর নাম) তার রিপোর্ট হয়।
 * একজন কর্মী একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে গোনা হয়; নিজে-লেখা পেশা (তালিকায় নেই) আলাদা সারিতে আসে।
 * ফল ReportData-র মতোই: ['head', 'rows' => [['cells','type'],...], 'summary'] — type = row | sub | total।
 */
final class ManpowerReport
{
    /** দিক => [ঘরের লেবেল, "সব"-এর লেখা, বোতামের লেখা] */
    public const DIMENSIONS = [
        'division' => ['বিভাগ', 'সব বিভাগ', 'বিভাগ অনুযায়ী দেখান'],
        'district' => ['জেলা', 'সব জেলা', 'জেলা অনুযায়ী দেখান'],
        'upazila' => ['উপজেলা/থানা', 'সব উপজেলা/থানা', 'উপজেলা/থানা অনুযায়ী দেখান'],
        'service' => ['সেবাসমূহ', 'সব সেবা', 'সেবা অনুযায়ী দেখান'],
        'name' => ['পেশাজীবীর নাম', 'বাছাই করুন', 'নাম অনুযায়ী দেখান'],
    ];

    /** পেশাজীবীর নামের ঘরের দুই বিকল্প: '' = বাছাই করুন (কিছু হবে না), 'all' = সকল নাম */
    public const NAME_ALL = 'all';

    /**
     * @param string $by    দিক (DIMENSIONS-এর চাবি)
     * @param string $value বাছাই: আইডি / সেবার slug / NAME_ALL; ফাঁকা = "সব" (নামের ঘরে ফাঁকা = কিছু বাছা হয়নি)
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    public static function build(string $by, string $value): array
    {
        $empty = ['head' => [], 'rows' => [], 'summary' => []];
        if (!isset(self::DIMENSIONS[$by])) {
            return $empty;
        }
        if ($by === 'name' && $value !== self::NAME_ALL) {
            $empty['summary'][] = 'পেশাজীবীর নামের ঘরে "সকল নাম" বেছে তারপর বোতাম চাপুন।';

            return $empty;
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            $empty['summary'][] = 'ডাটাবেস সংযোগ নেই, তাই এই রিপোর্ট এখানে দেখানো যাচ্ছে না।';

            return $empty;
        }

        try {
            $report = $by === 'name' ? self::byName($pdo) : self::byCounts($pdo, $by, $value);
        } catch (Throwable $e) {
            Logger::error('ManpowerReport failed: ' . $e->getMessage());
            $empty['summary'][] = 'রিপোর্ট তৈরি করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';

            return $empty;
        }
        try {
            $people = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'worker' AND status = 'approved'")->fetchColumn();
            $report['summary'][] = 'সাইটে এখন মোট অনুমোদিত পেশাজীবী (আলাদা মানুষ): ' . bn($people) . ' জন।';
        } catch (Throwable $e) {
            Logger::error('ManpowerReport count failed: ' . $e->getMessage());
        }
        $report['summary'][] = 'শুধু অনুমোদিত পেশাজীবী গোনা হয়েছে (যাঁরা সাইটে দেখান)। একজন একাধিক পেশায় থাকলে প্রতিটি পেশায় একবার করে ধরা হয়, তাই মোট সংখ্যা মোট মানুষের সংখ্যার চেয়ে বেশি হতে পারে।';

        return $report;
    }

    /** সেবার slug => নাম (বন্ধ সেবাসহ), সেবার ক্রমে। @return array<string,string> */
    public static function serviceNames(): array
    {
        return array_column(ServiceAdmin::all(), 'name', 'slug');
    }

    /**
     * বিভাগ / জেলা / উপজেলা / সেবা দিক: গোষ্ঠীর (এলাকা বা সেবা) ভেতরে সারি, প্রতি গোষ্ঠীর নিচে মোট, শেষে সর্বমোট।
     *
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    private static function byCounts(\PDO $pdo, string $by, string $value): array
    {
        $services = self::serviceNames();
        $geo = GeoRepository::all();
        $divisions = array_column($geo['divisions'], 'name', 'id');
        $districts = array_column($geo['districts'], 'name', 'id');
        $districtDivision = array_column($geo['districts'], 'parent', 'id');
        $upazilas = array_column($geo['upazilas'], 'name', 'id');

        // (বিভাগ, জেলা, উপজেলা, সেবা) প্রতি কতজন
        $rows = $pdo->query(
            "SELECT x.division_id, x.district_id, x.upazila_id, x.slug, COUNT(*) AS n FROM (
                SELECT u.id, u.division_id, u.district_id, u.upazila_id, COALESCE(u.service_slug, '') AS slug
                  FROM users u WHERE u.role = 'worker' AND u.status = 'approved'
                UNION ALL
                SELECT u.id, u.division_id, u.district_id, u.upazila_id, ws.service_slug AS slug
                  FROM users u JOIN worker_services ws ON ws.user_id = u.id
                 WHERE u.role = 'worker' AND u.status = 'approved'
             ) x GROUP BY x.division_id, x.district_id, x.upazila_id, x.slug"
        )->fetchAll();

        $serviceLabel = static fn (string $slug): string => $slug === '' ? 'নিজের লেখা পেশা (তালিকায় নেই)' : ($services[$slug] ?? $slug);
        $areaOf = static function (array $r) use ($by, $divisions, $districts, $upazilas): array {
            return match ($by) {
                'division' => [(int) $r['division_id'], $divisions[(int) $r['division_id']] ?? 'এলাকা অজানা'],
                'district' => [(int) $r['district_id'], $districts[(int) $r['district_id']] ?? 'এলাকা অজানা'],
                default => [(int) $r['upazila_id'], $upazilas[(int) $r['upazila_id']] ?? 'এলাকা অজানা'],
            };
        };

        // গোষ্ঠী => [নাম, ভেতরের সারির চাবি => [লেবেল..., সংখ্যা]]
        $groups = [];
        foreach ($rows as $r) {
            $slug = (string) $r['slug'];
            if ($by === 'service') {
                if ($value !== '' && $slug !== $value) {
                    continue;
                }
                $gKey = $slug;
                $gName = $serviceLabel($slug);
                $dId = (int) $r['district_id'];
                $iKey = (string) $dId;
                $iLabel = [$districts[$dId] ?? 'এলাকা অজানা', $divisions[$districtDivision[$dId] ?? 0] ?? ((string) ($divisions[(int) $r['division_id']] ?? ''))];
            } else {
                [$id, $name] = $areaOf($r);
                if ($value !== '' && (string) $id !== $value) {
                    continue;
                }
                $gKey = (string) $id;
                $gName = $name;
                $iKey = $slug;
                $iLabel = [$serviceLabel($slug)];
            }
            $groups[$gKey]['name'] = $gName;
            $groups[$gKey]['items'][$iKey]['label'] = $iLabel;
            $groups[$gKey]['items'][$iKey]['n'] = ($groups[$gKey]['items'][$iKey]['n'] ?? 0) + (int) $r['n'];
        }

        // বাছা এলাকা/সেবায় কেউ না থাকলেও গোষ্ঠীটা শূন্য দিয়ে দেখানো হয়
        if ($value !== '' && !isset($groups[$value])) {
            $groups[$value] = ['name' => self::pickedName($by, $value, $services, $divisions, $districts, $upazilas), 'items' => []];
        }

        // ক্রম: সেবা = সেবার তালিকার ক্রমে; এলাকা = নামের বর্ণানুক্রমে
        if ($by === 'service') {
            $order = array_flip(array_keys($services));
            uksort($groups, static fn ($a, $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
        } else {
            uasort($groups, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));
        }

        $head = $by === 'service' ? ['সেবা', 'জেলা', 'বিভাগ', 'লোকসংখ্যা'] : [self::DIMENSIONS[$by][0], 'সেবা', 'লোকসংখ্যা'];
        $out = [];
        $grand = 0;
        $order = array_flip(array_keys($services));
        foreach ($groups as $g) {
            $items = $g['items'] ?? [];
            if ($by === 'service') {
                uasort($items, static fn (array $a, array $b): int => $b['n'] <=> $a['n'] ?: strcmp($a['label'][0], $b['label'][0]));
            } else {
                uksort($items, static fn ($a, $b): int => ($order[$a] ?? PHP_INT_MAX) <=> ($order[$b] ?? PHP_INT_MAX));
            }
            $sum = 0;
            foreach ($items as $it) {
                $cells = $by === 'service' ? [$g['name'], $it['label'][0], $it['label'][1] ?? '', bn($it['n'])] : [$g['name'], $it['label'][0], bn($it['n'])];
                $out[] = ['cells' => $cells, 'type' => 'row'];
                $sum += $it['n'];
            }
            if ($items === []) {
                $out[] = ['cells' => $by === 'service' ? [$g['name'], 'কেউ নেই', '', bn(0)] : [$g['name'], 'কেউ নেই', bn(0)], 'type' => 'row'];
            }
            $out[] = ['cells' => array_pad(['মোট — ' . $g['name']], count($head) - 1, '') + [count($head) - 1 => bn($sum)], 'type' => 'sub'];
            $grand += $sum;
        }
        $out[] = ['cells' => array_pad(['সর্বমোট'], count($head) - 1, '') + [count($head) - 1 => bn($grand)], 'type' => 'total'];

        return ['head' => $head, 'rows' => $out, 'summary' => []];
    }

    /** @return string বাছা এলাকা/সেবার নাম (গোষ্ঠী শূন্য হলে দেখানোর জন্য) */
    private static function pickedName(string $by, string $value, array $services, array $divisions, array $districts, array $upazilas): string
    {
        return match ($by) {
            'service' => $value === '' ? 'নিজের লেখা পেশা (তালিকায় নেই)' : ($services[$value] ?? $value),
            'division' => $divisions[(int) $value] ?? 'এলাকা অজানা',
            'district' => $districts[(int) $value] ?? 'এলাকা অজানা',
            default => $upazilas[(int) $value] ?? 'এলাকা অজানা',
        };
    }

    /**
     * "সকল নাম": প্রতিটি সেবার নিচে সেই সেবার সব পেশাজীবীর নাম (ঠিকানাসহ), প্রতি সেবার নিচে মোট, শেষে সর্বমোট।
     *
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    private static function byName(\PDO $pdo): array
    {
        $services = self::serviceNames();
        $geo = GeoRepository::all();
        $divisions = array_column($geo['divisions'], 'name', 'id');
        $districts = array_column($geo['districts'], 'name', 'id');
        $upazilas = array_column($geo['upazilas'], 'short', 'id');

        $rows = $pdo->query(
            "SELECT x.name, x.phone, x.division_id, x.district_id, x.upazila_id, x.slug FROM (
                SELECT u.name, u.phone, u.division_id, u.district_id, u.upazila_id, COALESCE(u.service_slug, '') AS slug
                  FROM users u WHERE u.role = 'worker' AND u.status = 'approved'
                UNION ALL
                SELECT u.name, u.phone, u.division_id, u.district_id, u.upazila_id, ws.service_slug AS slug
                  FROM users u JOIN worker_services ws ON ws.user_id = u.id
                 WHERE u.role = 'worker' AND u.status = 'approved'
             ) x ORDER BY x.slug, x.name"
        )->fetchAll();

        $groups = [];
        foreach ($rows as $r) {
            $groups[(string) $r['slug']][] = $r;
        }
        $order = array_flip(array_keys($services));
        uksort($groups, static fn ($a, $b): int => ($a === '' ? PHP_INT_MAX : ($order[$a] ?? PHP_INT_MAX - 1)) <=> ($b === '' ? PHP_INT_MAX : ($order[$b] ?? PHP_INT_MAX - 1)));

        $head = ['সেবা', 'ক্রমিক', 'নাম', 'উপজেলা/থানা', 'জেলা', 'বিভাগ', 'মোবাইল'];
        $out = [];
        $grand = 0;
        foreach ($groups as $slug => $people) {
            $label = $slug === '' ? 'নিজের লেখা পেশা (তালিকায় নেই)' : ($services[$slug] ?? $slug);
            foreach ($people as $i => $p) {
                $out[] = ['cells' => [
                    $label, bn($i + 1), (string) $p['name'],
                    $upazilas[(int) $p['upazila_id']] ?? '—', $districts[(int) $p['district_id']] ?? '—', $divisions[(int) $p['division_id']] ?? '—',
                    (string) ($p['phone'] ?? '') !== '' ? format_phone((string) $p['phone']) : '—',
                ], 'type' => 'row'];
            }
            $out[] = ['cells' => ['মোট — ' . $label, '', '', '', '', '', bn(count($people)) . ' জন'], 'type' => 'sub'];
            $grand += count($people);
        }
        $out[] = ['cells' => ['সর্বমোট', '', '', '', '', '', bn($grand) . ' জন'], 'type' => 'total'];

        return ['head' => $head, 'rows' => $out, 'summary' => []];
    }
}
