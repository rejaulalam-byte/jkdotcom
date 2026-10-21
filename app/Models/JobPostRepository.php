<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** "আজকের প্রকাশিত" job notices. */
final class JobPostRepository
{
    /**
     * Saves a job posted from the public form. It waits as 'pending' until the admin approves it.
     *
     * @param array{title:string,title_custom:int,details:string,contact_name:string,phone:string,email:string,address:string,upazila_id:int,district_id:int,division_id:int,start_date:string,end_date:string} $data
     */
    public static function create(array $data): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            // No database (local preview): keep the post in storage/demo-jobs.json and show it at once,
            // so the whole flow (post -> list) can be tried. On the real site posts wait for the admin.
            return self::demoAdd($data);
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO job_posts
                    (title, title_custom, details, contact_name, phone, email, address, upazila_id, district_id, division_id,
                     start_date, end_date, ip_hash, status, created_at)
                 VALUES
                    (:title, :title_custom, :details, :name, :phone, :email, :address, :upazila, :district, :division,
                     :start_date, :end_date, :ip_hash, 'pending', NOW())"
            );
            $stmt->execute([
                ':title' => $data['title'],
                ':title_custom' => $data['title_custom'],
                ':details' => $data['details'],
                ':name' => $data['contact_name'],
                ':phone' => $data['phone'],
                ':email' => $data['email'] !== '' ? $data['email'] : null,
                ':address' => $data['address'],
                ':upazila' => $data['upazila_id'],
                ':district' => $data['district_id'],
                ':division' => $data['division_id'],
                ':start_date' => $data['start_date'],
                ':end_date' => $data['end_date'] !== '' ? $data['end_date'] : null,
                ':ip_hash' => Security::ipHash(),
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::error('JobPostRepository::create failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * Approved notices published within the last $days days, newest first.
     *
     * @return list<array{id:int,title:string}>
     */
    public static function recent(int $days, int $limit = 40): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            // posts made during the local preview come first
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
     * Notices to show on the list page: approved, started, and not yet finished
     * (without an end date a notice stays for `settings.jobs_visible_days` days). Newest first.
     * Phone and e-mail are NOT included, only hidden versions (the full ones come from contactOf()).
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
     * Full phone and e-mail of one shown notice (asked for by a click on the hidden number).
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
