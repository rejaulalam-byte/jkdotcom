<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/**
 * অ্যাডমিন ড্যাশবোর্ডের সংখ্যা। প্রতিটা সংখ্যা আলাদা প্রশ্নে; কোনো টেবিল না থাকলে বা ডাটাবেস না থাকলে (লোকাল প্রিভিউ) ০।
 */
final class AdminStats
{
    /** আজকের ও মোট গণনা। @return array<string,int> */
    public static function overview(): array
    {
        return [
            'services' => self::count('SELECT COUNT(*) FROM services WHERE is_active = 1'),
            'ads' => self::count('SELECT COUNT(*) FROM advertisements WHERE is_active = 1'),
            'jobs' => self::count("SELECT COUNT(*) FROM job_posts WHERE status = 'approved'"),
            'subscribers' => self::count('SELECT COUNT(*) FROM subscribers'),
            'feedback_unread' => self::count('SELECT COUNT(*) FROM feedback WHERE is_read = 0'),
            'visits_today' => self::count('SELECT COALESCE(SUM(visits), 0) FROM worker_visits WHERE visit_date = CURDATE()'),
            'clicks_today' => self::count('SELECT COUNT(*) FROM contact_clicks WHERE clicked_at >= CURDATE()'),
            'leads_today' => self::count('SELECT COUNT(*) FROM contact_leads WHERE created_at >= CURDATE()'),
        ];
    }

    /** একটা COUNT/SUM প্রশ্ন চালিয়ে পূর্ণসংখ্যা দেয়; ব্যর্থ হলে ০। */
    private static function count(string $sql): int
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return 0;
        }

        try {
            return (int) $pdo->query($sql)->fetchColumn();
        } catch (Throwable $e) {
            Logger::error('AdminStats failed (' . $sql . '): ' . $e->getMessage());

            return 0;
        }
    }
}
