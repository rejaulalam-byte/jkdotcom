<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** দর্শকের মতামত — ডাটাবেসে রাখা হয়, যাতে অ্যাডমিন পড়তে, রাখতে বা মুছতে পারেন। ডাটাবেস না থাকলে storage/demo-feedback.json। */
final class FeedbackRepository
{
    private const DEMO_FILE = '/storage/demo-feedback.json';

    /** তালিকার ট্যাব: কী => নাম। */
    public const TABS = ['' => 'সব', 'new' => 'নতুন (না-পড়া)', 'kept' => 'রাখা'];

    /** @param array{name:string,email:string,phone:string,subject:string,message:string} $data */
    public static function save(array $data): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $rows[] = $data + ['id' => (int) max(array_column($rows, 'id') ?: [0]) + 1, 'is_read' => 0, 'is_kept' => 0, 'created_at' => date('Y-m-d H:i:s')];

            return self::demoWrite($rows);
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO feedback (name, email, phone, subject, message, ip_hash, user_agent, created_at)
                 VALUES (:name, :email, :phone, :subject, :message, :ip_hash, :ua, NOW())'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':subject' => $data['subject'],
                ':message' => $data['message'],
                ':ip_hash' => Security::ipHash(),
                ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::save failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * অ্যাডমিনের তালিকা, নতুন আগে। $tab: '' সব, 'new' না-পড়া, 'kept' রাখা। $q = নাম, ইমেইল, ফোন, বিষয় বা লেখার অংশ।
     *
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function adminList(string $tab, string $q, int $limit, int $offset): array
    {
        $q = trim($q);
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = array_values(array_filter(self::demoRead(), static function (array $r) use ($tab, $q): bool {
                $hay = mb_strtolower(implode(' ', [$r['name'], $r['email'], $r['phone'], $r['subject'], $r['message']]));

                return ($tab !== 'new' || (int) $r['is_read'] === 0) && ($tab !== 'kept' || (int) $r['is_kept'] === 1)
                    && ($q === '' || str_contains($hay, mb_strtolower($q)));
            }));
            usort($rows, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

            return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
        }

        $where = ['1 = 1'];
        $args = [];
        if ($tab === 'new') {
            $where[] = 'is_read = 0';
        } elseif ($tab === 'kept') {
            $where[] = 'is_kept = 1';
        }
        if ($q !== '') {
            $where[] = '(name LIKE :q1 OR email LIKE :q2 OR phone LIKE :q3 OR subject LIKE :q4 OR message LIKE :q5)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $args = [':q1' => $like, ':q2' => $like, ':q3' => $like, ':q4' => $like, ':q5' => $like];
        }
        $sqlWhere = implode(' AND ', $where);

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE $sqlWhere");
            $count->execute($args);
            $stmt = $pdo->prepare(
                "SELECT id, name, email, phone, subject, message, is_read, is_kept, created_at FROM feedback WHERE $sqlWhere
                 ORDER BY id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $stmt->execute($args);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::adminList failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /** @return array{'':int,new:int,kept:int} ট্যাবের সংখ্যা */
    public static function counts(): array
    {
        $out = ['' => 0, 'new' => 0, 'kept' => 0];
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $r) {
                $out['']++;
                $out['new'] += (int) $r['is_read'] === 0 ? 1 : 0;
                $out['kept'] += (int) $r['is_kept'] === 1 ? 1 : 0;
            }

            return $out;
        }

        try {
            $row = $pdo->query('SELECT COUNT(*) AS n, SUM(is_read = 0) AS u, SUM(is_kept = 1) AS k FROM feedback')->fetch();
            $out = ['' => (int) ($row['n'] ?? 0), 'new' => (int) ($row['u'] ?? 0), 'kept' => (int) ($row['k'] ?? 0)];
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::counts failed: ' . $e->getMessage());
        }

        return $out;
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (self::demoRead() as $r) {
                if ((int) $r['id'] === $id) {
                    return $r;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT * FROM feedback WHERE id = :id');
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();

            return is_array($row) ? $row : null;
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::find failed: ' . $e->getMessage());

            return null;
        }
    }

    /** "পড়া হয়েছে" (is_read) বা "রাখা" (is_kept) চিহ্ন বসায়/তোলে। সফল হলে true। */
    public static function setFlag(int $id, string $flag, bool $value): bool
    {
        if (!in_array($flag, ['is_read', 'is_kept'], true)) {
            return false;
        }
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $r) {
                if ((int) $r['id'] === $id) {
                    $rows[$i][$flag] = $value ? 1 : 0;
                }
            }

            return self::demoWrite($rows);
        }

        try {
            // $flag ওপরে তালিকা মিলিয়ে নেওয়া, তাই SQL-এ বসানো নিরাপদ
            return $pdo->prepare("UPDATE feedback SET $flag = :v WHERE id = :id")->execute([':v' => $value ? 1 : 0, ':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::setFlag failed: ' . $e->getMessage());

            return false;
        }
    }

    /** একটা মতামত চিরতরে মোছে। সফল হলে true। */
    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoWrite(array_values(array_filter(self::demoRead(), static fn (array $r): bool => (int) $r['id'] !== $id)));
        }

        try {
            return $pdo->prepare('DELETE FROM feedback WHERE id = :id')->execute([':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::delete failed: ' . $e->getMessage());

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
        return @file_put_contents(BASE_PATH . self::DEMO_FILE, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
