<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** পেশাজীবী কর্মী (role 'worker') ও প্রতিনিধির (role 'agent') অ্যাকাউন্ট। */
final class UserRepository
{
    /** ডাটাবেস না থাকলে (লোকাল প্রিভিউ) অ্যাকাউন্ট এই ফাইলে থাকে; সেখানে নতুন আবেদন সঙ্গে সঙ্গে 'approved' হয়। */
    private const DEMO_FILE = '/storage/demo-users.json';

    /**
     * নতুন আবেদন সংরক্ষণ করে; আসল সাইটে status = 'pending' (অ্যাডমিন অনুমোদন দিলে সক্রিয়)।
     * মোবাইল আগেই থাকলে 'duplicate', অন্য সমস্যায় 'error', সফল হলে 'ok' ফেরত দেয়।
     *
     * @param array<string,mixed> $data role, name, gender, phone, email, password_hash, address, upazila_id, district_id, division_id,
     *                                  consent_at, photo, এবং কর্মীর জন্য service_slug, experience_years, bio, work_district_id, reference_1/2
     *                                  / প্রতিনিধির জন্য nid_number
     */
    public static function create(array $data): string
    {
        if (self::findByPhone((string) $data['phone'], (string) $data['role']) !== null) {
            return 'duplicate';
        }
        if (self::findByEmail((string) $data['email'], (string) $data['role']) !== null) {
            return 'duplicate_email';
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoAdd($data) ? 'ok' : 'error';
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (role, status, name, gender, phone, email, password_hash, service_slug, service_custom, experience_years, bio, nid_number,
                     address, upazila_id, district_id, division_id, work_all_districts, reference_1, reference_2, photo, ip_hash, consent_at, created_at)
                 VALUES
                    (:role, 'pending', :name, :gender, :phone, :email, :hash, :service, :custom, :experience, :bio, :nid,
                     :address, :upazila, :district, :division, :work_all, :ref1, :ref2, :photo, :ip_hash, :consent_at, NOW())"
            );
            $stmt->execute([
                ':role' => $data['role'],
                ':name' => $data['name'],
                ':gender' => $data['gender'] !== '' ? $data['gender'] : null,
                ':phone' => $data['phone'],
                ':email' => $data['email'],
                ':hash' => $data['password_hash'],
                ':service' => $data['service_slug'] ?? null,
                ':custom' => $data['service_custom'] ?? null,
                ':experience' => $data['experience_years'] ?? null,
                ':bio' => ($data['bio'] ?? '') !== '' ? $data['bio'] : null,
                ':nid' => $data['nid_number'] ?? null,
                ':address' => $data['address'],
                ':upazila' => $data['upazila_id'],
                ':district' => $data['district_id'],
                ':division' => $data['division_id'],
                ':work_all' => (int) ($data['work_all_districts'] ?? 0),
                ':ref1' => ((string) ($data['reference_1'] ?? '')) !== '' ? $data['reference_1'] : null,
                ':ref2' => ((string) ($data['reference_2'] ?? '')) !== '' ? $data['reference_2'] : null,
                ':photo' => $data['photo'] ?? null,
                ':ip_hash' => Security::ipHash(),
                ':consent_at' => $data['consent_at'] ?? null,
            ]);
            $newId = (int) $pdo->lastInsertId();
            self::saveWorkDistricts($pdo, $newId, (array) ($data['work_district_ids'] ?? []));
            self::saveExtraServices($pdo, $newId, (array) ($data['extra_services'] ?? []));

            return 'ok';
        } catch (Throwable $e) {
            // একই পাশে একই মোবাইল/ইমেইলে দুটো আবেদন একসঙ্গে এলে ইউনিক-কী আটকায়
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return str_contains($e->getMessage(), 'uq_users_role_email') ? 'duplicate_email' : 'duplicate';
            }
            Logger::error('UserRepository::create failed: ' . $e->getMessage());

            return 'error';
        }
    }

    /**
     * মোবাইল নাম্বার দিয়ে অ্যাকাউন্ট খোঁজে (লগইন ও দ্বিতীয়বার-নিবন্ধন আটকাতে)। $role দিলে শুধু সেই পাশের (worker|agent) অ্যাকাউন্ট।
     *
     * @return array<string,mixed>|null
     */
    public static function findByPhone(string $phone, ?string $role = null): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                if ((string) $row['phone'] === $phone && ($role === null || (string) $row['role'] === $role)) {
                    return $row;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = :phone' . ($role !== null ? ' AND role = :role' : '') . ' LIMIT 1');
            $stmt->execute([':phone' => $phone] + ($role !== null ? [':role' => $role] : []));
            $row = $stmt->fetch();

            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Logger::error('UserRepository::findByPhone failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * ইমেইল দিয়ে অ্যাকাউন্ট খোঁজে (বড়-ছোট হাতের পার্থক্য ছাড়া)।
     *
     * @return array<string,mixed>|null
     */
    public static function findByEmail(string $email, ?string $role = null): ?array
    {
        $email = mb_strtolower(trim($email));
        if ($email === '') {
            return null;
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                if (mb_strtolower((string) ($row['email'] ?? '')) === $email && ($role === null || (string) $row['role'] === $role)) {
                    return $row;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email' . ($role !== null ? ' AND role = :role' : '') . ' LIMIT 1');
            $stmt->execute([':email' => $email] + ($role !== null ? [':role' => $role] : []));
            $row = $stmt->fetch();

            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Logger::error('UserRepository::findByEmail failed: ' . $e->getMessage());

            return null;
        }
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                if ((int) $row['id'] === $id) {
                    return $row;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Logger::error('UserRepository::findById failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * একটা সেবার অনুমোদিত সব কর্মী, নামের বর্ণানুক্রমে (সেবার তালিকা-পেইজ /services/<slug>)।
     * এলাকার নাম আগেই বসানো থাকে, যাতে খোঁজা ও দেখানো এক জায়গায় হয়।
     *
     * @return list<array{id:int,name:string,phone:string,address:string,upazila:string,district:string,division:string,work_all:bool,work_districts:list<string>}>
     */
    public static function listWorkersByService(string $slug): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(
                self::demoRead(),
                static fn (array $r): bool => ($r['role'] ?? '') === 'worker' && ($r['status'] ?? '') === 'approved'
                    && ((string) ($r['service_slug'] ?? '') === $slug || in_array($slug, (array) ($r['extra_services'] ?? []), true))
            ));
        } else {
            try {
                $stmt = $pdo->prepare(
                    "SELECT id, name, phone, address, upazila_id, district_id, division_id, work_all_districts FROM users
                     WHERE role = 'worker' AND status = 'approved'
                       AND (service_slug = :slug OR id IN (SELECT user_id FROM worker_services WHERE service_slug = :slug2))
                     ORDER BY name ASC, id ASC"
                );
                $stmt->execute([':slug' => $slug, ':slug2' => $slug]);
                $rows = $stmt->fetchAll();
            } catch (Throwable $e) {
                Logger::error('UserRepository::listWorkersByService failed: ' . $e->getMessage());

                return [];
            }
        }

        $work = self::workDistrictsOf($pdo, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $list = [];
        foreach ($rows as $r) {
            $geo = GeoRepository::names((int) $r['upazila_id'], (int) $r['district_id'], (int) $r['division_id']);
            $ids = $pdo === null ? array_map('intval', (array) ($r['work_district_ids'] ?? [])) : ($work[(int) $r['id']] ?? []);
            $list[] = [
                'id' => (int) $r['id'],
                'name' => (string) $r['name'],
                'phone' => (string) $r['phone'],
                'address' => (string) $r['address'],
                'work_all' => !empty($r['work_all_districts']),
                'work_districts' => GeoRepository::workDistrictNames($ids),
            ] + $geo;
        }
        usort($list, static fn (array $a, array $b): int => strcmp($a['name'], $b['name']));

        return $list;
    }

    /**
     * কয়েকজন কর্মীর কাজের জেলার id (কর্মীর id => জেলার id-তালিকা)। ডাটাবেস না থাকলে ফাঁকা।
     *
     * @param list<int> $userIds
     * @return array<int,list<int>>
     */
    private static function workDistrictsOf(?\PDO $pdo, array $userIds): array
    {
        if ($pdo === null || $userIds === []) {
            return [];
        }

        try {
            $in = implode(',', array_fill(0, count($userIds), '?'));
            $stmt = $pdo->prepare("SELECT user_id, district_id FROM worker_work_districts WHERE user_id IN ($in) ORDER BY district_id");
            $stmt->execute(array_values($userIds));
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int) $row['user_id']][] = (int) $row['district_id'];
            }

            return $out;
        } catch (Throwable $e) {
            Logger::error('UserRepository::workDistrictsOf failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * সর্বজনীনভাবে দেখানো যায় এমন কর্মী: শুধু 'approved' কর্মী (প্রতিনিধি বা অপেক্ষমাণ নয়)।
     *
     * @return array<string,mixed>|null
     */
    public static function findPublicWorker(int $id): ?array
    {
        $row = self::findById($id);

        return $row !== null && ($row['role'] ?? '') === 'worker' && ($row['status'] ?? '') === 'approved' ? $row : null;
    }

    /**
     * কর্মীর "কাজ করতে আগ্রহী" জেলাগুলো নতুন করে বসায় (আগেরগুলো মুছে)।
     *
     * @param list<int> $ids
     */
    private static function saveWorkDistricts(\PDO $pdo, int $userId, array $ids): void
    {
        $pdo->prepare('DELETE FROM worker_work_districts WHERE user_id = :u')->execute([':u' => $userId]);
        $insert = $pdo->prepare('INSERT IGNORE INTO worker_work_districts (user_id, district_id) VALUES (:u, :d)');
        foreach (array_slice($ids, 0, GeoRepository::MAX_WORK_DISTRICTS) as $districtId) {
            $insert->execute([':u' => $userId, ':d' => (int) $districtId]);
        }
    }

    /**
     * কর্মীর "আরও পেশা" নতুন করে বসায় (আগেরগুলো মুছে)।
     *
     * @param list<string> $slugs
     */
    private static function saveExtraServices(\PDO $pdo, int $userId, array $slugs): void
    {
        $pdo->prepare('DELETE FROM worker_services WHERE user_id = :u')->execute([':u' => $userId]);
        $insert = $pdo->prepare('INSERT IGNORE INTO worker_services (user_id, service_slug) VALUES (:u, :s)');
        foreach (array_slice($slugs, 0, ServiceRepository::MAX_EXTRA) as $slug) {
            $insert->execute([':u' => $userId, ':s' => (string) $slug]);
        }
    }

    /**
     * একজন কর্মীর "আরও পেশা"-র slug-গুলো (প্রধান পেশা বাদে)।
     *
     * @param array<string,mixed> $user users-এর সারি
     * @return list<string>
     */
    public static function extraServices(array $user): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return array_map('strval', (array) ($user['extra_services'] ?? []));
        }

        try {
            $stmt = $pdo->prepare('SELECT service_slug FROM worker_services WHERE user_id = :u ORDER BY service_slug');
            $stmt->execute([':u' => (int) $user['id']]);

            return array_map('strval', array_column($stmt->fetchAll(), 'service_slug'));
        } catch (Throwable $e) {
            Logger::error('UserRepository::extraServices failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * একজন কর্মীর কাজের জেলার id-গুলো ও "সব জেলা" চিহ্ন।
     *
     * @param array<string,mixed> $user users-এর সারি
     * @return array{ids:list<int>,all:bool}
     */
    public static function workDistricts(array $user): array
    {
        $all = !empty($user['work_all_districts']);
        $pdo = Database::connection();
        if ($pdo === null) {
            return ['ids' => array_map('intval', (array) ($user['work_district_ids'] ?? [])), 'all' => $all];
        }

        try {
            $stmt = $pdo->prepare('SELECT district_id FROM worker_work_districts WHERE user_id = :u ORDER BY district_id');
            $stmt->execute([':u' => (int) $user['id']]);

            return ['ids' => array_map('intval', array_column($stmt->fetchAll(), 'district_id')), 'all' => $all];
        } catch (Throwable $e) {
            Logger::error('UserRepository::workDistricts failed: ' . $e->getMessage());

            return ['ids' => [], 'all' => $all];
        }
    }

    /** সফল লগইনের সময় লিখে রাখে; পুরনো হ্যাশ দুর্বল হলে নতুন হ্যাশ বসায় (password_needs_rehash)। */
    public static function touchLogin(int $id, ?string $newHash = null): void
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i]['last_login_at'] = date('Y-m-d H:i:s');
                    if ($newHash !== null) {
                        $rows[$i]['password_hash'] = $newHash;
                    }
                }
            }
            self::demoWrite($rows);

            return;
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET last_login_at = NOW(), password_hash = COALESCE(:hash, password_hash) WHERE id = :id');
            $stmt->execute([':hash' => $newHash, ':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('UserRepository::touchLogin failed: ' . $e->getMessage());
        }
    }

    /**
     * সদস্যের নিজে-বদলানো যায় এমন তথ্য সংরক্ষণ করে (নাম, ইমেইল, জেন্ডার, NID কখনও নয়)।
     * মোবাইল অন্য কারও হলে 'duplicate', সফল হলে 'ok', নইলে 'error'। 'photo' কী না থাকলে ছবি অপরিবর্তিত।
     *
     * @param array<string,mixed> $data phone, address, upazila_id, district_id, division_id, ঐচ্ছিক photo,
     *                                  কর্মীর জন্য service_slug, service_custom, experience_years, bio
     */
    public static function updateProfile(int $id, array $data): string
    {
        $role = (string) (self::findById($id)['role'] ?? '');
        $other = self::findByPhone((string) $data['phone'], $role);
        if ($other !== null && (int) $other['id'] !== $id) {
            return 'duplicate';
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i] = array_merge($row, array_intersect_key($data, array_flip([
                        'phone', 'address', 'upazila_id', 'district_id', 'division_id', 'photo',
                        'service_slug', 'service_custom', 'experience_years', 'bio', 'work_district_ids', 'work_all_districts',
                        'reference_1', 'reference_2', 'extra_services',
                    ])));
                }
            }

            return self::demoWrite($rows) ? 'ok' : 'error';
        }

        $sets = ['phone = :phone', 'address = :address', 'upazila_id = :upazila', 'district_id = :district', 'division_id = :division'];
        $args = [
            ':id' => $id, ':phone' => $data['phone'], ':address' => $data['address'],
            ':upazila' => $data['upazila_id'], ':district' => $data['district_id'], ':division' => $data['division_id'],
        ];
        if (array_key_exists('service_slug', $data)) {
            $sets[] = 'service_slug = :service';
            $sets[] = 'service_custom = :custom';
            $sets[] = 'experience_years = :experience';
            $sets[] = 'bio = :bio';
            $sets[] = 'work_all_districts = :work_all';
            $sets[] = 'reference_1 = :ref1';
            $sets[] = 'reference_2 = :ref2';
            $args[':ref1'] = $data['reference_1'];
            $args[':ref2'] = $data['reference_2'];
            $args[':work_all'] = (int) ($data['work_all_districts'] ?? 0);
            $args += [
                ':service' => $data['service_slug'], ':custom' => $data['service_custom'],
                ':experience' => $data['experience_years'], ':bio' => ($data['bio'] ?? '') !== '' ? $data['bio'] : null,
            ];
        }
        if (array_key_exists('photo', $data)) {
            $sets[] = 'photo = :photo';
            $args[':photo'] = $data['photo'];
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $sets) . ' WHERE id = :id');
            $stmt->execute($args);
            if (array_key_exists('work_district_ids', $data)) {
                self::saveWorkDistricts($pdo, $id, (array) $data['work_district_ids']);
            }
            if (array_key_exists('extra_services', $data)) {
                self::saveExtraServices($pdo, $id, (array) $data['extra_services']);
            }

            return 'ok';
        } catch (Throwable $e) {
            // একই মোবাইলে একসঙ্গে দুটো বদল এলে ইউনিক-কী আটকায়
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicate';
            }
            Logger::error('UserRepository::updateProfile failed: ' . $e->getMessage());

            return 'error';
        }
    }

    /** নতুন পাসওয়ার্ড-হ্যাশ বসায় এবং "পাসওয়ার্ড বদলাতে হবে" চিহ্ন মুছে দেয়। সফল হলে true। */
    public static function changePassword(int $id, string $hash): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i]['password_hash'] = $hash;
                    $rows[$i]['must_change_password'] = 0;
                }
            }

            return self::demoWrite($rows);
        }

        try {
            $stmt = $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0 WHERE id = :id');

            return $stmt->execute([':hash' => $hash, ':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('UserRepository::changePassword failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    private static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function demoWrite(array $rows): bool
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }

        return @file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }

    /** @param array<string,mixed> $data */
    private static function demoAdd(array $data): bool
    {
        $rows = self::demoRead();
        $rows[] = $data + [
            'id' => count($rows) + 1,
            'status' => 'approved',
            'must_change_password' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'last_login_at' => null,
        ];

        return self::demoWrite($rows);
    }
}
