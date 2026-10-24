<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * কর্মী-তালিকার ঘূর্ণি ক্রম ও ভিজিটের গণনা।
 *
 * - ক্রম: যে কর্মী কখনও "কল" পায়নি বা সবচেয়ে আগে পেয়েছে সে তালিকার ওপরে; কল পেলে ওই পেশার তালিকায় নিচে নামে।
 *   "কল" = দর্শক নাম-মোবাইল দিয়ে নাম্বার/ইমেইল দেখেছেন। একই ক্রমে সবসময় একই নাম প্রথমে থাকে না, তাই সবাই সমান সুযোগ পান।
 * - কখনও-কল-না-পাওয়াদের মধ্যে ক্রম দর্শকের সেশন-ভিত্তিক এলোমেলো (এক দর্শকের কাছে স্থির, দর্শক বদলালে বদলায়)।
 * - এক দর্শক যতক্ষণ তালিকা দেখেন, ক্রম স্থির থাকে (পৃষ্ঠা বদলালে একই নাম দুবার বা বাদ পড়ে না); ১৫ মিনিট পর বা কর্মী-সেট বদলালে নতুন করে সাজে।
 */
final class WorkerRotation
{
    /** ডাটাবেস না থাকলে (লোকাল প্রিভিউ) এই ফাইলে থাকে। */
    private const DEMO_FILE = '/storage/demo-rotation.json';

    /** এক দর্শকের কাছে সাজানো ক্রম কতক্ষণ স্থির থাকে (সেকেন্ড)। */
    private const KEEP_SECONDS = 900;

    /**
     * কর্মীদের ঘূর্ণি ক্রমে সাজায়।
     *
     * @param list<array<string,mixed>> $workers  প্রতিটিতে 'id'
     * @return list<array<string,mixed>>
     */
    public static function ordered(array $workers, string $slug): array
    {
        $ids = array_map(static fn (array $w): int => (int) $w['id'], $workers);
        $snap = $_SESSION['wk_rot'][$slug] ?? null;
        $sorted = $ids;
        sort($sorted);

        if (!is_array($snap) || time() - (int) $snap['t'] > self::KEEP_SECONDS || $snap['set'] !== $sorted) {
            $last = self::lastCalls($slug);
            $seed = $_SESSION['wk_seed'] ??= bin2hex(random_bytes(4));
            $rank = [];
            foreach ($ids as $id) {
                $rank[$id] = [$last[$id] ?? 0, crc32($seed . '|' . $slug . '|' . $id)];
            }
            usort($ids, static fn (int $a, int $b): int => $rank[$a] <=> $rank[$b]);
            $snap = ['t' => time(), 'set' => $sorted, 'ids' => $ids];
            $_SESSION['wk_rot'][$slug] = $snap;
        }

        $pos = array_flip($snap['ids']);
        usort($workers, static fn (array $a, array $b): int => $pos[(int) $a['id']] <=> $pos[(int) $b['id']]);

        return $workers;
    }

    /**
     * একজন কর্মী একটা পেশার তালিকায় "কল" পেলেন। একই দর্শক একই কর্মীকে একদিনে একবারই গোনা হয়।
     */
    public static function recordCall(int $userId, string $slug): void
    {
        $key = $userId . '|' . date('Y-m-d');
        if (!empty($_SESSION['rot_done'][$key])) {
            return;
        }
        $_SESSION['rot_done'][$key] = true;

        $pdo = Database::connection();
        if ($pdo === null) {
            self::demoWrite(static function (array $d) use ($userId, $slug): array {
                $d['calls'][$slug . '|' . $userId] = time();

                return $d;
            });

            return;
        }

        try {
            $pdo->prepare(
                'INSERT INTO worker_rotation (user_id, service_slug, last_call_at, calls) VALUES (:u, :s, NOW(), 1)
                 ON DUPLICATE KEY UPDATE last_call_at = NOW(), calls = calls + 1'
            )->execute([':u' => $userId, ':s' => $slug]);
        } catch (Throwable $e) {
            Logger::error('WorkerRotation::recordCall failed: ' . $e->getMessage());
        }
    }

    /** কর্মীর ব্যক্তিগত পেইজ দেখা হলো। একই দর্শকের একদিনের একাধিকবার ও সার্চ-বট বাদ। */
    public static function recordVisit(int $userId): void
    {
        if (preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview/i', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1) {
            return;
        }
        $key = $userId . '|' . date('Y-m-d');
        if (!empty($_SESSION['visit_done'][$key])) {
            return;
        }
        $_SESSION['visit_done'][$key] = true;

        $pdo = Database::connection();
        if ($pdo === null) {
            self::demoWrite(static function (array $d) use ($key): array {
                $d['visits'][$key] = (int) ($d['visits'][$key] ?? 0) + 1;

                return $d;
            });

            return;
        }

        try {
            $pdo->prepare(
                'INSERT INTO worker_visits (user_id, visit_date, visits) VALUES (:u, CURDATE(), 1)
                 ON DUPLICATE KEY UPDATE visits = visits + 1'
            )->execute([':u' => $userId]);
        } catch (Throwable $e) {
            Logger::error('WorkerRotation::recordVisit failed: ' . $e->getMessage());
        }
    }

    /**
     * এই পেশার তালিকায় কর্মীর সর্বশেষ কল-এর সময় (কর্মীর id => Unix সময়)। কখনও কল না পেলে নেই।
     *
     * @return array<int,int>
     */
    private static function lastCalls(string $slug): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $out = [];
            foreach ((array) (self::demoRead()['calls'] ?? []) as $key => $at) {
                [$s, $id] = explode('|', (string) $key) + [1 => '0'];
                if ($s === $slug) {
                    $out[(int) $id] = (int) $at;
                }
            }

            return $out;
        }

        try {
            $stmt = $pdo->prepare('SELECT user_id, UNIX_TIMESTAMP(last_call_at) AS t FROM worker_rotation WHERE service_slug = :s');
            $stmt->execute([':s' => $slug]);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[(int) $row['user_id']] = (int) $row['t'];
            }

            return $out;
        } catch (Throwable $e) {
            Logger::error('WorkerRotation::lastCalls failed: ' . $e->getMessage());

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

    /** @param callable(array<string,mixed>):array<string,mixed> $change */
    private static function demoWrite(callable $change): void
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }
        @file_put_contents($file, json_encode($change(self::demoRead()), JSON_UNESCAPED_UNICODE), LOCK_EX);
    }
}
