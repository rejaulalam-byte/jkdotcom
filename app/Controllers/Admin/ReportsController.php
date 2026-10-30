<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\View;
use App\Models\ReportData;

/**
 * রিপোর্ট (<admin>/reports, অনুমতি "reports"): প্রথমে রিপোর্টের বাক্সগুলো (প্রতিটিতে শিরোনাম); ক্লিক করলে আলাদা পেইজে টেবিল
 * (<admin>/reports/<নাম>), তারিখ বেছে (থেকে-পর্যন্ত; শুরুতে আজ) দেখা যায়, "বন্ধ করুন" চাপলে বাক্সের পেইজে ফেরে।
 */
final class ReportsController
{
    /** তারিখ-সীমার সর্বোচ্চ দিন (এর বেশি হলে ছোট করা হয়)। */
    private const MAX_DAYS = 366;

    public function index(): void
    {
        $admin = AdminAuth::require('reports');
        header('Cache-Control: no-store');
        View::render('admin/reports', ['admin' => $admin, 'title' => 'রিপোর্ট', 'reports' => ReportData::REPORTS], 'layouts/admin');
    }

    /** @param array<string,mixed> $meta slug = রিপোর্টের নাম */
    public function show(array $meta = []): void
    {
        $admin = AdminAuth::require('reports');
        $id = (string) ($meta['slug'] ?? '');
        if (!isset(ReportData::REPORTS[$id])) {
            http_response_code(404);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'পাওয়া যায়নি', 'message' => 'এই রিপোর্ট পাওয়া যায়নি।'], 'layouts/admin');

            return;
        }

        [$from, $to, $note] = $this->range();
        $report = ReportData::build($id, $from, $to);
        if ($note !== null) {
            $report['summary'][] = $note;
        }
        header('Cache-Control: no-store');
        View::render('admin/report', [
            'admin' => $admin,
            'title' => ReportData::REPORTS[$id][0],
            'id' => $id,
            'from' => $from,
            'to' => $to,
            'today' => date('Y-m-d'),
            'report' => $report,
        ], 'layouts/admin');
    }

    /**
     * GET from / to (Y-m-d) থেকে তারিখ-সীমা; ভুল বা না থাকলে আজ। উল্টো হলে সোজা করা, MAX_DAYS-এর বেশি হলে ছোট করা।
     *
     * @return array{0:string,1:string,2:?string} [থেকে, পর্যন্ত, কিছু বদলালে জানানোর লেখা]
     */
    private function range(): array
    {
        $today = date('Y-m-d');
        $valid = static fn (string $d): bool => ($t = \DateTimeImmutable::createFromFormat('!Y-m-d', $d)) !== false && $t->format('Y-m-d') === $d;
        $from = (string) ($_GET['from'] ?? '');
        $to = (string) ($_GET['to'] ?? '');
        $from = $valid($from) ? $from : ($valid($to) ? $to : $today);
        $to = $valid($to) ? $to : $from;
        // ভবিষ্যতের তারিখ নেই: আজকের পরে কিছু ঘটেনি
        $from = min($from, $today);
        $to = min($to, $today);
        $note = null;
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }
        if (strtotime($to) - strtotime($from) > self::MAX_DAYS * 86400) {
            $from = date('Y-m-d', strtotime($to . ' -' . self::MAX_DAYS . ' days'));
            $note = 'সর্বোচ্চ ' . bn(self::MAX_DAYS) . ' দিনের রিপোর্ট দেখানো যায়, তাই শুরুর তারিখ এগিয়ে আনা হয়েছে।';
        }

        return [$from, $to, $note];
    }
}
