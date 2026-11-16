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

    /** তৃতীয় গ্রাফে সর্বোচ্চ কতটি সেবা। */
    private const WEEK_SERVICES = 20;

    /** ব্রাউজার থেকে আসা তারিখ ("২০২৬-১১-১৬" ছাঁদে) ঠিক হলে ও আজকের পরের না হলে সেটা, নইলে null। */
    public static function cleanDay(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) !== 1 || !checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return null;
        }

        return $value >= '2020-01-01' && $value <= date('Y-m-d') ? $value : null;
    }

    /** মাস ("২০২৬-১১" ছাঁদে) ঠিক হলে ও এই মাসের পরের না হলে সেটা, নইলে null। */
    public static function cleanMonth(mixed $value): ?string
    {
        if (!is_string($value) || preg_match('/^(\d{4})-(\d{2})$/', $value, $m) !== 1 || (int) $m[2] < 1 || (int) $m[2] > 12) {
            return null;
        }

        return $value >= '2020-01' && $value <= date('Y-m') ? $value : null;
    }

    /** তৃতীয় গ্রাফে সর্বোচ্চ কত দিনের পাল্লা। */
    public const MAX_RANGE_DAYS = 366;

    /**
     * তৃতীয় গ্রাফের "থেকে–পর্যন্ত": না দিলে গত ৭ দিন (গতকাল পর্যন্ত); উল্টো দিলে ঠিক করে দেয়; পাল্লা MAX_RANGE_DAYS-এর বেশি হলে শেষ থেকে গোনা হয়।
     *
     * @return array{0:string,1:string} [থেকে, পর্যন্ত]
     */
    public static function range(?string $from, ?string $to): array
    {
        $to ??= date('Y-m-d', strtotime('-1 day'));
        $from ??= date('Y-m-d', strtotime('-7 days'));
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        $earliest = date('Y-m-d', strtotime($to . ' -' . (self::MAX_RANGE_DAYS - 1) . ' days'));

        return [max($from, $earliest), $to];
    }

    /**
     * ড্যাশবোর্ডের তিন গ্রাফের তথ্য: একটি দিন (না দিলে গতকাল), একটি মাস (না দিলে গত মাস) ও একটি পাল্লা (না দিলে গত ৭ দিন, গতকাল পর্যন্ত) সেবা অনুযায়ী।
     *
     * @param string|null $day   "Y-m-d" (cleanDay দিয়ে যাচাই করা)
     * @param string|null $month "Y-m"   (cleanMonth দিয়ে যাচাই করা)
     * @param string|null $from  "Y-m-d" পাল্লার শুরু (cleanDay দিয়ে যাচাই করা)
     * @param string|null $to    "Y-m-d" পাল্লার শেষ
     * @return array{yesterday:array{date:string,visits:int,clicks:int},month:array{first:string,visits:int,clicks:int},
     *               week:array{from:string,to:string,rows:list<array{name:string,visits:int,clicks:int}>,more:int}}
     */
    public static function charts(?string $day = null, ?string $month = null, ?string $from = null, ?string $to = null): array
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $day ??= $yesterday;
        $monthFirst = $month !== null ? $month . '-01' : date('Y-m-01', strtotime('first day of last month'));
        $monthLast = date('Y-m-t', strtotime($monthFirst));
        [$weekFrom, $weekTo] = self::range($from, $to);

        $names = [];
        foreach (ServiceRepository::all() as $service) {
            $names[$service['slug']] = $service['name'];
        }
        $weekVisits = WorkerRotation::serviceVisitsBetween($weekFrom, $weekTo);
        $weekClicks = ContactTrackingRepository::clicksBetween($weekFrom, $weekTo)['by_slug'];
        $rows = [];
        foreach (array_unique(array_merge(array_keys($weekVisits), array_keys($weekClicks))) as $slug) {
            if (isset($names[$slug])) {
                $rows[] = ['name' => $names[$slug], 'visits' => (int) ($weekVisits[$slug] ?? 0), 'clicks' => (int) ($weekClicks[$slug] ?? 0)];
            }
        }
        usort($rows, static fn (array $a, array $b): int => ($b['visits'] + $b['clicks']) <=> ($a['visits'] + $a['clicks']));

        return [
            'yesterday' => [
                'date' => $day,
                'visits' => array_sum(WorkerRotation::serviceVisitsBetween($day, $day)),
                'clicks' => ContactTrackingRepository::clicksBetween($day, $day)['total'],
            ],
            'month' => [
                'first' => $monthFirst,
                'visits' => array_sum(WorkerRotation::serviceVisitsBetween($monthFirst, $monthLast)),
                'clicks' => ContactTrackingRepository::clicksBetween($monthFirst, $monthLast)['total'],
            ],
            'week' => [
                'from' => $weekFrom,
                'to' => $weekTo,
                'rows' => array_slice($rows, 0, self::WEEK_SERVICES),
                'more' => max(0, count($rows) - self::WEEK_SERVICES),
            ],
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
