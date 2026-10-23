<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** কর্মীর কাজের মূল্যায়ন (রেটিং): জমা নেওয়া ও গড় হিসাব। */
final class RatingRepository
{
    /** মালিকের ঠিক করা বাছাই; তালিকার বাইরের মান নেওয়া হয় না। */
    public const CHOICES = [5.0, 4.5, 4.0, 3.5, 3.0, 2.5, 2.0, 1.0];

    /** ডাটাবেস না থাকলে (লোকাল প্রিভিউ) রেটিং এই ফাইলে থাকে। */
    private const DEMO_FILE = '/storage/demo-ratings.json';

    /** ফর্মে আসা লেখা বৈধ রেটিং হলে সংখ্যা, নইলে null। */
    public static function parse(mixed $value): ?float
    {
        $number = is_string($value) ? (float) $value : 0.0;

        return in_array($number, self::CHOICES, true) ? $number : null;
    }

    /**
     * একজন দর্শক (voter) একটা কর্মীকে রেটিং দেন; আগেও দিয়ে থাকলে সেটা বদলে যায়।
     */
    public static function submit(int $workerId, string $voter, float $rating): bool
    {
        $voterHash = hash('sha256', $voter);
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $rows[$workerId . ':' . $voterHash] = ['worker_id' => $workerId, 'rating' => $rating];

            return self::demoWrite($rows);
        }

        try {
            $pdo->prepare(
                'INSERT INTO worker_ratings (worker_id, rating, voter_hash, ip_hash)
                 VALUES (:w, :r, :v, :ip)
                 ON DUPLICATE KEY UPDATE rating = VALUES(rating), ip_hash = VALUES(ip_hash)'
            )->execute([':w' => $workerId, ':r' => $rating, ':v' => $voterHash, ':ip' => Security::ipHash()]);

            return true;
        } catch (Throwable $e) {
            Logger::error('RatingRepository::submit failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * কয়েকজন কর্মীর গড় রেটিং (এক দশমিকে); রেটিং না পাওয়া কর্মী ফলাফলে থাকে না।
     *
     * @param list<int> $workerIds
     * @return array<int,float>  কর্মীর id => গড়
     */
    public static function averages(array $workerIds): array
    {
        if ($workerIds === []) {
            return [];
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $sum = [];
            foreach (self::demoRead() as $row) {
                if (in_array((int) $row['worker_id'], $workerIds, true)) {
                    $sum[(int) $row['worker_id']][] = (float) $row['rating'];
                }
            }

            return array_map(static fn (array $list): float => round(array_sum($list) / count($list), 1), $sum);
        }

        try {
            $in = implode(',', array_fill(0, count($workerIds), '?'));
            $stmt = $pdo->prepare("SELECT worker_id, ROUND(AVG(rating), 1) AS avg_rating FROM worker_ratings WHERE worker_id IN ($in) GROUP BY worker_id");
            $stmt->execute(array_values($workerIds));

            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int) $row['worker_id']] = (float) $row['avg_rating'];
            }

            return $out;
        } catch (Throwable $e) {
            Logger::error('RatingRepository::averages failed: ' . $e->getMessage());

            return [];
        }
    }

    /** একজন কর্মীর গড় রেটিং ও কতজন দিয়েছেন। @return array{avg:?float,count:int} */
    public static function summary(int $workerId): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $list = [];
            foreach (self::demoRead() as $row) {
                if ((int) $row['worker_id'] === $workerId) {
                    $list[] = (float) $row['rating'];
                }
            }

            return ['avg' => $list === [] ? null : round(array_sum($list) / count($list), 1), 'count' => count($list)];
        }

        try {
            $stmt = $pdo->prepare('SELECT ROUND(AVG(rating), 1) AS avg_rating, COUNT(*) AS n FROM worker_ratings WHERE worker_id = :w');
            $stmt->execute([':w' => $workerId]);
            $row = $stmt->fetch();

            return ['avg' => $row && (int) $row['n'] > 0 ? (float) $row['avg_rating'] : null, 'count' => (int) ($row['n'] ?? 0)];
        } catch (Throwable $e) {
            Logger::error('RatingRepository::summary failed: ' . $e->getMessage());

            return ['avg' => null, 'count' => 0];
        }
    }

    /** @return array<string,array{worker_id:int,rating:float}> */
    private static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $rows = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];

        return is_array($rows) ? $rows : [];
    }

    /** @param array<string,array{worker_id:int,rating:float}> $rows */
    private static function demoWrite(array $rows): bool
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }

        return @file_put_contents($file, json_encode($rows, JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
