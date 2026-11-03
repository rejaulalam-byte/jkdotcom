<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\GeoRepository;
use App\Models\ManpowerReport;
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

        if ($id === 'manpower') {
            $this->manpower($admin);

            return;
        }

        [$from, $to, $note] = $this->range();
        $report = ReportData::build($id, $from, $to);
        if ($note !== null) {
            $report['summary'][] = $note;
        }
        if (($_GET['csv'] ?? '') === '1') {
            $this->csv($admin, $id, $from, $to, $report);

            return;
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
     * লোকবলের রিপোর্ট: তারিখ নয়। ফর্মে পাঁচটি ঘর (বিভাগ / জেলা / উপজেলা-থানা / সেবা / পেশাজীবীর নাম), একটাই "দেখান" বোতাম।
     * বাছাইগুলো একসঙ্গে খাটে (বিভাগ → জেলা → উপজেলা একটার ভেতরে আরেকটা); কিছু না বাছলে খালি টেবিল।
     *
     * @param array<string,mixed> $admin
     */
    private function manpower(array $admin): void
    {
        $filters = [];
        foreach (array_keys(ManpowerReport::DIMENSIONS) as $dim) {
            $filters[$dim] = (string) ($_GET[$dim] ?? '');
        }
        $searched = isset($_GET['go']);
        $chosen = $searched && count(array_filter($filters, static fn (string $v): bool => $v !== '')) > 0;
        $report = ManpowerReport::build($searched ? $filters : []);
        if ($searched && !$chosen) {
            $report['summary'][] = 'কোনো ঘর বাছাই করা হয়নি। অন্তত একটা ঘরে "সকল ..." বা নির্দিষ্ট নাম বেছে "দেখান" চাপুন।';
        }
        if ($chosen && ($_GET['csv'] ?? '') === '1') {
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'report_csv', ReportData::REPORTS['manpower'][0]);
            $this->csvOut('report-manpower-' . date('Y-m-d') . '.csv', $report);

            return;
        }

        header('Cache-Control: no-store');
        View::render('admin/report-manpower', [
            'admin' => $admin,
            'title' => ReportData::REPORTS['manpower'][0],
            'filters' => $filters,
            'chosen' => $chosen,
            'report' => $report,
            'geo' => GeoRepository::all(),
            'services' => ManpowerReport::serviceNames(),
        ], 'layouts/admin');
    }
    /**
     * টেবিলটা CSV হিসেবে নামায় (এক্সেলে বাংলা ঠিক দেখাতে শুরুতে BOM)। সূত্র হিসেবে চলে এমন শুরু (=, +, -, @) আটকাতে সামনে ' বসে।
     *
     * @param array<string,mixed> $admin
     * @param array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $report
     */
    private function csv(array $admin, string $id, string $from, string $to, array $report): void
    {
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'report_csv', ReportData::REPORTS[$id][0] . ' ' . $from . ' – ' . $to);
        $this->csvOut('report-' . $id . '-' . $from . '_' . $to . '.csv', $report);
    }

    /**
     * @param array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>} $report
     */
    private function csvOut(string $file, array $report): void
    {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Cache-Control: no-store');
        $safe = static fn (string $v): string => preg_match('/^[=+\-@]/', $v) === 1 ? "'" . $v : $v;
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_map($safe, $report['head']));
        foreach ($report['rows'] as $row) {
            fputcsv($out, array_map($safe, $row['cells']));
        }
        fclose($out);
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
