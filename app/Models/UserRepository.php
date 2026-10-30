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
        // আগে থেকে থাকা সদস্য পুরনো সাইট থেকে আনা হলে (legacy_id) আলাদা বার্তা: তাঁকে হেল্পলাইনে ফোন করতে বলা হয়
        $byPhone = self::findByPhone((string) $data['phone'], (string) $data['role']);
        if ($byPhone !== null) {
            return (string) ($byPhone['legacy_id'] ?? '') !== '' ? 'duplicate_legacy' : 'duplicate';
        }
        $byEmail = self::findByEmail((string) $data['email'], (string) $data['role']);
        if ($byEmail !== null) {
            return (string) ($byEmail['legacy_id'] ?? '') !== '' ? 'duplicate_email_legacy' : 'duplicate_email';
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoAdd($data) ? 'ok' : 'error';
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (role, status, name, gender, phone, email, password_hash, must_change_password, service_slug, service_custom, experience_years, bio, nid_number,
                     address, upazila_id, district_id, division_id, work_all_districts, reference_1, reference_2, photo, ip_hash, consent_at, created_at)
                 VALUES
                    (:role, 'pending', :name, :gender, :phone, :email, :hash, :must_change, :service, :custom, :experience, :bio, :nid,
                     :address, :upazila, :district, :division, :work_all, :ref1, :ref2, :photo, :ip_hash, :consent_at, NOW())"
            );
            $stmt->execute([
                ':role' => $data['role'],
                ':name' => $data['name'],
                ':gender' => $data['gender'] !== '' ? $data['gender'] : null,
                ':phone' => $data['phone'],
                ':email' => (string) ($data['email'] ?? '') !== '' ? $data['email'] : null,
                ':hash' => $data['password_hash'],
                ':must_change' => (int) ($data['must_change_password'] ?? 0),
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
        return self::workerList($slug);
    }

    /**
     * অনুমোদিত সব কর্মী (সাইট-জুড়ে খোঁজার জন্য, /search); প্রতিজনের মূল পেশার নামও থাকে।
     *
     * @return list<array{id:int,name:string,phone:string,address:string,upazila:string,district:string,division:string,work_all:bool,work_districts:list<string>,profession:string}>
     */
    public static function listApprovedWorkers(): array
    {
        return self::workerList(null);
    }

    /**
     * @param string|null $slug null = সব সেবার কর্মী
     * @return list<array<string,mixed>>
     */
    private static function workerList(?string $slug): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(
                self::demoRead(),
                static fn (array $r): bool => ($r['role'] ?? '') === 'worker' && ($r['status'] ?? '') === 'approved'
                    && ($slug === null || (string) ($r['service_slug'] ?? '') === $slug || in_array($slug, (array) ($r['extra_services'] ?? []), true))
            ));
        } else {
            try {
                $sql = "SELECT id, name, phone, address, upazila_id, district_id, division_id, work_all_districts, service_slug, service_custom FROM users
                        WHERE role = 'worker' AND status = 'approved'"
                    . ($slug === null ? '' : ' AND (service_slug = :slug OR id IN (SELECT user_id FROM worker_services WHERE service_slug = :slug2))')
                    . ' ORDER BY name ASC, id ASC';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($slug === null ? [] : [':slug' => $slug, ':slug2' => $slug]);
                $rows = $stmt->fetchAll();
            } catch (Throwable $e) {
                Logger::error('UserRepository::workerList failed: ' . $e->getMessage());

                return [];
            }
        }

        $work = self::workDistrictsOf($pdo, array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $serviceNames = array_column(ServiceRepository::all(), 'name', 'slug');
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
                'profession' => ((string) ($r['service_slug'] ?? '')) !== '' ? ($serviceNames[(string) $r['service_slug']] ?? '') : (string) ($r['service_custom'] ?? ''),
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

    /**
     * পুরনো সাইট থেকে আনা কর্মী (legacy_id আছে) কি প্রথম লগইনের "তথ্য পূর্ণ করুন" ধাপ বাকি রেখেছেন।
     * নতুন রেজিস্ট্রেশনের সদস্যের ক্ষেত্রে সবসময় false।
     *
     * @param array<string,mixed> $user
     */
    public static function needsSetup(array $user): bool
    {
        return (int) ($user['must_change_password'] ?? 0) === 1 && (string) ($user['legacy_id'] ?? '') !== '';
    }

    /**
     * প্রথম লগইনের তথ্য পূর্ণ করা: নতুন পাসওয়ার্ড, ইমেইল, ঠিকানা, সম্মতি (এবং মোবাইল না থাকলে মোবাইল)।
     * 'ok', 'duplicate' (মোবাইল), 'duplicate_email' বা 'error' ফেরত দেয়।
     *
     * @param array<string,mixed> $data password_hash, email, address, gender, upazila_id, district_id, division_id, reference_1, reference_2, এবং ঐচ্ছিক phone
     */
    public static function completeSetup(int $id, array $data): string
    {
        $user = self::findById($id);
        if ($user === null) {
            return 'error';
        }
        $role = (string) $user['role'];
        $other = self::findByEmail((string) $data['email'], $role);
        if ($other !== null && (int) $other['id'] !== $id) {
            return 'duplicate_email';
        }
        $hasPhone = isset($data['phone']) && $data['phone'] !== '';
        if ($hasPhone) {
            $other = self::findByPhone((string) $data['phone'], $role);
            if ($other !== null && (int) $other['id'] !== $id) {
                return 'duplicate';
            }
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i] = array_merge($row, [
                        'password_hash' => $data['password_hash'], 'must_change_password' => 0,
                        'email' => $data['email'], 'address' => $data['address'], 'consent_at' => date('Y-m-d H:i:s'),
                        'gender' => $data['gender'], 'upazila_id' => $data['upazila_id'], 'district_id' => $data['district_id'],
                        'division_id' => $data['division_id'], 'reference_1' => $data['reference_1'], 'reference_2' => $data['reference_2'],
                    ], $hasPhone ? ['phone' => $data['phone'], 'phone_raw' => null] : []);
                }
            }

            return self::demoWrite($rows) ? 'ok' : 'error';
        }

        try {
            $sets = 'password_hash = :hash, must_change_password = 0, email = :email, address = :address, consent_at = NOW(),
                gender = :gender, upazila_id = :upz, district_id = :dist, division_id = :div, reference_1 = :ref1, reference_2 = :ref2'
                . ($hasPhone ? ', phone = :phone, phone_raw = NULL' : '');
            $args = [':id' => $id, ':hash' => $data['password_hash'], ':email' => $data['email'], ':address' => $data['address'],
                ':gender' => $data['gender'], ':upz' => $data['upazila_id'], ':dist' => $data['district_id'], ':div' => $data['division_id'],
                ':ref1' => $data['reference_1'] !== '' ? $data['reference_1'] : null, ':ref2' => $data['reference_2'] !== '' ? $data['reference_2'] : null]
                + ($hasPhone ? [':phone' => $data['phone']] : []);
            $pdo->prepare("UPDATE users SET $sets WHERE id = :id")->execute($args);

            return 'ok';
        } catch (Throwable $e) {
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return str_contains($e->getMessage(), 'uq_users_role_email') ? 'duplicate_email' : 'duplicate';
            }
            Logger::error('UserRepository::completeSetup failed: ' . $e->getMessage());

            return 'error';
        }
    }

    /**
     * অ্যাডমিন একজন কর্মী/প্রতিনিধির তথ্য বদলায়। $data-তে যে কী আছে সেটাই বদলায়:
     * name, gender, phone, email, address, upazila_id, district_id, division_id, photo (ফাইলের নাম বা null = মুছে ফেলা),
     * কর্মীর: service_slug, service_custom, experience_years, bio, reference_1/2, work_all_districts, work_district_ids, extra_services;
     * প্রতিনিধির: nid_number। খালি মোবাইল/ইমেইল/জেন্ডার NULL হয়; মোবাইল বসালে phone_raw মুছে যায়।
     * 'ok', 'duplicate' (মোবাইল), 'duplicate_email' বা 'error' ফেরত দেয়।
     *
     * @param array<string,mixed> $data
     */
    public static function adminUpdate(int $id, array $data): string
    {
        $member = self::findById($id);
        if ($member === null) {
            return 'error';
        }
        $role = (string) $member['role'];
        $phone = (string) ($data['phone'] ?? '');
        $email = mb_strtolower((string) ($data['email'] ?? ''));
        if ($phone !== '') {
            $other = self::findByPhone($phone, $role);
            if ($other !== null && (int) $other['id'] !== $id) {
                return 'duplicate';
            }
        }
        if ($email !== '') {
            $other = self::findByEmail($email, $role);
            if ($other !== null && (int) $other['id'] !== $id) {
                return 'duplicate_email';
            }
        }

        $columns = [
            'name' => $data['name'],
            'gender' => (string) ($data['gender'] ?? '') !== '' ? $data['gender'] : null,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
            'address' => $data['address'],
            'upazila_id' => $data['upazila_id'],
            'district_id' => $data['district_id'],
            'division_id' => $data['division_id'],
        ];
        if ($phone !== '') {
            $columns['phone_raw'] = null;
        }
        if (array_key_exists('photo', $data)) {
            $columns['photo'] = $data['photo'];
        }
        if ($role === 'worker') {
            $columns += [
                'service_slug' => $data['service_slug'], 'service_custom' => $data['service_custom'],
                'experience_years' => $data['experience_years'], 'bio' => (string) ($data['bio'] ?? '') !== '' ? $data['bio'] : null,
                'reference_1' => (string) ($data['reference_1'] ?? '') !== '' ? $data['reference_1'] : null,
                'reference_2' => (string) ($data['reference_2'] ?? '') !== '' ? $data['reference_2'] : null,
                'work_all_districts' => (int) ($data['work_all_districts'] ?? 0),
            ];
        } else {
            $columns['nid_number'] = (string) ($data['nid_number'] ?? '') !== '' ? $data['nid_number'] : null;
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i] = array_merge($row, $columns, $role === 'worker'
                        ? ['work_district_ids' => (array) ($data['work_district_ids'] ?? []), 'extra_services' => (array) ($data['extra_services'] ?? [])]
                        : []);
                }
            }

            return self::demoWrite($rows) ? 'ok' : 'error';
        }

        try {
            $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($columns)));
            $pdo->prepare("UPDATE users SET $sets WHERE id = :id")->execute($columns + ['id' => $id]);
            if ($role === 'worker') {
                self::saveWorkDistricts($pdo, $id, (array) ($data['work_district_ids'] ?? []));
                self::saveExtraServices($pdo, $id, (array) ($data['extra_services'] ?? []));
            }

            return 'ok';
        } catch (Throwable $e) {
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return str_contains($e->getMessage(), 'uq_users_role_email') ? 'duplicate_email' : 'duplicate';
            }
            Logger::error('UserRepository::adminUpdate failed: ' . $e->getMessage());

            return 'error';
        }
    }

    /** অ্যাডমিন তালিকার "সব"-এ যে অবস্থাগুলো আসে (সংরক্ষিত ও ডাস্টবিন আলাদা ট্যাবে)। */
    public const LISTED_STATUSES = ['pending', 'approved', 'suspended', 'rejected'];

    /** সংরক্ষিত ও ডাস্টবিনে সদস্য এত দিন (দিন) থাকে, তারপর পরের ধাপে যায়। */
    public const RESERVE_DAYS = 30;

    /**
     * সদস্যের অবস্থা বদলায়। 'approved' হলে অনুমোদনের সময় লেখা হয়; 'reserved'/'trashed' হলে আগের অবস্থা মনে রাখা হয় (ফিরিয়ে আনতে)।
     * $note হোল্ড/বাতিলের কারণ (ঐচ্ছিক)। সফল হলে true।
     */
    public static function setStatus(int $id, string $status, ?string $note = null): bool
    {
        $member = self::findById($id);
        if ($member === null) {
            return false;
        }
        $previous = in_array($status, ['reserved', 'trashed'], true) && !in_array($member['status'], ['reserved', 'trashed'], true)
            ? (string) $member['status']
            : (string) ($member['previous_status'] ?? '');
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 255);

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i]['status'] = $status;
                    $rows[$i]['status_note'] = $note;
                    $rows[$i]['previous_status'] = $previous !== '' ? $previous : null;
                    $rows[$i]['status_changed_at'] = date('Y-m-d H:i:s');
                    if ($status === 'approved') {
                        $rows[$i]['approved_at'] = date('Y-m-d H:i:s');
                    } elseif ($status === 'pending') {
                        $rows[$i]['approved_at'] = null;
                    }
                }
            }

            return self::demoWrite($rows);
        }

        try {
            $pdo->prepare(
                'UPDATE users SET status = :s, status_note = :n, previous_status = :p, status_changed_at = NOW()'
                . ($status === 'approved' ? ', approved_at = NOW()' : ($status === 'pending' ? ', approved_at = NULL' : '')) . ' WHERE id = :id'
            )->execute([':s' => $status, ':n' => $note, ':p' => $previous !== '' ? $previous : null, ':id' => $id]);

            return true;
        } catch (Throwable $e) {
            Logger::error('UserRepository::setStatus failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * ৩০ দিন পুরনো সংরক্ষিতকে ডাস্টবিনে পাঠায়, ৩০ দিন পুরনো ডাস্টবিনের সদস্যকে চিরতরে মোছে।
     * (আলাদা সময়-ভিত্তিক কাজ নেই; অ্যাডমিন প্যানেলের তালিকা খুললে চলে।)
     *
     * @return array{trashed:int,purged:int}
     */
    public static function sweep(): array
    {
        $out = ['trashed' => 0, 'purged' => 0];
        $limit = time() - self::RESERVE_DAYS * 86400;

        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                $at = strtotime((string) ($row['status_changed_at'] ?? '')) ?: 0;
                if ($row['status'] === 'reserved' && $at > 0 && $at < $limit) {
                    self::setStatus((int) $row['id'], 'trashed');
                    $out['trashed']++;
                } elseif ($row['status'] === 'trashed' && $at > 0 && $at < $limit) {
                    self::purge((int) $row['id']);
                    $out['purged']++;
                }
            }

            return $out;
        }

        try {
            $cut = date('Y-m-d H:i:s', $limit);
            $stmt = $pdo->prepare("SELECT id FROM users WHERE status = 'trashed' AND status_changed_at < :c");
            $stmt->execute([':c' => $cut]);
            foreach ($stmt->fetchAll() as $row) {
                self::purge((int) $row['id']);
                $out['purged']++;
            }
            $upd = $pdo->prepare("UPDATE users SET previous_status = COALESCE(previous_status, 'approved'), status = 'trashed', status_changed_at = NOW() WHERE status = 'reserved' AND status_changed_at < :c");
            $upd->execute([':c' => $cut]);
            $out['trashed'] = $upd->rowCount();
        } catch (Throwable $e) {
            Logger::error('UserRepository::sweep failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * সদস্যকে চিরতরে মোছে: তার পেশা, কাজের জেলা, রেটিং, ঘূর্ণি ক্রমের ও ভিজিটের সারি এবং ছবির ফাইলসহ। ফেরানো যায় না।
     * (দর্শকের "আগ্রহী" তালিকা ও ক্লিকের হিসাব থাকে, কারণ ওগুলো দর্শকদের তথ্য।)
     */
    public static function purge(int $id): bool
    {
        $member = self::findById($id);
        if ($member === null) {
            return false;
        }
        $photo = (string) ($member['photo'] ?? '');

        $pdo = Database::connection();
        if ($pdo === null) {
            $ok = self::demoWrite(array_values(array_filter(self::demoRead(), static fn (array $r): bool => (int) $r['id'] !== $id)));
        } else {
            try {
                foreach (['worker_services' => 'user_id', 'worker_work_districts' => 'user_id', 'worker_rotation' => 'user_id', 'worker_visits' => 'user_id', 'worker_ratings' => 'worker_id'] as $table => $col) {
                    $pdo->prepare("DELETE FROM $table WHERE $col = :id")->execute([':id' => $id]);
                }
                $pdo->prepare('DELETE FROM users WHERE id = :id')->execute([':id' => $id]);
                $ok = true;
            } catch (Throwable $e) {
                Logger::error('UserRepository::purge failed: ' . $e->getMessage());
                $ok = false;
            }
        }
        if ($ok) {
            CustomFields::deleteValuesFor((string) $member['role'], $id);
        }
        if ($ok && $photo !== '' && \App\Core\PhotoUpload::url($photo) !== null) {
            @unlink(\App\Core\PhotoUpload::directory() . '/' . $photo);
        }

        return $ok;
    }

    /**
     * অ্যাডমিনের তালিকা-পেইজ: এক ভূমিকার (worker|agent) সদস্য, নতুন আগে। ফিল্টার:
     * 'status' (খালি = সব), 'q' (নাম, মোবাইল, ইমেইল বা ঠিকানার অংশ), 'source' ('legacy' = পুরনো সাইট থেকে আনা, 'new' = নতুন),
     * 'nophone' (true হলে শুধু মোবাইল-ছাড়া)।
     *
     * @param array{status?:string,q?:string,source?:string,nophone?:bool} $filter
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function adminSearch(string $role, array $filter, int $limit, int $offset): array
    {
        $status = (string) ($filter['status'] ?? '');
        $q = trim((string) ($filter['q'] ?? ''));
        $source = (string) ($filter['source'] ?? '');
        $noPhone = !empty($filter['nophone']);

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(), static function (array $r) use ($role, $status, $q, $source, $noPhone): bool {
                $hay = mb_strtolower(implode(' ', [(string) $r['name'], (string) ($r['phone'] ?? ''), (string) ($r['email'] ?? ''), (string) ($r['address'] ?? '')]));

                return $r['role'] === $role
                    && ($status === '' ? in_array($r['status'], self::LISTED_STATUSES, true) : $r['status'] === $status)
                    && ($q === '' || str_contains($hay, mb_strtolower($q)))
                    && ($source !== 'legacy' || (string) ($r['legacy_id'] ?? '') !== '')
                    && ($source !== 'new' || (string) ($r['legacy_id'] ?? '') === '')
                    && (!$noPhone || (string) ($r['phone'] ?? '') === '');
            }));
            usort($rows, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

            return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
        }

        $where = ['role = :role'];
        $args = [':role' => $role];
        if ($status !== '') {
            $where[] = 'status = :status';
            $args[':status'] = $status;
        } else {
            $where[] = "status IN ('" . implode("','", self::LISTED_STATUSES) . "')";
        }
        if ($q !== '') {
            // সাধারণ (non-emulated) প্রস্তুতিতে একই নামের প্লেসহোল্ডার বারবার চলে না, তাই চারটা আলাদা
            $where[] = '(name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3 OR address LIKE :q4)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $args += [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like];
        }
        if ($source === 'legacy') {
            $where[] = 'legacy_id IS NOT NULL';
        } elseif ($source === 'new') {
            $where[] = 'legacy_id IS NULL';
        }
        if ($noPhone) {
            $where[] = 'phone IS NULL';
        }
        $sqlWhere = implode(' AND ', $where);

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $sqlWhere");
            $count->execute($args);
            $stmt = $pdo->prepare(
                "SELECT id, role, status, name, phone, phone_raw, email, service_slug, service_custom, address, upazila_id, district_id, division_id,
                        legacy_id, created_at FROM users WHERE $sqlWhere ORDER BY id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $stmt->execute($args);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('UserRepository::adminSearch failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * ভূমিকা ও অবস্থা অনুযায়ী সদস্যের সংখ্যা (অ্যাডমিন ড্যাশবোর্ডের জন্য)।
     *
     * @return array<string,array<string,int>>  ['worker' => ['pending' => 3, 'approved' => 900], 'agent' => [...]]
     */
    public static function countByRoleStatus(): array
    {
        $out = ['worker' => [], 'agent' => []];
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                $out[$row['role']][$row['status']] = ($out[$row['role']][$row['status']] ?? 0) + 1;
            }

            return $out;
        }

        try {
            foreach ($pdo->query('SELECT role, status, COUNT(*) AS n FROM users GROUP BY role, status')->fetchAll() as $r) {
                $out[(string) $r['role']][(string) $r['status']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            Logger::error('UserRepository::countByRoleStatus failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * "ফোন করা হয়েছে" টিক দেয় (মেয়াদ এখন থেকে LegacyActivation::DAYS দিন) বা তুলে নেয়।
     * শুধু পুরনো সাইটের কর্মী, যিনি এখনও নিজের পাসওয়ার্ড বসাননি। সফল হলে true।
     */
    public static function markCalled(int $id, bool $called): bool
    {
        $now = date('Y-m-d H:i:s');
        $deadline = date('Y-m-d H:i:s', time() + LegacyActivation::DAYS * 86400);
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $done = false;
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id && (string) ($row['legacy_id'] ?? '') !== '' && (int) ($row['must_change_password'] ?? 0) === 1) {
                    $rows[$i]['phone_called_at'] = $called ? $now : null;
                    $rows[$i]['activation_deadline'] = $called ? $deadline : null;
                    $done = true;
                }
            }

            return $done && self::demoWrite($rows);
        }

        try {
            $stmt = $pdo->prepare(
                'UPDATE users SET phone_called_at = :at, activation_deadline = :dl
                 WHERE id = :id AND legacy_id IS NOT NULL AND must_change_password = 1'
            );
            $stmt->execute([':at' => $called ? $now : null, ':dl' => $called ? $deadline : null, ':id' => $id]);

            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            Logger::error('UserRepository::markCalled failed: ' . $e->getMessage());

            return false;
        }
    }

    /** সক্রিয় করার অবস্থা (LegacyActivation::STATES-এর কী) অনুযায়ী SQL-এর শর্ত। */
    private static function legacyStateSql(string $state): string
    {
        return match ($state) {
            'todo' => 'must_change_password = 1 AND activation_deadline IS NULL',
            'called' => 'must_change_password = 1 AND activation_deadline >= NOW()',
            'expired' => 'must_change_password = 1 AND activation_deadline < NOW()',
            'active' => 'must_change_password = 0',
            default => '1 = 1',
        };
    }

    /**
     * পুরনো সাইট থেকে আনা কর্মীর তালিকা (সংরক্ষিত/ডাস্টবিনের বাইরে), নাম অনুযায়ী। ফিল্টার: 'state' (খালি = সব), 'q' (নাম, মোবাইল বা ইমেইল)।
     *
     * @param array{state?:string,q?:string} $filter
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function legacyList(array $filter, int $limit, int $offset): array
    {
        $state = isset(LegacyActivation::STATES[$filter['state'] ?? '']) ? (string) $filter['state'] : '';
        $q = trim((string) ($filter['q'] ?? ''));

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(), static function (array $r) use ($state, $q): bool {
                $hay = mb_strtolower(implode(' ', [(string) $r['name'], (string) ($r['phone'] ?? ''), (string) ($r['email'] ?? '')]));

                return (string) ($r['legacy_id'] ?? '') !== '' && in_array($r['status'], self::LISTED_STATUSES, true)
                    && ($state === '' || LegacyActivation::state($r) === $state)
                    && ($q === '' || str_contains($hay, mb_strtolower($q)));
            }));
            usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
        }

        $where = ['legacy_id IS NOT NULL', "status IN ('" . implode("','", self::LISTED_STATUSES) . "')", self::legacyStateSql($state)];
        $args = [];
        if ($q !== '') {
            $where[] = '(name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $args = [':q1' => $like, ':q2' => $like, ':q3' => $like];
        }
        $sqlWhere = implode(' AND ', $where);

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $sqlWhere");
            $count->execute($args);
            $stmt = $pdo->prepare(
                "SELECT id, status, name, phone, phone_raw, email, legacy_id, must_change_password, phone_called_at, activation_deadline
                 FROM users WHERE $sqlWhere ORDER BY name, id LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $stmt->execute($args);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('UserRepository::legacyList failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * পুরনো সাইটের কর্মী অবস্থা অনুযায়ী কত জন (সংরক্ষিত/ডাস্টবিনের বাইরে)।
     *
     * @return array<string,int> todo|called|expired|active => সংখ্যা
     */
    public static function legacyCounts(): array
    {
        $out = array_fill_keys(array_keys(LegacyActivation::STATES), 0);
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                $state = LegacyActivation::state($row);
                if ($state !== '' && in_array($row['status'], self::LISTED_STATUSES, true)) {
                    $out[$state]++;
                }
            }

            return $out;
        }

        try {
            foreach (array_keys($out) as $state) {
                $stmt = $pdo->query(
                    "SELECT COUNT(*) FROM users WHERE legacy_id IS NOT NULL AND status IN ('" . implode("','", self::LISTED_STATUSES) . "') AND "
                    . self::legacyStateSql($state)
                );
                $out[$state] = $stmt === false ? 0 : (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            Logger::error('UserRepository::legacyCounts failed: ' . $e->getMessage());
        }

        return $out;
    }
    /**
     * অনুমোদিত কর্মীদের মধ্যে যাদের তথ্য বাকি (IncompleteInfo): $filter খালি = মূল চারটার (মোবাইল, ইমেইল, ঠিকানা, এলাকা) যেকোনোটা,
     * নইলে একটা কী। $q = নাম, মোবাইল বা ইমেইলের অংশ। নাম অনুযায়ী সাজানো।
     *
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function incompleteList(string $filter, string $q, int $limit, int $offset): array
    {
        $filter = isset(IncompleteInfo::LABELS[$filter]) ? $filter : '';
        $q = trim($q);

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(), static function (array $r) use ($filter, $q): bool {
                if ($r['role'] !== 'worker' || $r['status'] !== 'approved') {
                    return false;
                }
                $missing = IncompleteInfo::missing($r);
                $hay = mb_strtolower(implode(' ', [(string) $r['name'], (string) ($r['phone'] ?? ''), (string) ($r['email'] ?? '')]));

                return ($filter === '' ? array_intersect($missing, IncompleteInfo::CORE) !== [] : in_array($filter, $missing, true))
                    && ($q === '' || str_contains($hay, mb_strtolower($q)));
            }));
            usort($rows, static fn (array $a, array $b): int => strcmp((string) $a['name'], (string) $b['name']));

            return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
        }

        $where = ["role = 'worker'", "status = 'approved'", IncompleteInfo::sqlFor($filter)];
        $args = [];
        if ($q !== '') {
            $where[] = '(name LIKE :q1 OR phone LIKE :q2 OR email LIKE :q3)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $args = [':q1' => $like, ':q2' => $like, ':q3' => $like];
        }
        $sqlWhere = implode(' AND ', $where);

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM users WHERE $sqlWhere");
            $count->execute($args);
            $stmt = $pdo->prepare(
                "SELECT id, name, phone, phone_raw, email, address, upazila_id, experience_years, bio, legacy_id
                 FROM users WHERE $sqlWhere ORDER BY name, id LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $stmt->execute($args);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('UserRepository::incompleteList failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /**
     * অনুমোদিত কর্মীদের মধ্যে কোন তথ্য কত জনের বাকি। কী '' = মূল চারটার যেকোনোটা বাকি এমন কর্মী (ট্যাব "সব")।
     *
     * @return array<string,int>
     */
    public static function incompleteCounts(): array
    {
        $out = array_fill_keys(array_merge([''], array_keys(IncompleteInfo::LABELS)), 0);
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                if ($row['role'] !== 'worker' || $row['status'] !== 'approved') {
                    continue;
                }
                $missing = IncompleteInfo::missing($row);
                foreach ($missing as $key) {
                    $out[$key]++;
                }
                if (array_intersect($missing, IncompleteInfo::CORE) !== []) {
                    $out['']++;
                }
            }

            return $out;
        }

        try {
            foreach (array_keys($out) as $key) {
                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'worker' AND status = 'approved' AND " . IncompleteInfo::sqlFor((string) $key));
                $out[$key] = $stmt === false ? 0 : (int) $stmt->fetchColumn();
            }
        } catch (Throwable $e) {
            Logger::error('UserRepository::incompleteCounts failed: ' . $e->getMessage());
        }

        return $out;
    }

    /**
     * অ্যাডমিন একজন কর্মীর খালি থাকা মোবাইল/ইমেইল/ঠিকানা বসায় (যে কী-তে মান আছে শুধু সেটাই বদলায়, বাকি যেমন আছে)।
     * মোবাইল বসলে phone_raw মুছে যায়। 'ok', 'duplicate' (মোবাইল), 'duplicate_email' বা 'error' ফেরত দেয়।
     *
     * @param array{phone?:string,email?:string,address?:string} $fields
     */
    public static function fillMissing(int $id, array $fields): string
    {
        $member = self::findById($id);
        if ($member === null) {
            return 'error';
        }
        $role = (string) $member['role'];
        $columns = [];
        foreach (IncompleteInfo::INLINE as $key) {
            if ((string) ($fields[$key] ?? '') !== '') {
                $columns[$key] = (string) $fields[$key];
            }
        }
        if ($columns === []) {
            return 'error';
        }
        if (isset($columns['phone'])) {
            $other = self::findByPhone($columns['phone'], $role);
            if ($other !== null && (int) $other['id'] !== $id) {
                return 'duplicate';
            }
            $columns['phone_raw'] = null;
        }
        if (isset($columns['email'])) {
            $other = self::findByEmail($columns['email'], $role);
            if ($other !== null && (int) $other['id'] !== $id) {
                return 'duplicate_email';
            }
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $rows[$i] = array_merge($row, $columns);
                }
            }

            return self::demoWrite($rows) ? 'ok' : 'error';
        }

        try {
            $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($columns)));
            $pdo->prepare("UPDATE users SET $sets WHERE id = :id")->execute($columns + ['id' => $id]);

            return 'ok';
        } catch (Throwable $e) {
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return str_contains($e->getMessage(), 'uq_users_role_email') ? 'duplicate_email' : 'duplicate';
            }
            Logger::error('UserRepository::fillMissing failed: ' . $e->getMessage());

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

    /**
     * অনুমোদিত কর্মীর সংখ্যা প্রতিটি পেশায় (প্রধান পেশা ও "আরও পেশা" দুটোই গোনা): slug => সংখ্যা।
     *
     * @return array<string,int>
     */
    public static function workerCountsByService(): array
    {
        $pdo = Database::connection();
        $counts = [];
        if ($pdo === null) {
            foreach (self::demoRead() as $r) {
                if (($r['role'] ?? '') === 'worker' && ($r['status'] ?? '') === 'approved') {
                    foreach (array_unique(array_filter(array_merge([(string) ($r['service_slug'] ?? '')], (array) ($r['extra_services'] ?? [])))) as $slug) {
                        $counts[$slug] = ($counts[$slug] ?? 0) + 1;
                    }
                }
            }

            return $counts;
        }
        try {
            $rows = $pdo->query(
                "SELECT slug, COUNT(DISTINCT uid) AS n FROM (
                    SELECT service_slug AS slug, id AS uid FROM users WHERE role = 'worker' AND status = 'approved' AND service_slug IS NOT NULL
                    UNION ALL
                    SELECT ws.service_slug, ws.user_id FROM worker_services ws JOIN users u ON u.id = ws.user_id WHERE u.role = 'worker' AND u.status = 'approved'
                 ) t GROUP BY slug"
            )->fetchAll();
            foreach ($rows as $r) {
                $counts[(string) $r['slug']] = (int) $r['n'];
            }
        } catch (Throwable $e) {
            Logger::error('UserRepository::workerCountsByService failed: ' . $e->getMessage());
        }

        return $counts;
    }

    /**
     * কর্মীদের নিজের লেখা পেশা (তালিকায় ছিল না, service_custom), একই লেখা এক সারিতে।
     * মোছার পথে থাকা (সংরক্ষিত/ডাস্টবিন) সদস্য বাদ।
     *
     * @return list<array{text:string,members:list<array{id:int,name:string,status:string}>}>
     */
    public static function customProfessions(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(), static fn (array $r): bool => ($r['role'] ?? '') === 'worker'
                && (string) ($r['service_custom'] ?? '') !== '' && !in_array($r['status'] ?? '', ['reserved', 'trashed'], true)));
        } else {
            try {
                $rows = $pdo->query(
                    "SELECT id, name, status, service_custom FROM users
                     WHERE role = 'worker' AND service_custom IS NOT NULL AND service_custom <> '' AND status NOT IN ('reserved', 'trashed')
                     ORDER BY service_custom, id"
                )->fetchAll();
            } catch (Throwable $e) {
                Logger::error('UserRepository::customProfessions failed: ' . $e->getMessage());

                return [];
            }
        }
        $groups = [];
        foreach ($rows as $r) {
            $text = trim((string) $r['service_custom']);
            $groups[$text][] = ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'status' => (string) $r['status']];
        }

        return array_map(static fn (string $text, array $members): array => ['text' => $text, 'members' => $members], array_keys($groups), array_values($groups));
    }

    /**
     * নিজের লেখা একটা পেশার সব কর্মীকে তালিকার একটা সেবায় বসায় (service_slug), service_custom মুছে।
     * ফেরত: কতজন বদলাল।
     */
    public static function assignCustomProfession(string $text, string $slug): int
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $n = 0;
            foreach ($rows as &$r) {
                if (($r['role'] ?? '') === 'worker' && trim((string) ($r['service_custom'] ?? '')) === $text) {
                    $r['service_slug'] = $slug;
                    $r['service_custom'] = null;
                    $n++;
                }
            }
            unset($r);

            return self::demoWrite($rows) ? $n : 0;
        }
        try {
            $stmt = $pdo->prepare("UPDATE users SET service_slug = :slug, service_custom = NULL WHERE role = 'worker' AND TRIM(service_custom) = :text");
            $stmt->execute([':slug' => $slug, ':text' => $text]);

            return $stmt->rowCount();
        } catch (Throwable $e) {
            Logger::error('UserRepository::assignCustomProfession failed: ' . $e->getMessage());

            return 0;
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
