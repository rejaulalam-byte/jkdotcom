<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * "এলাকার নাম": সদস্যের এলাকার ছোট নাম (আগারগাঁও, উত্তরা-৫নং সেক্টর, গ্রামের ইউনিয়ন)।
 * শুরুর তালিকা database/seeds/areas.php (ইউনিয়ন + শহরের পরিচিত এলাকা, ফাইলে); তার বাইরের নাম সদস্য নিজে লিখলে
 * area_names_extra-তে 'pending' হয়ে বসে, অ্যাডমিন যাচাই করে 'approved' করলে সবার সাজেশনে আসে (অ্যাডমিনের "এলাকার নাম" পেইজ)।
 * ডাটাবেস না থাকলে (লোকাল প্রিভিউ) শুধু ফাইলের তালিকা চলে।
 */
final class AreaRepository
{
    /** @var array{unions:list<array{0:int,1:string}>,places:list<array{0:int,1:string}>}|null */
    private static ?array $seed = null;

    /** @var array<string,true>|null সব জানা নামের তুলনার-চাবি */
    private static ?array $known = null;

    /** @return array{unions:list<array{0:int,1:string}>,places:list<array{0:int,1:string}>} */
    private static function seed(): array
    {
        return self::$seed ??= require BASE_PATH . '/database/seeds/areas.php';
    }

    /** তুলনার জন্য: ফাঁক, হাইফেন ও ছোট-বড় তফাত বাদ ("মিরপুর-১০" = "মিরপুর ১০")। */
    public static function key(string $name): string
    {
        return mb_strtolower((string) preg_replace('/[\s\-‐‑–—_.,]+/u', '', $name));
    }

    /** ফর্ম থেকে আসা নাম পরিষ্কার: বাড়তি ফাঁক বাদ, সর্বোচ্চ ১০০ অক্ষর। */
    public static function clean(mixed $value): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        return mb_substr($name, 0, 100);
    }

    /**
     * সাজেশনের তালিকা: জেলার আইডি => [[নাম, উপজেলার আইডি (০ = পুরো জেলা)], ...]।
     *
     * @return array<int,list<array{0:string,1:int}>>
     */
    public static function suggestions(): array
    {
        $district = [];
        foreach (GeoRepository::all()['upazilas'] as $u) {
            $district[$u['id']] = $u['parent'];
        }
        $out = [];
        foreach (self::seed()['unions'] as [$upazilaId, $name]) {
            $out[$district[$upazilaId] ?? 0][] = [$name, $upazilaId];
        }
        foreach (self::seed()['places'] as [$districtId, $name]) {
            $out[$districtId][] = [$name, 0];
        }
        foreach (self::approvedExtras() as $r) {
            $d = $r['district_id'] > 0 ? $r['district_id'] : ($district[$r['upazila_id']] ?? 0);
            $out[$d][] = [$r['name'], $r['upazila_id']];
        }

        return $out;
    }

    /** নামটা কি শুরুর তালিকায় বা অনুমোদিত অতিরিক্ত তালিকায় আছে। */
    public static function isKnown(string $name): bool
    {
        if (self::$known === null) {
            self::$known = [];
            foreach (self::seed()['unions'] as [, $n]) {
                self::$known[self::key($n)] = true;
            }
            foreach (self::seed()['places'] as [, $n]) {
                self::$known[self::key($n)] = true;
            }
            foreach (self::approvedExtras() as $r) {
                self::$known[self::key($r['name'])] = true;
            }
        }

        return isset(self::$known[self::key($name)]);
    }

    /**
     * সদস্য তালিকার বাইরের নাম লিখলে সেটা অ্যাডমিনের যাচাইয়ের জন্য রাখে (একই নাম আগে থাকলে কিছু করে না)।
     */
    public static function noteTyped(string $name, int $upazilaId, int $districtId): void
    {
        $name = self::clean($name);
        if ($name === '' || self::isKnown($name)) {
            return;
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            return;
        }
        try {
            $find = $pdo->prepare('SELECT id FROM area_names_extra WHERE name = :n LIMIT 1');
            $find->execute([':n' => $name]);
            if ($find->fetchColumn() !== false) {
                return;
            }
            $pdo->prepare('INSERT INTO area_names_extra (name, upazila_id, district_id, status, created_at) VALUES (:n, :u, :d, \'pending\', NOW())')
                ->execute([':n' => $name, ':u' => $upazilaId > 0 ? $upazilaId : null, ':d' => $districtId > 0 ? $districtId : null]);
        } catch (Throwable $e) {
            Logger::error('AreaRepository::noteTyped failed: ' . $e->getMessage());
        }
    }

    /** @return list<array{id:int,name:string,upazila_id:int,district_id:int,status:string,created_at:string}> */
    private static function extras(string $where): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return [];
        }
        try {
            $rows = $pdo->query('SELECT id, name, upazila_id, district_id, status, created_at FROM area_names_extra' . $where . ' ORDER BY name ASC, id ASC')->fetchAll();
        } catch (Throwable $e) {
            Logger::error('AreaRepository::extras failed: ' . $e->getMessage());

            return [];
        }

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'upazila_id' => (int) $r['upazila_id'], 'district_id' => (int) $r['district_id'],
            'status' => (string) $r['status'], 'created_at' => (string) $r['created_at'],
        ], $rows);
    }

    /** @return list<array{id:int,name:string,upazila_id:int,district_id:int,status:string,created_at:string}> */
    public static function approvedExtras(): array
    {
        return self::extras(" WHERE status = 'approved'");
    }

    /**
     * অ্যাডমিনের তালিকা: pending বা approved, প্রতিটির সঙ্গে কতজন সদস্য ওই নাম ব্যবহার করছেন।
     *
     * @return list<array{id:int,name:string,upazila_id:int,district_id:int,status:string,created_at:string,users:int}>
     */
    public static function listFor(string $status): array
    {
        $rows = self::extras($status === 'approved' ? " WHERE status = 'approved'" : " WHERE status = 'pending'");
        $pdo = Database::connection();
        if ($pdo === null || $rows === []) {
            return array_map(static fn (array $r): array => $r + ['users' => 0], $rows);
        }
        $count = $pdo->prepare('SELECT COUNT(*) FROM users WHERE area_name = :n');
        foreach ($rows as &$r) {
            $count->execute([':n' => $r['name']]);
            $r['users'] = (int) $count->fetchColumn();
        }
        unset($r);

        return $rows;
    }

    /** @return int যাচাইয়ের অপেক্ষার নামের সংখ্যা (অ্যাডমিন ড্যাশবোর্ড/মেনুর জন্য) */
    public static function pendingCount(): int
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return 0;
        }
        try {
            return (int) $pdo->query("SELECT COUNT(*) FROM area_names_extra WHERE status = 'pending'")->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    public static function approve(int $id): bool
    {
        return self::run("UPDATE area_names_extra SET status = 'approved' WHERE id = :id", [':id' => $id]);
    }

    /** নাম ঠিক করে অনুমোদন; যে সদস্যরা পুরনো বানানে লিখেছিলেন তাঁদের নামও বদলে যায়। */
    public static function rename(int $id, string $newName): bool
    {
        $newName = self::clean($newName);
        $pdo = Database::connection();
        if ($pdo === null || $newName === '') {
            return false;
        }
        try {
            $old = $pdo->prepare('SELECT name FROM area_names_extra WHERE id = :id');
            $old->execute([':id' => $id]);
            $oldName = $old->fetchColumn();
            if ($oldName === false) {
                return false;
            }
            $pdo->prepare("UPDATE area_names_extra SET name = :n, status = 'approved' WHERE id = :id")->execute([':n' => $newName, ':id' => $id]);
            $pdo->prepare('UPDATE users SET area_name = :n WHERE area_name = :o')->execute([':n' => $newName, ':o' => $oldName]);

            return true;
        } catch (Throwable $e) {
            Logger::error('AreaRepository::rename failed: ' . $e->getMessage());

            return false;
        }
    }

    /** তালিকা থেকে বাদ; সদস্যদের লেখা নাম যেমন আছে তেমনই থাকে। */
    public static function delete(int $id): bool
    {
        return self::run('DELETE FROM area_names_extra WHERE id = :id', [':id' => $id]);
    }

    /** অ্যাডমিন নিজে নতুন নাম যোগ করলে সরাসরি 'approved'। */
    public static function add(string $name, int $upazilaId, int $districtId): bool
    {
        $name = self::clean($name);
        if ($name === '' || self::isKnown($name)) {
            return false;
        }

        return self::run(
            "INSERT INTO area_names_extra (name, upazila_id, district_id, status, created_at) VALUES (:n, :u, :d, 'approved', NOW())",
            [':n' => $name, ':u' => $upazilaId > 0 ? $upazilaId : null, ':d' => $districtId > 0 ? $districtId : null]
        );
    }

    /** @param array<string,mixed> $args */
    private static function run(string $sql, array $args): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return false;
        }
        try {
            $pdo->prepare($sql)->execute($args);
            self::$known = null;

            return true;
        } catch (Throwable $e) {
            Logger::error('AreaRepository::run failed: ' . $e->getMessage());

            return false;
        }
    }
}
