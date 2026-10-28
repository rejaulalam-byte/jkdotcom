<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** এলাকা: বিভাগ → জেলা → উপজেলা/থানা। */
final class GeoRepository
{
    /** @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string,short?:string}>}|null */
    private static ?array $cache = null;

    /**
     * সব এলাকা, নাম বাংলায়। যে উপজেলার নাম একাধিক জেলায় আছে (যেমন "সদর") তাকে
     * জেলাসহ দেখানো হয়: "সদর (বগুড়া জেলা)"।
     *
     * @return array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string}>}
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $raw = self::load();
        $districtName = [];
        foreach ($raw['districts'] as $d) {
            $districtName[$d[0]] = $d[2];
        }
        $count = array_count_values(array_map(static fn (array $u): string => $u[2], $raw['upazilas']));

        $upazilas = [];
        foreach ($raw['upazilas'] as $u) {
            $name = $count[$u[2]] > 1 ? $u[2] . ' (' . ($districtName[$u[1]] ?? '') . ')' : $u[2];
            $upazilas[] = ['id' => (int) $u[0], 'parent' => (int) $u[1], 'name' => $name, 'short' => $u[2]];
        }
        usort($upazilas, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        $districts = array_map(static fn (array $d): array => ['id' => (int) $d[0], 'parent' => (int) $d[1], 'name' => (string) $d[2]], $raw['districts']);
        usort($districts, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return self::$cache = [
            'divisions' => array_map(static fn (array $d): array => ['id' => (int) $d[0], 'name' => (string) $d[1]], $raw['divisions']),
            'districts' => $districts,
            'upazilas' => $upazilas,
        ];
    }

    /**
     * একটা জায়গার এলাকার নাম দেখানোর জন্য: উপজেলা/থানা, জেলা, বিভাগ ("বিভাগ" শব্দ ছাড়া)।
     *
     * @return array{upazila:string,district:string,division:string}
     */
    public static function names(int $upazilaId, int $districtId, int $divisionId): array
    {
        $geo = self::all();
        $out = ['upazila' => '', 'district' => '', 'division' => ''];
        foreach ($geo['upazilas'] as $u) {
            if ($u['id'] === $upazilaId) {
                $out['upazila'] = $u['short'] ?? $u['name'];
            }
        }
        foreach ($geo['districts'] as $d) {
            if ($d['id'] === $districtId) {
                $out['district'] = $d['name'];
            }
        }
        foreach ($geo['divisions'] as $v) {
            if ($v['id'] === $divisionId) {
                $out['division'] = trim((string) preg_replace('/\s*বিভাগ$/u', '', $v['name']));
            }
        }

        return $out;
    }

    /** একজন কর্মী সর্বোচ্চ কতটি জেলা বাছতে পারেন ("সব জেলা" আলাদা বাছাই)। */
    public const MAX_WORK_DISTRICTS = 5;

    /**
     * ফর্মে আসা "কোন জেলায় কাজ করতে আগ্রহী" মান পড়ে যাচাই করে।
     * $ids = work_districts[] (ট্যাগগুলো), $all = work_all ("সব জেলা"), $pick = JavaScript ছাড়া একটা জেলা বাছার ঘর।
     * "সব জেলা" হলে আলাদা জেলার তালিকা ফাঁকা থাকে।
     *
     * @return array{ids:list<int>,all:bool,error:?string}
     */
    public static function parseWorkDistricts(mixed $ids, mixed $all, mixed $pick): array
    {
        $list = [];
        foreach (is_array($ids) ? $ids : [] as $id) {
            $list[(int) $id] = (int) $id;
        }
        $isAll = !empty($all) || $pick === 'all';
        if (!$isAll && $list === [] && (int) $pick > 0) {
            $list[(int) $pick] = (int) $pick;
        }
        if ($isAll) {
            return ['ids' => [], 'all' => true, 'error' => null];
        }
        foreach ($list as $id) {
            if (!self::districtExists($id)) {
                return ['ids' => [], 'all' => false, 'error' => msg('msg.work_district_bad')];
            }
        }
        if (count($list) > self::MAX_WORK_DISTRICTS) {
            return ['ids' => [], 'all' => false, 'error' => msg('msg.work_districts_max', ['max' => bn(self::MAX_WORK_DISTRICTS)])];
        }

        return ['ids' => array_values($list), 'all' => false, 'error' => null];
    }

    /**
     * বাছা কাজের জেলাগুলো একটা লেখায় ("ঢাকা, বরিশাল" বা "সব জেলা"); কিছু না থাকলে ফাঁকা।
     *
     * @param list<int> $ids
     */
    public static function workDistrictsText(array $ids, bool $all): string
    {
        if ($all) {
            return 'সব জেলা';
        }

        return implode(', ', self::workDistrictNames($ids));
    }

    /**
     * জেলার id থেকে নাম (বাছাইয়ের ক্রমে)।
     *
     * @param list<int> $ids
     * @return list<string>
     */
    public static function workDistrictNames(array $ids): array
    {
        $byId = [];
        foreach (self::all()['districts'] as $d) {
            $byId[$d['id']] = $d['name'];
        }

        return array_values(array_filter(array_map(static fn ($id): string => $byId[(int) $id] ?? '', $ids), static fn (string $n): bool => $n !== ''));
    }

    /** এই id-র জেলা আছে কি না (ফর্মে আসা মান যাচাইয়ের জন্য)। */
    public static function districtExists(int $districtId): bool
    {
        foreach (self::all()['districts'] as $d) {
            if ($d['id'] === $districtId) {
                return true;
            }
        }

        return false;
    }

    /** উপজেলা জেলার অন্তর্গত এবং জেলা বিভাগের অন্তর্গত হলে true। */
    public static function isConsistent(int $divisionId, int $districtId, int $upazilaId): bool
    {
        $geo = self::all();
        $district = null;
        foreach ($geo['districts'] as $d) {
            if ($d['id'] === $districtId) {
                $district = $d;
            }
        }
        if ($district === null || $district['parent'] !== $divisionId) {
            return false;
        }
        foreach ($geo['upazilas'] as $u) {
            if ($u['id'] === $upazilaId) {
                return $u['parent'] === $districtId;
            }
        }

        return false;
    }

    /**
     * [id, name_bn] / [id, parent, name_bn] আকারে সারি: ডাটাবেস থেকে, না থাকলে
     * database/seeds/geo.php থেকে (লোকাল ডেমো)।
     *
     * @return array{divisions:list<list<mixed>>,districts:list<list<mixed>>,upazilas:list<list<mixed>>}
     */
    private static function load(): array
    {
        $pdo = Database::connection();
        if ($pdo !== null) {
            try {
                $fetch = static fn (string $sql): array => $pdo->query($sql)->fetchAll(\PDO::FETCH_NUM);
                $divisions = $fetch('SELECT id, name_bn FROM divisions ORDER BY id');
                if ($divisions !== []) {
                    return [
                        'divisions' => $divisions,
                        'districts' => array_map(static fn (array $r): array => [$r[0], $r[1], $r[2]], $fetch('SELECT id, division_id, name_bn FROM districts')),
                        'upazilas' => array_map(static fn (array $r): array => [$r[0], $r[1], $r[2]], $fetch('SELECT id, district_id, name_bn FROM upazilas')),
                    ];
                }
            } catch (Throwable $e) {
                Logger::error('GeoRepository::load failed: ' . $e->getMessage());
            }
        }

        $seed = require BASE_PATH . '/database/seeds/geo.php';

        return [
            'divisions' => array_map(static fn (array $r): array => [$r[0], $r[1]], $seed['divisions']),
            'districts' => array_map(static fn (array $r): array => [$r[0], $r[1], $r[2]], $seed['districts']),
            'upazilas' => array_map(static fn (array $r): array => [$r[0], $r[1], $r[2]], $seed['upazilas']),
        ];
    }
}
