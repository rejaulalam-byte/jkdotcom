<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** Areas: বিভাগ (division) -> জেলা (district) -> উপজেলা/থানা (upazila). */
final class GeoRepository
{
    /** @var array{divisions:list<array{id:int,name:string}>,districts:list<array{id:int,parent:int,name:string}>,upazilas:list<array{id:int,parent:int,name:string,short?:string}>}|null */
    private static ?array $cache = null;

    /**
     * Every area, names in Bangla. An upazila name that exists in several districts
     * (e.g. "সদর") is shown with its district: "সদর (বগুড়া জেলা)".
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
     * Area names of one place for display: upazila/thana, district, division (without the word "বিভাগ").
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

    /** True when the upazila belongs to the district and the district to the division. */
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
     * Rows as [id, name_bn] / [id, parent, name_bn]: from the database, or from
     * database/seeds/geo.php when no database is connected (local demo).
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
