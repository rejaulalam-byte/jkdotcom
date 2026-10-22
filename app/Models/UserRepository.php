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
     * @param array<string,mixed> $data role, name, phone, email, password_hash, address, upazila_id, district_id, division_id,
     *                                  photo, এবং কর্মীর জন্য service_slug, experience_years, bio / প্রতিনিধির জন্য nid_number
     */
    public static function create(array $data): string
    {
        if (self::findByPhone((string) $data['phone']) !== null) {
            return 'duplicate';
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoAdd($data) ? 'ok' : 'error';
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users
                    (role, status, name, phone, email, password_hash, service_slug, experience_years, bio, nid_number,
                     address, upazila_id, district_id, division_id, photo, ip_hash, created_at)
                 VALUES
                    (:role, 'pending', :name, :phone, :email, :hash, :service, :experience, :bio, :nid,
                     :address, :upazila, :district, :division, :photo, :ip_hash, NOW())"
            );
            $stmt->execute([
                ':role' => $data['role'],
                ':name' => $data['name'],
                ':phone' => $data['phone'],
                ':email' => $data['email'],
                ':hash' => $data['password_hash'],
                ':service' => $data['service_slug'] ?? null,
                ':experience' => $data['experience_years'] ?? null,
                ':bio' => ($data['bio'] ?? '') !== '' ? $data['bio'] : null,
                ':nid' => $data['nid_number'] ?? null,
                ':address' => $data['address'],
                ':upazila' => $data['upazila_id'],
                ':district' => $data['district_id'],
                ':division' => $data['division_id'],
                ':photo' => $data['photo'] ?? null,
                ':ip_hash' => Security::ipHash(),
            ]);

            return 'ok';
        } catch (Throwable $e) {
            // একই মোবাইলে দুটো আবেদন একসঙ্গে এলে ইউনিক-কী আটকায়
            if ($e instanceof \PDOException && ($e->errorInfo[1] ?? 0) === 1062) {
                return 'duplicate';
            }
            Logger::error('UserRepository::create failed: ' . $e->getMessage());

            return 'error';
        }
    }

    /**
     * মোবাইল নাম্বার দিয়ে অ্যাকাউন্ট খোঁজে (লগইন ও দ্বিতীয়বার-নিবন্ধন আটকাতে)।
     *
     * @return array<string,mixed>|null
     */
    public static function findByPhone(string $phone): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $row) {
                if ((string) $row['phone'] === $phone) {
                    return $row;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE phone = :phone LIMIT 1');
            $stmt->execute([':phone' => $phone]);
            $row = $stmt->fetch();

            return $row === false ? null : $row;
        } catch (Throwable $e) {
            Logger::error('UserRepository::findByPhone failed: ' . $e->getMessage());

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
