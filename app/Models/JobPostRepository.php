<?php

declare(strict_types=1);

namespace App\Models;

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
            Logger::error('Job post (demo mode, not saved to DB): ' . json_encode($data, JSON_UNESCAPED_UNICODE));

            return true;
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
            return DemoData::jobs();
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
}
