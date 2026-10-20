<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** "আজকের প্রকাশিত" job notices. */
final class JobPostRepository
{
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
