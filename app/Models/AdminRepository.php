<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/**
 * অ্যাডমিন প্যানেলের ব্যবহারকারী (সুপার অ্যাডমিন, অ্যাডমিনিস্ট্রেটর) ও কার্যলগ।
 * ডাটাবেস না থাকলে (লোকাল প্রিভিউ) সবকিছু storage/demo-admins.json-এ থাকে।
 */
final class AdminRepository
{
    private const DEMO_FILE = '/storage/demo-admins.json';

    /** update() যে ঘরগুলো বদলাতে দেয় (বাকিটা নয়)। */
    private const EDITABLE = ['name', 'email', 'password_hash', 'role', 'permissions', 'status', 'token_hash', 'token_expires', 'last_login_at'];

    public static function count(): int
    {
        return count(self::all());
    }

    /** @return array<string,mixed>|null */
    public static function findByEmail(string $email): ?array
    {
        $email = mb_strtolower(trim($email));
        foreach (self::all() as $row) {
            if ($row['email'] === $email) {
                return $row;
            }
        }

        return null;
    }

    /** @return array<string,mixed>|null */
    public static function findById(int $id): ?array
    {
        foreach (self::all() as $row) {
            if ((int) $row['id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /** ইমেইলের লিংকের টোকেনের হ্যাশ মিললে ও মেয়াদ থাকলে সেই অ্যাডমিন। @return array<string,mixed>|null */
    public static function findByTokenHash(string $hash): ?array
    {
        foreach (self::all() as $row) {
            if ((string) $row['token_hash'] !== '' && hash_equals((string) $row['token_hash'], $hash)
                && (string) $row['token_expires'] !== '' && strtotime((string) $row['token_expires']) > time()) {
                return $row;
            }
        }

        return null;
    }

    /**
     * সব অ্যাডমিন, পুরনো আগে। প্রতিটিতে 'permission_list' (অনুমতির কী-গুলো) থাকে।
     *
     * @return list<array<string,mixed>>
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead()['admins'] ?? [];
        } else {
            try {
                $rows = $pdo->query('SELECT * FROM admins ORDER BY id ASC')->fetchAll();
            } catch (Throwable $e) {
                Logger::error('AdminRepository::all failed: ' . $e->getMessage());

                return [];
            }
        }

        return array_map(static function (array $r): array {
            $r['permission_list'] = AdminPermissions::clean(json_decode((string) ($r['permissions'] ?? ''), true));

            return $r;
        }, array_values($rows));
    }

    /**
     * নতুন অ্যাডমিন বানায়; id ফেরত দেয়, ব্যর্থ হলে null। ইমেইল আগে থাকলে null।
     *
     * @param list<string> $permissions
     */
    public static function create(string $name, string $email, string $role, array $permissions, ?string $passwordHash): ?int
    {
        $email = mb_strtolower(trim($email));
        if (self::findByEmail($email) !== null) {
            return null;
        }
        $perm = json_encode(AdminPermissions::clean($permissions));

        $pdo = Database::connection();
        if ($pdo === null) {
            $data = self::demoRead();
            $id = 1 + max([0, ...array_map(static fn (array $r): int => (int) $r['id'], $data['admins'] ?? [])]);
            $data['admins'][] = [
                'id' => $id, 'name' => $name, 'email' => $email, 'password_hash' => $passwordHash, 'role' => $role,
                'permissions' => $perm, 'status' => 'active', 'token_hash' => null, 'token_expires' => null,
                'last_login_at' => null, 'created_at' => date('Y-m-d H:i:s'),
            ];

            return self::demoWrite($data) ? $id : null;
        }

        try {
            $pdo->prepare('INSERT INTO admins (name, email, password_hash, role, permissions) VALUES (:n, :e, :p, :r, :perm)')
                ->execute([':n' => $name, ':e' => $email, ':p' => $passwordHash, ':r' => $role, ':perm' => $perm]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            Logger::error('AdminRepository::create failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * কয়েকটা ঘর বদলায় (শুধু EDITABLE-এর ঘর)। সফল হলে true।
     *
     * @param array<string,mixed> $fields
     */
    public static function update(int $id, array $fields): bool
    {
        $fields = array_intersect_key($fields, array_flip(self::EDITABLE));
        if ($fields === []) {
            return false;
        }
        if (isset($fields['email'])) {
            $fields['email'] = mb_strtolower(trim((string) $fields['email']));
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $data = self::demoRead();
            foreach ($data['admins'] ?? [] as $i => $row) {
                if ((int) $row['id'] === $id) {
                    $data['admins'][$i] = array_merge($row, $fields);
                }
            }

            return self::demoWrite($data);
        }

        try {
            $sets = implode(', ', array_map(static fn (string $k): string => "$k = :$k", array_keys($fields)));
            $stmt = $pdo->prepare("UPDATE admins SET $sets WHERE id = :id");
            $stmt->execute(['id' => $id] + $fields);

            return true;
        } catch (Throwable $e) {
            Logger::error('AdminRepository::update failed: ' . $e->getMessage());

            return false;
        }
    }

    /** চালু (active) সুপার অ্যাডমিন কয়জন (শেষজনকে বন্ধ হতে না দিতে)। */
    public static function activeSuperCount(): int
    {
        return count(array_filter(self::all(), static fn (array $r): bool => $r['role'] === 'super' && $r['status'] === 'active'));
    }

    /** কার্যলগে একটা লাইন যোগ করে। */
    public static function log(?int $adminId, string $adminName, string $action, ?string $detail = null): void
    {
        $detail = $detail === null ? null : mb_substr($detail, 0, 500);
        $pdo = Database::connection();
        if ($pdo === null) {
            $data = self::demoRead();
            $data['log'][] = ['admin_id' => $adminId, 'admin_name' => $adminName, 'action' => $action, 'detail' => $detail, 'created_at' => date('Y-m-d H:i:s')];
            self::demoWrite($data);

            return;
        }

        try {
            $pdo->prepare('INSERT INTO admin_log (admin_id, admin_name, action, detail, ip_hash) VALUES (:a, :n, :act, :d, :ip)')
                ->execute([':a' => $adminId, ':n' => $adminName, ':act' => $action, ':d' => $detail, ':ip' => Security::ipHash()]);
        } catch (Throwable $e) {
            Logger::error('AdminRepository::log failed: ' . $e->getMessage());
        }
    }

    /**
     * সবচেয়ে নতুন লগ আগে।
     *
     * @return list<array<string,mixed>>
     */
    public static function recentLog(int $limit = 200): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return array_slice(array_reverse(self::demoRead()['log'] ?? []), 0, $limit);
        }

        try {
            return $pdo->query('SELECT admin_name, action, detail, created_at FROM admin_log ORDER BY id DESC LIMIT ' . (int) $limit)->fetchAll();
        } catch (Throwable $e) {
            Logger::error('AdminRepository::recentLog failed: ' . $e->getMessage());

            return [];
        }
    }

    /** @return array<string,mixed> */
    private static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($data) ? $data : [];
    }

    /** @param array<string,mixed> $data */
    private static function demoWrite(array $data): bool
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }

        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX) !== false;
    }
}
