<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * সাইটের পেইজ-ভিজিট: কোন দর্শক (ব্রাউজার-সেশনের এলোমেলো কোড) একদিনে কোন কোন পেইজ দেখলেন। IP বা নাম রাখা হয় না।
 * record() প্রতিটি সাধারণ পেইজ দেখানোর সময় চলে (View::render); বট, অ্যাডমিন, লাইভ-এডিট ফ্রেম ও ভুল-পেইজ (404) বাদ।
 * today() ড্যাশবোর্ডের "আজকের দর্শক" ঘরের সংখ্যাগুলো দেয়। গোনা শুরু হয় চালু হওয়ার সময় থেকে, পিছনের দিনের নয়।
 */
final class PageVisits
{
    /** এর বেশি পুরনো সারি মাঝে মাঝে মুছে ফেলা হয় (দিন)। */
    private const KEEP_DAYS = 120;

    /** এই দর্শক আজ এই পেইজ দেখলেন (একদিনে একই পেইজ একবারই)। */
    public static function record(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || live_preview() || !empty($_SESSION['admin_id']) || (int) http_response_code() !== 200) {
            return;
        }
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($agent === '' || preg_match('/bot|crawl|spider|slurp|facebookexternalhit|preview|monitor|curl|wget|python|headless/i', $agent) === 1) {
            return;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = $path === '' ? '/' : ($path === '/' ? '/' : rtrim($path, '/'));
        if (strlen($path) > 120) {
            return;
        }

        $today = date('Y-m-d');
        if (($_SESSION['pv_date'] ?? '') !== $today) {
            $_SESSION['pv_date'] = $today;
            $_SESSION['pv_done'] = [];
        }
        if (!empty($_SESSION['pv_done'][$path])) {
            return;
        }
        $_SESSION['pv_done'][$path] = true;
        $_SESSION['pv_id'] ??= bin2hex(random_bytes(8));

        $pdo = Database::connection();
        if ($pdo === null) {
            return;
        }
        try {
            $pdo->prepare('INSERT IGNORE INTO page_visits (visit_date, visitor, page) VALUES (:d, :v, :p)')
                ->execute([':d' => $today, ':v' => $_SESSION['pv_id'], ':p' => $path]);
            if (random_int(1, 300) === 1) {
                $pdo->prepare('DELETE FROM page_visits WHERE visit_date < :d')->execute([':d' => date('Y-m-d', strtotime('-' . self::KEEP_DAYS . ' days'))]);
            }
        } catch (Throwable $e) {
            Logger::error('PageVisits::record failed: ' . $e->getMessage());
        }
    }

    /**
     * আজ এ পর্যন্তের সংখ্যা।
     *
     * @return array{visitors:int,home_only:int,many:int,services_only:int,services_seen:int}
     */
    public static function today(): array
    {
        $zero = ['visitors' => 0, 'home_only' => 0, 'many' => 0, 'services_only' => 0, 'services_seen' => 0];
        $pdo = Database::connection();
        if ($pdo === null) {
            return $zero;
        }
        $d = date('Y-m-d');
        $count = static function (string $having) use ($pdo, $d): int {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM (SELECT visitor FROM page_visits WHERE visit_date = :d GROUP BY visitor $having) t");
            $stmt->execute([':d' => $d]);

            return (int) $stmt->fetchColumn();
        };

        try {
            $seen = $pdo->prepare("SELECT COUNT(DISTINCT page) FROM page_visits WHERE visit_date = :d AND page LIKE '/services/%'");
            $seen->execute([':d' => $d]);

            return [
                'visitors' => $count(''),
                'home_only' => $count("HAVING COUNT(*) = 1 AND MAX(page) = '/'"),
                'many' => $count('HAVING COUNT(*) >= 2'),
                'services_only' => $count("HAVING SUM(CASE WHEN page LIKE '/services/%' THEN 0 ELSE 1 END) = 0"),
                'services_seen' => (int) $seen->fetchColumn(),
            ];
        } catch (Throwable $e) {
            Logger::error('PageVisits::today failed: ' . $e->getMessage());

            return $zero;
        }
    }
}
