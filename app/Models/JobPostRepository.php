<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** "আজকের প্রকাশিত" কাজের বিজ্ঞপ্তি। */
final class JobPostRepository
{
    /**
     * সর্বজনীন ফর্ম থেকে জানানো কাজ সংরক্ষণ করে। সঙ্গে সঙ্গে প্রকাশিত হয় (status 'approved'); অ্যাডমিন পরে সম্পাদনা বা লুকাতে পারবেন।
     *
     * @param array{title:string,title_custom:int,details:string,contact_name:string,contact_gender:string,phone:string,email:string,address:string,upazila_id:int,district_id:int,division_id:int,start_date:string,end_date:string} $data
     */
    public static function create(array $data): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            // ডাটাবেস নেই (লোকাল প্রিভিউ): পোস্ট storage/demo-jobs.json-এ রেখে সঙ্গে সঙ্গে দেখানো হয়,
            // যাতে পুরো প্রক্রিয়া (জানান → তালিকা) পরীক্ষা করা যায়। আসল সাইটেও কাজের বিজ্ঞপ্তিতে অনুমোদন লাগে না।
            return self::demoAdd($data);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO job_posts
                    (title, title_custom, details, contact_name, contact_gender, phone, email, address, upazila_id, district_id, division_id,
                     start_date, end_date, ip_hash, status, published_at, consent_at, created_at)
                 VALUES
                    (:title, :title_custom, :details, :name, :gender, :phone, :email, :address, :upazila, :district, :division,
                     :start_date, :end_date, :ip_hash, 'approved', NOW(), :consent_at, NOW())"
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':title_custom' => $data['title_custom'],
                ':details' => $data['details'],
                ':name' => $data['contact_name'],
                ':gender' => $data['contact_gender'] !== '' ? $data['contact_gender'] : null,
                ':phone' => $data['phone'],
                ':email' => $data['email'] !== '' ? $data['email'] : null,
                ':address' => $data['address'],
                ':upazila' => $data['upazila_id'],
                ':district' => $data['district_id'],
                ':division' => $data['division_id'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
                ':ip_hash' => Security::ipHash(),
                ':consent_at' => $data['consent_at'] ?? null,
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::error('JobPostRepository::create failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * গত $days দিনে প্রকাশিত অনুমোদিত বিজ্ঞপ্তি, নতুনটা আগে।
     *
     * @return list<array{id:int,title:string}>
     */
    public static function recent(int $days, int $limit = 40): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            // লোকাল প্রিভিউতে করা পোস্ট আগে আসে
            $mine = array_map(
                static fn (array $r): array => ['id' => (int) $r['id'], 'title' => (string) $r['title']],
                array_reverse(self::demoRead())
            );

            return array_merge($mine, DemoData::jobs());
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT id, title FROM job_posts
                 WHERE status = 'approved' AND published_at >= :cutoff
                 ORDER BY published_at DESC, id DESC
                 LIMIT :max"
            );
            $stmt->bindValue(':cutoff', date('Y-m-d H:i:s', time() - $days * 86400));
            $stmt->bindValue(':max', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return array_map(
                static fn (array $r): array => ['id' => (int) $r['id'], 'title' => (string) $r['title']],
                $stmt->fetchAll()
            );
        } catch (Throwable $e) {
            Logger::error('JobPostRepository::recent failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * তালিকা-পেইজে দেখানোর বিজ্ঞপ্তি: অনুমোদিত, শুরু হয়ে গেছে, এখনও শেষ হয়নি
     * (শেষের তারিখ না থাকলে `settings.jobs_visible_days` দিন থাকে)। নতুনটা আগে।
     * ফোন ও ইমেইল আসল রূপে নেই, শুধু লুকানো রূপ আছে (পূর্ণরূপ আসে contactOf() থেকে)।
     *
     * @return list<array<string,string|int>>
     */
    public static function listActive(int $limit = 100): array
    {
        $out = [];
        foreach (self::activeRows($limit) as $r) {
            $names = GeoRepository::names((int) ($r['upazila_id'] ?? 0), (int) ($r['district_id'] ?? 0), (int) ($r['division_id'] ?? 0));
            $out[] = [
                'id' => (int) $r['id'],
                'title' => (string) $r['title'],
                'details' => (string) $r['details'],
                'contact_name' => (string) $r['contact_name'],
                'address' => (string) $r['address'],
                'upazila' => $names['upazila'],
                'district' => $names['district'],
                'division' => $names['division'],
                'start_date' => (string) ($r['start_date'] ?? substr((string) $r['created_at'], 0, 10)),
                'created_at' => (string) $r['created_at'],
                'phone_masked' => mask_phone((string) $r['phone']),
                'email_masked' => mask_email((string) ($r['email'] ?? '')),
            ];
        }

        return $out;
    }

    /**
     * দেখানো একটা বিজ্ঞপ্তির পূর্ণ ফোন ও ইমেইল (লুকানো নাম্বারে ক্লিকের পর চাওয়া হয়)।
     *
     * @return array{phone:string,email:string}|null
     */
    public static function contactOf(int $id): ?array
    {
        foreach (self::activeRows(500) as $r) {
            if ((int) $r['id'] === $id) {
                return ['phone' => (string) $r['phone'], 'email' => (string) ($r['email'] ?? '')];
            }
        }

        return null;
    }

    /** @return list<array<string,mixed>> */
    private static function activeRows(int $limit): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return array_slice(array_merge(array_reverse(self::demoRead()), DemoData::jobPosts()), 0, $limit);
        }

        try {
            $days = max(1, min(30, (int) Content::get('settings.jobs_visible_days', '3')));
            $stmt = $pdo->prepare(
                "SELECT id, title, details, contact_name, phone, email, address, upazila_id, district_id, division_id,
                        start_date, created_at
                 FROM job_posts
                 WHERE status = 'approved'
                   AND COALESCE(start_date, DATE(created_at)) <= CURDATE()
                   AND COALESCE(end_date, DATE_ADD(COALESCE(start_date, DATE(created_at)), INTERVAL :days DAY)) >= CURDATE()
                 ORDER BY created_at DESC, id DESC
                 LIMIT :max"
            );
            $stmt->bindValue(':days', $days, \PDO::PARAM_INT);
            $stmt->bindValue(':max', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (Throwable $e) {
            Logger::error('JobPostRepository::activeRows failed: ' . $e->getMessage());

            return [];
        }
    }

    private const DEMO_FILE = '/storage/demo-jobs.json';

    /** @return list<array<string,mixed>> */
    private static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param array<string,mixed> $data */
    private static function demoAdd(array $data): bool
    {
        $rows = self::demoRead();
        $rows[] = [
            'id' => 1000 + count($rows) + 1,
            'title' => $data['title'],
            'details' => $data['details'],
            'contact_name' => $data['contact_name'],
            'contact_gender' => $data['contact_gender'],
            'phone' => $data['phone'],
            'email' => $data['email'],
            'address' => $data['address'],
            'upazila_id' => $data['upazila_id'],
            'district_id' => $data['district_id'],
            'division_id' => $data['division_id'],
            'start_date' => $data['start_date'],
            'created_at' => date('Y-m-d H:i:s'),
        ];
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        if (@file_put_contents($file, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) === false) {
            Logger::error('Job post (demo mode) could not be written to ' . $file);

            return false;
        }

        return true;
    }
}
