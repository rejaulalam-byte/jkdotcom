<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * রিপোর্ট "একজন পেশাজীবির একাধিক স্থান ও সেবার নামের তালিকা": কে কয়টি বিভাগ, জেলা, উপজেলা/থানা ও সেবার জন্য নাম দিয়েছেন।
 * স্থান = নিজের ঠিকানার জেলা/উপজেলা + "কাজ করতে আগ্রহী" জেলাগুলো ("সব জেলা" হলে ৬৪টিই); বিভাগ জেলা থেকে আসে।
 * সেবা = প্রধান পেশা (আগে) + "আরও পেশা"। শুধু যাঁর একাধিক জেলা বা একাধিক সেবা আছে তিনি আসেন; অনুমোদিত পেশাজীবী।
 * ফল ReportData-র ছাঁচে: ['head', 'rows' => [['cells','type'],...], 'summary'] — type = row | sum।
 */
final class MultiPlaceReport
{
    /** সর্বোচ্চ কতজনের সারি দেখানো হয় (বেশি হলে কেটে জানানো হয়) */
    public const MAX_PEOPLE = 400;

    private const HEAD = ['ক্রমিক নং', 'পেশাজীবির নাম', 'মোবাইল নাম্বার', 'বিভাগ (সংখ্যা)', 'জেলা (সংখ্যা)', 'উপজেলা/থানা (সংখ্যা)', 'সেবা (সংখ্যা)', 'কাজের জেলা', 'সেবার নাম (প্রধান আগে)'];

    /** @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} */
    public static function build(): array
    {
        $empty = ['head' => self::HEAD, 'rows' => [['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'], self::sumRow(0, 0, 0)], 'summary' => []];
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

    /**
     * @param array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $empty
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    private static function make(\PDO $pdo, array $empty): array
    {
        $services = ManpowerReport::serviceNames();
        $order = array_flip(array_keys($services));
        $geo = GeoRepository::all();
        $districtName = array_column($geo['districts'], 'name', 'id');
        $districtDivision = array_column($geo['districts'], 'parent', 'id');

        $users = $pdo->query(
            "SELECT id, name, phone, service_slug, service_custom, division_id, district_id, upazila_id, work_all_districts
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
            $divisions = array_values(array_unique(array_filter(array_merge(array_map(static fn (int $d): int => (int) ($districtDivision[$d] ?? 0), $districts), [(int) $u['division_id']]))));

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
            $workList = $all ? 'সব জেলা' : implode(', ', array_map(static fn (int $d): string => $districtName[$d] ?? '', array_values(array_unique($work[$id] ?? []))));
            $people[] = [
                'name' => (string) $u['name'], 'phone' => (string) ($u['phone'] ?? ''),
                'divisions' => count($divisions), 'districts' => count($districts), 'upazilas' => (int) $u['upazila_id'] > 0 ? 1 : 0,
                'names' => $names, 'work' => $workList !== '' ? $workList : '—',
            ];
        }

        // বেশি সেবা, তারপর বেশি জেলা, তারপর নাম
        usort($people, static fn (array $a, array $b): int => [count($b['names']), $b['districts'], $a['name']] <=> [count($a['names']), $a['districts'], $b['name']]);
        $summary = [];
        $totalPeople = count($people);
        if ($totalPeople > self::MAX_PEOPLE) {
            $summary[] = 'মোট ' . bn($totalPeople) . ' জন, তাই প্রথম ' . bn(self::MAX_PEOPLE) . ' জন দেখানো হলো।';
            $people = array_slice($people, 0, self::MAX_PEOPLE);
        }
        $summary[] = 'শুধু অনুমোদিত পেশাজীবীদের মধ্যে যাঁর একাধিক জেলা (নিজের জেলা + কাজ করতে আগ্রহী জেলা) অথবা একাধিক সেবা আছে তাঁরাই এখানে আসেন। উপজেলা/থানা বলতে নিজের ঠিকানার উপজেলা (সর্বোচ্চ ১টি)। সেবার নামে প্রধান পেশা আগে, তারপর "আরও পেশা"।';

        $rows = [];
        $serviceCount = 0;
        foreach ($people as $i => $p) {
            foreach ($p['names'] as $k => $serviceName) {
                $rows[] = ['cells' => $k === 0
                    ? [bn($i + 1), $p['name'], $p['phone'] !== '' ? format_phone($p['phone']) : '—', bn($p['divisions']), bn($p['districts']), bn($p['upazilas']), bn(count($p['names'])), $p['work'], $serviceName]
                    : ['', '', '', '', '', '', '', '', $serviceName], 'type' => 'row'];
            }
            if ($p['names'] === []) {
                $rows[] = ['cells' => [bn($i + 1), $p['name'], $p['phone'] !== '' ? format_phone($p['phone']) : '—', bn($p['divisions']), bn($p['districts']), bn($p['upazilas']), bn(0), $p['work'], '—'], 'type' => 'row'];
            }
            $serviceCount += count($p['names']);
        }
        if ($rows === []) {
            $rows[] = ['cells' => array_fill(0, count(self::HEAD), ''), 'type' => 'row'];
        }
        $rows[] = self::sumRow(count($people), $serviceCount, array_sum(array_column($people, 'districts')));

        return ['head' => self::HEAD, 'rows' => $rows, 'summary' => $summary];
    }

    /** একদম নিচের যোগফল-সারি: cells = [লেখা, পেশাজীবীর সংখ্যা]। */
    private static function sumRow(int $people, int $services, int $districts): array
    {
        return ['cells' => ['যোগফল — ' . bn($people) . ' জন পেশাজীবী, সেবার নাম মোট ' . bn($services) . 'টি, জেলার স্থান মোট ' . bn($districts) . 'টি', bn($people)], 'type' => 'sum'];
    }
}
