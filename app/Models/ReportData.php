<?php

declare(strict_types=1);

namespace App\Models;

/**
 * অ্যাডমিনের রিপোর্ট (<admin>/reports): পাঁচটি রিপোর্টের টেবিল তৈরি করে। প্রতিটি ফেরত দেয়
 * ['head' => কলামের নাম, 'rows' => [['cells' => [...], 'type' => 'row'|'sub'|'total'], ...], 'summary' => লাইনের তালিকা]।
 * তারিখ-সীমা Y-m-d, দুই দিনসহ। প্রতিবেদনে সর্বোচ্চ MAX_ROWS সারি আসে (বেশি হলে summary-তে জানানো হয়)।
 */
final class ReportData
{
    public const MAX_ROWS = 2000;

    /** রিপোর্টের নাম => [শিরোনাম, এক লাইনের বিবরণ]। ক্রম = মেনুর ক্রম। */
    public const REPORTS = [
        'interest' => ['সেবা অনুযায়ী মানুষের আগ্রহ', 'কোন কোন সেবায় মানুষ আগ্রহ দেখিয়েছেন: পেইজ ভিজিট, নাম-মোবাইল দিয়ে আগ্রহ, ফোন ও ইমেইল দেখা।'],
        'checked' => ['কোন কর্মীর ফোন-ইমেইল দেখা হয়েছে', 'সেবা অনুযায়ী: কর্মী, তাঁর এলাকা, আর যিনি দেখেছেন তাঁর নাম ও মোবাইল।'],
        'jobs' => ['কাজের বিজ্ঞপ্তি কতগুলো ও কী কী', 'কতগুলো কাজ প্রকাশ পেয়েছে, কোন সেবার, কে দিয়েছেন, কোথাকার।'],
        'job-inquiries' => ['কাজের বিজ্ঞপ্তিতে কারা খোঁজ নিয়েছেন', 'প্রতিটি সেবার নিচে সারাংশ, শেষে মোট; অনুসন্ধানকারীর নাম ও মোবাইলসহ।'],
        'registrations' => ['পেশাজীবীর নিবন্ধন', 'কতজন নিবন্ধন করেছেন, কতজন অনুমোদিত, অপেক্ষায় বা বাতিল।'],
    ];

    /** @var array<int,array<string,mixed>|null> */
    private static array $workers = [];

    /**
     * @return array{head:list<string>,rows:list<array{cells:list<string>,type:string}>,summary:list<string>}
     */
    public static function build(string $id, string $from, string $to): array
    {
        return match ($id) {
            'interest' => self::interest($from, $to),
            'checked' => self::checked($from, $to),
            'jobs' => self::jobs($from, $to),
            'job-inquiries' => self::jobInquiries($from, $to),
            default => self::registrations($from, $to),
        };
    }

    /** ১) সেবা অনুযায়ী আগ্রহ: ভিজিট, আগ্রহ (নাম-মোবাইল), ফোন, ইমেইল। */
    private static function interest(string $from, string $to): array
    {
        $names = self::serviceNames();
        $byName = array_flip(array_map('mb_strtolower', $names));
        $stat = [];
        $add = static function (string $slugOrLabel, string $field, int $n = 1) use (&$stat): void {
            $stat[$slugOrLabel] ??= ['visits' => 0, 'leads' => 0, 'phone' => 0, 'email' => 0, 'people' => []];
            $stat[$slugOrLabel][$field] += $n;
        };

        foreach (WorkerRotation::serviceVisitsBetween($from, $to) as $slug => $n) {
            $add((string) $slug, 'visits', (int) $n);
        }
        foreach (ContactTrackingRepository::leadsBetween($from, $to) as $lead) {
            $key = $lead['type'] === 'worker'
                ? (string) (self::worker($lead['target_id'])['service_slug'] ?? '')
                : (string) ($byName[mb_strtolower((string) (JobPostRepository::adminFind($lead['target_id'])['title'] ?? ''))] ?? '');
            if ($key === '') {
                $key = '*' . self::targetService($lead);
            }
            $add($key, 'leads');
            $add($key, $lead['kind'] === 'phone' ? 'phone' : 'email');
            $stat[$key]['people'][$lead['visitor_phone']] = true;
        }

        uasort($stat, static fn (array $a, array $b): int => [$b['leads'], $b['visits']] <=> [$a['leads'], $a['visits']]);
        $rows = [];
        $total = ['visits' => 0, 'leads' => 0, 'phone' => 0, 'email' => 0];
        foreach ($stat as $key => $s) {
            $label = str_starts_with((string) $key, '*') ? substr((string) $key, 1) : ($names[$key] ?? (string) $key);
            $rows[] = ['cells' => [$label, bn($s['visits']), bn($s['leads']), bn(count($s['people'])), bn($s['phone']), bn($s['email'])], 'type' => 'row'];
            foreach (['visits', 'leads', 'phone', 'email'] as $f) {
                $total[$f] += $s[$f];
            }
        }
        if ($rows !== []) {
            $rows[] = ['cells' => ['মোট', bn($total['visits']), bn($total['leads']), '', bn($total['phone']), bn($total['email'])], 'type' => 'total'];
        }

        return [
            'head' => ['সেবা', 'সেবার পেইজ দেখা', 'আগ্রহী (নাম-মোবাইল দিয়েছেন)', 'আলাদা দর্শক', 'ফোন দেখেছেন', 'ইমেইল দেখেছেন'],
            'rows' => $rows,
            'summary' => ['সেবার পেইজ দেখার গণনা শুরু হয়েছে ৩০ অক্টোবর ২০২৬ থেকে; তার আগের তারিখে "সেবার পেইজ দেখা" ০ দেখাবে।'],
        ];
    }

    /** ২) কোন কর্মীর ফোন/ইমেইল দেখা হয়েছে, সেবা অনুযায়ী। */
    private static function checked(string $from, string $to): array
    {
        $names = self::serviceNames();
        $list = [];
        foreach (ContactTrackingRepository::leadsBetween($from, $to, 'worker') as $lead) {
            $w = self::worker($lead['target_id']);
            $slug = (string) ($w['service_slug'] ?? '');
            $service = $slug !== '' ? ($names[$slug] ?? $slug) : ((string) ($w['service_custom'] ?? '') !== '' ? (string) $w['service_custom'] : '—');
            $list[] = [
                'service' => $service,
                'at' => $lead['at'],
                'cells' => [
                    bn_datetime(strtotime($lead['at'])), $service,
                    $w !== null ? (string) $w['name'] : '(কর্মী পাওয়া যায়নি)', $w !== null ? self::phone((string) ($w['phone'] ?? '')) : '',
                    $w !== null ? self::area($w) : '', $lead['kind'] === 'phone' ? 'ফোন' : 'ইমেইল',
                    $lead['visitor_name'], format_phone($lead['visitor_phone']),
                ],
            ];
        }
        usort($list, static fn (array $a, array $b): int => [$a['service'], $a['at']] <=> [$b['service'], $b['at']]);
        $rows = array_map(static fn (array $r): array => ['cells' => $r['cells'], 'type' => 'row'], $list);
        if ($rows !== []) {
            $rows[] = ['cells' => ['মোট ' . bn(count($rows)) . 'বার দেখা হয়েছে', '', '', '', '', '', '', ''], 'type' => 'total'];
        }

        return [
            'head' => ['সময়', 'সেবা', 'কর্মীর নাম', 'কর্মীর ফোন', 'এলাকা', 'কী দেখেছেন', 'দর্শকের নাম', 'দর্শকের মোবাইল'],
            'rows' => self::cap($rows, $summary),
            'summary' => $summary,
        ];
    }

    /** ৩) প্রকাশ পাওয়া কাজের বিজ্ঞপ্তি। */
    private static function jobs(string $from, string $to): array
    {
        $rows = [];
        $perDay = [];
        $all = JobPostRepository::adminList('', '', 5000, 0)['rows'];
        usort($all, static fn (array $a, array $b): int => strcmp((string) ($a['created_at'] ?? ''), (string) ($b['created_at'] ?? '')));
        foreach ($all as $job) {
            $day = substr((string) ($job['created_at'] ?? ''), 0, 10);
            if ($day < $from || $day > $to) {
                continue;
            }
            $perDay[$day] = ($perDay[$day] ?? 0) + 1;
            $rows[] = ['cells' => [
                bn_datetime(strtotime((string) $job['created_at'])), (string) $job['title'], (string) $job['contact_name'],
                self::phone((string) $job['phone']), self::area($job), (string) ($job['status'] ?? 'approved') === 'approved' ? 'প্রকাশিত' : 'লুকানো',
            ], 'type' => 'row'];
        }
        $summary = [];
        if ($rows !== []) {
            $summary[] = 'মোট ' . bn(count($rows)) . 'টি কাজের বিজ্ঞপ্তি এসেছে।';
            foreach ($perDay as $day => $n) {
                $summary[] = self::dayLabel($day) . ': ' . bn($n) . 'টি';
            }
        }

        return ['head' => ['সময়', 'কাজ (সেবা)', 'বিজ্ঞপ্তিদাতার নাম', 'ফোন', 'এলাকা', 'অবস্থা'], 'rows' => self::cap($rows, $summary), 'summary' => $summary];
    }

    /** ৪) কাজের বিজ্ঞপ্তিতে কারা খোঁজ নিয়েছেন: প্রতিটি সেবার নিচে সারাংশ, শেষে মোট। */
    private static function jobInquiries(string $from, string $to): array
    {
        $groups = [];
        foreach (ContactTrackingRepository::leadsBetween($from, $to, 'job') as $lead) {
            $job = JobPostRepository::adminFind($lead['target_id']);
            $title = $job !== null ? (string) $job['title'] : '(মোছা বা পাওয়া যায়নি)';
            $groups[$title][] = ['cells' => [
                bn_datetime(strtotime($lead['at'])), $title,
                $job !== null ? (string) $job['contact_name'] : '', $job !== null ? self::phone((string) $job['phone']) : '', $job !== null ? self::area($job) : '',
                $lead['visitor_name'], format_phone($lead['visitor_phone']), $lead['kind'] === 'phone' ? 'ফোন' : 'ইমেইল',
            ], 'type' => 'row'];
        }
        ksort($groups);
        $rows = [];
        $count = 0;
        foreach ($groups as $title => $items) {
            foreach ($items as $item) {
                $rows[] = $item;
            }
            $rows[] = ['cells' => ['সারাংশ — ' . $title, 'মোট ' . bn(count($items)) . ' বার খোঁজ নেওয়া হয়েছে', '', '', '', '', '', ''], 'type' => 'sub'];
            $count += count($items);
        }
        if ($rows !== []) {
            $rows[] = ['cells' => ['মোট সারাংশ', bn($count) . ' বার খোঁজ, ' . bn(count($groups)) . 'টি সেবা/কাজে', '', '', '', '', '', ''], 'type' => 'total'];
        }

        return ['head' => ['সময়', 'কাজ (সেবা)', 'বিজ্ঞপ্তিদাতার নাম', 'ফোন', 'এলাকা', 'অনুসন্ধানকারীর নাম', 'অনুসন্ধানকারীর মোবাইল', 'কী দেখেছেন'], 'rows' => self::cap($rows, $summary), 'summary' => $summary];
    }

    /** ৫) পেশাজীবীর নিবন্ধন: দিন অনুযায়ী, এখনকার অবস্থাসহ। পুরনো সাইট থেকে আনা কর্মী গোনা হয় না। */
    private static function registrations(string $from, string $to): array
    {
        $days = [];
        foreach (UserRepository::registrationsBetween($from, $to) as $r) {
            $d = &$days[$r['date']];
            $d ??= ['all' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'suspended' => 0, 'gone' => 0];
            $d['all']++;
            $key = in_array($r['status'], ['reserved', 'trashed'], true) ? 'gone' : $r['status'];
            if (isset($d[$key])) {
                $d[$key]++;
            }
            unset($d);
        }
        ksort($days);
        $rows = [];
        $sum = ['all' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0, 'suspended' => 0, 'gone' => 0];
        foreach ($days as $day => $d) {
            $rows[] = ['cells' => [self::dayLabel($day), bn($d['all']), bn($d['approved']), bn($d['pending']), bn($d['rejected']), bn($d['suspended']), bn($d['gone'])], 'type' => 'row'];
            foreach ($sum as $k => $_) {
                $sum[$k] += $d[$k];
            }
        }
        // কিছু না থাকলেও মোট সারি (সব শূন্য) দেখানো, যাতে "আজ কেউ নিবন্ধন করেননি" স্পষ্ট বোঝা যায়
        $rows[] = ['cells' => ['মোট', bn($sum['all']), bn($sum['approved']), bn($sum['pending']), bn($sum['rejected']), bn($sum['suspended']), bn($sum['gone'])], 'type' => 'total'];

        return [
            'head' => ['তারিখ', 'নিবন্ধন করেছেন', 'অনুমোদিত', 'অপেক্ষায় (পেন্ডিং)', 'বাতিল', 'হোল্ডে', 'মোছা (সংরক্ষিত/ডাস্টবিন)'],
            'rows' => $rows,
            'summary' => ['অবস্থা এখনকার (আজকের) অবস্থা, নিবন্ধনের দিনের নয়। পুরনো সাইট থেকে আনা ৯৯৩ জন (প্রায় সবাই ২৪ অক্টোবরে) ধরা হয়নি; নতুন নিবন্ধন (ফর্মে বা অ্যাডমিনের হাতে যোগ করা) ধরা হয়েছে।'],
        ];
    }

    // ---- ভেতরের সহায়ক ----

    /** @return array<string,string> slug => সেবার নাম (বন্ধ সেবাসহ) */
    private static function serviceNames(): array
    {
        return array_column(ServiceAdmin::all(), 'name', 'slug');
    }

    /** @return array<string,mixed>|null */
    private static function worker(int $id): ?array
    {
        return self::$workers[$id] ??= UserRepository::findById($id);
    }

    /** আগ্রহ-গণনার জন্য: লিডের লক্ষ্যের সেবা/কাজের নাম। @param array{type:string,target_id:int} $lead */
    private static function targetService(array $lead): string
    {
        if ($lead['type'] === 'worker') {
            $w = self::worker($lead['target_id']);

            return (string) ($w['service_custom'] ?? '') !== '' ? (string) $w['service_custom'] : 'অন্য পেশা';
        }

        return (string) (JobPostRepository::adminFind($lead['target_id'])['title'] ?? 'কাজের বিজ্ঞপ্তি');
    }

    /** @param array<string,mixed> $row address, upazila_id, district_id, division_id */
    private static function area(array $row): string
    {
        $geo = GeoRepository::names((int) ($row['upazila_id'] ?? 0), (int) ($row['district_id'] ?? 0), (int) ($row['division_id'] ?? 0));

        $parts = array_map(static fn (string $x): string => trim((string) preg_replace('/\s+/u', ' ', $x)), [(string) ($row['address'] ?? ''), $geo['upazila'], $geo['district']]);

        return implode(', ', array_filter($parts, static fn (string $x): bool => $x !== ''));
    }

    private static function phone(string $phone): string
    {
        return $phone !== '' ? format_phone($phone) : '—';
    }

    /** "৩০ অক্টোবর ২০২৬" */
    public static function dayLabel(string $day): string
    {
        $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
        $t = strtotime($day);

        return bn((int) date('j', $t)) . ' ' . $months[(int) date('n', $t) - 1] . ' ' . bn((int) date('Y', $t));
    }

    /**
     * সারি MAX_ROWS-এর বেশি হলে কেটে দেয় (শেষের মোট-সারি রেখে) ও $summary-তে জানায়।
     *
     * @param list<array{cells:list<string>,type:string}> $rows
     * @param list<string>|null $summary
     * @return list<array{cells:list<string>,type:string}>
     */
    private static function cap(array $rows, ?array &$summary): array
    {
        $summary ??= [];
        if (count($rows) > self::MAX_ROWS) {
            $summary[] = 'সারি অনেক বেশি, তাই প্রথম ' . bn(self::MAX_ROWS) . 'টি দেখানো হলো। তারিখের সীমা ছোট করে দেখুন।';
            $rows = array_slice($rows, 0, self::MAX_ROWS);
        }

        return $rows;
    }
}
