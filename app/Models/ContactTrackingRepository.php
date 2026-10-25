<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/**
 * কে লুকানো ফোন নাম্বার / ইমেইল দেখতে চাইছে তার হিসাব:
 *  - লুকানো নাম্বার বা ইমেইলে প্রতিটি ক্লিক একটা "কল" হিসেবে গোনা হয় (contact_clicks);
 *  - যিনি দেখতে চান তিনি আগে আগ্রহের কথা জানিয়ে নাম + মোবাইল দেন (contact_leads)।
 * কাজের বিজ্ঞপ্তির জন্য ব্যবহার হয় ($type = 'job'), পরে কর্মীর তালিকার জন্যও ($type = 'worker')।
 * অ্যাডমিন প্যানেলে দিন/বিজ্ঞপ্তি-ভিত্তিক গণনা ও আগ্রহীদের তালিকা দেখানো হবে।
 */
final class ContactTrackingRepository
{
    private const DEMO_FILE = '/storage/demo-contact-log.json';

    /** লুকানো ফোন/ইমেইলে একটা ক্লিক। $kind = 'phone' | 'email'। */
    public static function click(string $type, int $targetId, string $kind): void
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            self::demoAppend('clicks', ['type' => $type, 'target_id' => $targetId, 'kind' => $kind, 'at' => date('Y-m-d H:i:s')]);

            return;
        }

        try {
            $pdo->prepare('INSERT INTO contact_clicks (target_type, target_id, kind, clicked_at, ip_hash) VALUES (:t, :i, :k, NOW(), :ip)')
                ->execute([':t' => $type, ':i' => $targetId, ':k' => $kind, ':ip' => Security::ipHash()]);
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::click failed: ' . $e->getMessage());
        }
    }

    /** একজন দর্শক "আমি আগ্রহী" বলে নিজের নাম ও মোবাইল নাম্বার দিয়েছেন। */
    public static function lead(string $type, int $targetId, string $kind, string $name, string $phone): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoAppend('leads', [
                'type' => $type, 'target_id' => $targetId, 'kind' => $kind,
                'name' => $name, 'phone' => $phone, 'at' => date('Y-m-d H:i:s'),
            ]);
        }

        try {
            $pdo->prepare(
                'INSERT INTO contact_leads (target_type, target_id, kind, visitor_name, visitor_phone, created_at, ip_hash)
                 VALUES (:t, :i, :k, :n, :p, NOW(), :ip)'
            )->execute([':t' => $type, ':i' => $targetId, ':k' => $kind, ':n' => $name, ':p' => $phone, ':ip' => Security::ipHash()]);

            return true;
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::lead failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * অ্যাডমিনের আগ্রহীর তালিকা, নতুন আগে। $type: '' সব, 'job' বা 'worker'; $q = আগ্রহীর নাম বা মোবাইলের অংশ।
     * প্রতিটি সারিতে target_title (বিজ্ঞপ্তির ধরন বা কর্মীর নাম, না পেলে খালি)।
     *
     * @return array{rows:list<array<string,mixed>>,total:int}
     */
    public static function leadsList(string $type, string $q, int $limit, int $offset): array
    {
        $type = in_array($type, ['job', 'worker'], true) ? $type : '';
        $q = trim($q);

        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            $rows = [];
            foreach (array_reverse((array) ($data['leads'] ?? [])) as $i => $l) {
                $hay = mb_strtolower(($l['name'] ?? '') . ' ' . ($l['phone'] ?? ''));
                if (($type !== '' && $l['type'] !== $type) || ($q !== '' && !str_contains($hay, mb_strtolower($q)))) {
                    continue;
                }
                $title = '';
                if ($l['type'] === 'worker') {
                    $title = (string) (UserRepository::findById((int) $l['target_id'])['name'] ?? '');
                }
                $rows[] = [
                    'id' => $i + 1, 'target_type' => $l['type'], 'target_id' => (int) $l['target_id'], 'kind' => $l['kind'],
                    'visitor_name' => $l['name'], 'visitor_phone' => $l['phone'], 'created_at' => $l['at'], 'target_title' => $title,
                ];
            }

            return ['rows' => array_slice($rows, $offset, $limit), 'total' => count($rows)];
        }

        $where = ['1 = 1'];
        $args = [];
        if ($type !== '') {
            $where[] = 'l.target_type = :type';
            $args[':type'] = $type;
        }
        if ($q !== '') {
            $where[] = '(l.visitor_name LIKE :q1 OR l.visitor_phone LIKE :q2)';
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $args += [':q1' => $like, ':q2' => $like];
        }
        $sqlWhere = implode(' AND ', $where);

        try {
            $count = $pdo->prepare("SELECT COUNT(*) FROM contact_leads l WHERE $sqlWhere");
            $count->execute($args);
            $stmt = $pdo->prepare(
                "SELECT l.id, l.target_type, l.target_id, l.kind, l.visitor_name, l.visitor_phone, l.created_at,
                        COALESCE(j.title, u.name, '') AS target_title
                 FROM contact_leads l
                 LEFT JOIN job_posts j ON l.target_type = 'job' AND j.id = l.target_id
                 LEFT JOIN users u ON l.target_type = 'worker' AND u.id = l.target_id
                 WHERE $sqlWhere ORDER BY l.id DESC LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
            );
            $stmt->execute($args);

            return ['rows' => $stmt->fetchAll(), 'total' => (int) $count->fetchColumn()];
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::leadsList failed: ' . $e->getMessage());

            return ['rows' => [], 'total' => 0];
        }
    }

    /** @return array{'':int,job:int,worker:int} ধরন অনুযায়ী আগ্রহীর সংখ্যা */
    public static function leadsCounts(): array
    {
        $out = ['' => 0, 'job' => 0, 'worker' => 0];
        $pdo = Database::connection();
        if ($pdo === null) {
            $file = BASE_PATH . self::DEMO_FILE;
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            foreach ((array) ($data['leads'] ?? []) as $l) {
                $out['']++;
                $out[$l['type'] === 'worker' ? 'worker' : 'job']++;
            }

            return $out;
        }

        try {
            foreach ($pdo->query('SELECT target_type, COUNT(*) AS n FROM contact_leads GROUP BY target_type')->fetchAll() as $r) {
                $out[(string) $r['target_type']] = (int) $r['n'];
                $out[''] += (int) $r['n'];
            }
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::leadsCounts failed: ' . $e->getMessage());
        }

        return $out;
    }

    /** @param array<string,mixed> $row */
    private static function demoAppend(string $list, array $row): bool
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $data[$list][] = $row;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }

        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
