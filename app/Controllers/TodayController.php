<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\ContactTrackingRepository;
use App\Models\GeoRepository;
use App\Models\JobPostRepository;
use App\Models\ServiceRepository;

/** "আজকের প্রকাশিত": কাজের তালিকা (/today), লুকানো ফোন/ইমেইল দেখানো এবং কাজ জানানোর ফর্ম (/today/post)। */
final class TodayController
{
    /** আজকের প্রকাশিত বিজ্ঞপ্তির তালিকা (/today). @param array<string,mixed> $meta */
    public function index(array $meta = []): void
    {
        $jobs = [];
        foreach (JobPostRepository::listActive() as $job) {
            $where = array_filter([
                (string) $job['contact_name'],
                (string) $job['address'],
                $job['upazila'] !== '' ? 'থানা/উপজেলা- ' . $job['upazila'] : '',
                $job['district'] !== '' ? $job['district'] . ' জেলা' : '',
                $job['division'] !== '' ? 'বিভাগ - ' . $job['division'] : '',
            ], static fn (string $part): bool => $part !== '');
            $job['contact_line'] = implode(', ', $where) . '।';
            $job['date_bn'] = bn_date((string) $job['start_date']);
            $job['time_bn'] = bn_time((string) $job['created_at']);
            $jobs[] = $job;
        }

        View::render('pages/today-list', [
            'bodyClass' => 'page-today',
            'title' => 'আজকের প্রকাশিত কাজের তালিকা — Jorurikaj.com',
            'description' => content('today.list.intro'),
            'heading' => content('today.list.title'),
            'jobs' => $jobs,
            'token' => Security::csrfToken(),
        ]);
    }

    /**
     * একটা বিজ্ঞপ্তির লুকানো ফোন নাম্বার অথবা ইমেইল (JSON), দুই ধাপে:
     *  step=click  প্রতিটি ক্লিক একটা "কল" হিসেবে গোনা হয়; এই দর্শক এই বিজ্ঞপ্তির জন্য আগেই নাম + মোবাইল
     *              দিয়ে থাকলে সঙ্গে সঙ্গে যোগাযোগ দেখায়, নইলে {need_lead: true} (পপ-আপ খোলে);
     *  step=lead   দর্শক "আগ্রহী" বলে নাম + মোবাইল পাঠালেন: সংরক্ষণ হয়, অ্যাডমিন ইমেইল পান,
     *              যোগাযোগ দেখানো হয়।
     * চাওয়ার সংখ্যা দর্শক-প্রতি সীমিত, তাই একসঙ্গে অনেক নাম্বার সংগ্রহ করা যায় না।
     *
     * @param array<string,mixed> $meta
     */
    public function contact(array $meta = []): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');

        $reply = static function (array $body, int $status = 200): void {
            http_response_code($status);
            echo json_encode($body, JSON_UNESCAPED_UNICODE);
        };

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $reply(['error' => 'csrf'], 403);

            return;
        }
        if (RateLimiter::tooMany('job_contact', 60, 3600)) {
            $reply(['error' => 'limit'], 429);

            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $kind = (string) ($_POST['kind'] ?? '');
        $step = (string) ($_POST['step'] ?? 'click');
        $contact = in_array($kind, ['phone', 'email'], true) ? JobPostRepository::contactOf($id) : null;
        $value = $contact === null ? '' : ($kind === 'phone' ? $contact['phone'] : $contact['email']);
        if ($contact === null || $value === '') {
            $reply(['error' => 'none'], 404);

            return;
        }

        $show = static fn (): array => $kind === 'phone'
            ? ['kind' => 'phone', 'value' => format_phone($value), 'href' => 'tel:+88' . normalize_phone($value)]
            : ['kind' => 'email', 'value' => $value, 'href' => 'mailto:' . $value];

        if ($step === 'click') {
            ContactTrackingRepository::click('job', $id, $kind);
            if (!empty($_SESSION['contact_ok']['job'][$id])) {
                $reply($show());
            } else {
                $reply(['need_lead' => true]);
            }

            return;
        }

        // ধাপ = lead
        if (RateLimiter::tooMany('job_lead', 10, 3600)) {
            $reply(['error' => 'limit'], 429);

            return;
        }
        $name = $this->clean($_POST['name'] ?? '', 100);
        $phone = normalize_phone($this->clean($_POST['phone'] ?? '', 20));
        if ($name === '' || mb_strlen($name) < 2) {
            $reply(['error' => 'invalid', 'message' => 'আপনার নাম লিখুন।'], 422);

            return;
        }
        if (!preg_match('/^01[3-9][0-9]{8}$/', $phone)) {
            $reply(['error' => 'invalid', 'message' => 'মোবাইল নাম্বারটি সঠিক নয়। ১১ সংখ্যার নাম্বার দিন, যেমন 01712345678।'], 422);

            return;
        }
        if (!ContactTrackingRepository::lead('job', $id, $kind, $name, $phone)) {
            $reply(['error' => 'save', 'message' => 'দুঃখিত, এখন সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'], 500);

            return;
        }
        $_SESSION['contact_ok']['job'][$id] = true;

        // অ্যাডমিন সঙ্গে সঙ্গে জানতে পান (একই তথ্য অ্যাডমিন প্যানেলের জন্যও জমা থাকে)
        $notice = null;
        foreach (JobPostRepository::listActive(500) as $job) {
            if ((int) $job['id'] === $id) {
                $notice = $job;
            }
        }
        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = "কেউ একটি কাজের " . ($kind === 'phone' ? 'ফোন নাম্বার' : 'ইমেইল') . " দেখতে চেয়েছেন — jorurikaj.com\n\n"
            . 'কাজ: ' . ($notice['title'] ?? ('#' . $id)) . "\n"
            . 'কাজের মালিক: ' . ($notice['contact_name'] ?? '') . "\n\n"
            . "যিনি আগ্রহ দেখিয়েছেন:\nনাম: {$name}\nমোবাইল: {$phone}\n\n"
            . "(প্রয়োজনে কাজের মালিককে ফোন করে জেনে নিন এই নামে কেউ খোঁজ নিয়েছেন কিনা।)\n";
        if (!Mailer::send($to, 'কাজের যোগাযোগ দেখতে চেয়েছেন: ' . ($notice['title'] ?? ('#' . $id)), $body)) {
            Logger::error('Contact-lead e-mail could not be sent');
        }

        $reply($show());
    }

    /** @param array<string,mixed> $meta */
    public function postForm(array $meta = []): void
    {
        $done = Session::pull('job_posted', null);

        View::render('pages/today-post', [
            'bodyClass' => 'page-post',
            'title' => 'কাজ সম্পর্কে জানান — Jorurikaj.com',
            'description' => content('today.post.intro'),
            'heading' => content('today.post.title'),
            'done' => $done,
            'errors' => Session::pull('job_errors', []),
            'old' => Session::pull('job_old', []),
            'geo' => GeoRepository::all(),
            'services' => ServiceRepository::all(),
        ]);
    }

    /** @param array<string,mixed> $meta */
    public function postStore(array $meta = []): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail(['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }

        // লুকানো "website" ঘর: আসল মানুষ ফাঁকা রাখে, স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect('/today/post');
        }

        if (RateLimiter::tooMany('job_post', 5, 3600)) {
            $this->fail(['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }

        $data = [
            'title' => $this->clean($_POST['title'] ?? '', 120),
            'contact_name' => $this->clean($_POST['contact_name'] ?? '', 100),
            'contact_gender' => clean_gender($_POST['contact_gender'] ?? null),
            'phone' => normalize_phone($this->clean($_POST['phone'] ?? '', 20)),
            'email' => $this->clean($_POST['email'] ?? '', 150),
            'address' => $this->clean($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
            'start_date' => $this->clean($_POST['start_date'] ?? '', 10),
            'end_date' => $this->clean($_POST['end_date'] ?? '', 10),
            'details' => $this->clean($_POST['details'] ?? '', 3000, true),
            'consent' => !empty($_POST['consent']) ? 1 : 0,
        ];

        // সেবার তালিকায় নেই এমন কাজের ধরনে চিহ্ন দেওয়া হয়, যাতে অ্যাডমিন বানান যাচাই করতে পারেন
        $known = array_column(ServiceRepository::all(), 'name');
        $data['title_custom'] = in_array($data['title'], $known, true) ? 0 : 1;

        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->fail($errors, $data);
        }

        $data['consent_at'] = date('Y-m-d H:i:s');
        if (!JobPostRepository::create($data)) {
            $this->fail(['দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], $data);
        }

        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = "নতুন কাজের তথ্য প্রকাশিত হয়েছে — jorurikaj.com\n\n"
            . "কাজের ধরন: {$data['title']}\nনাম: {$data['contact_name']}\nমোবাইল: {$data['phone']}\n"
            . "ইমেইল: {$data['email']}\nঠিকানা: {$data['address']}\n"
            . "প্রচারের তারিখ: {$data['start_date']}" . ($data['end_date'] !== '' ? " থেকে {$data['end_date']}" : '') . "\n\n"
            . "{$data['details']}\n";
        $replyTo = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : null;
        if (!Mailer::send($to, 'নতুন কাজের তথ্য: ' . $data['title'], $body, $replyTo)) {
            // আগেই সংরক্ষিত (অ্যাডমিন প্যানেলের জন্য), তাই কিছু হারাবে না
            Logger::error('Job post e-mail could not be sent');
        }

        // বার্তা-বক্সে জানানোকারীর নাম ও এলাকা দেখানো হয়
        $geo = GeoRepository::names($data['upazila_id'], $data['district_id'], $data['division_id']);
        Session::flash('job_posted', [
            'name' => honorific_name($data['contact_gender'], $data['contact_name']),
            'area' => implode(', ', array_filter([$geo['upazila'], $geo['district'], $geo['division']])),
        ]);
        redirect('/today/post');
    }

    /**
     * @param array<string,mixed> $d
     * @return list<string>
     */
    private function validate(array $d): array
    {
        $errors = [];
        if ($d['title'] === '') {
            $errors[] = 'কাজের ধরন বাছাই করুন, অথবা নিজে লিখুন।';
        }
        if ($d['contact_name'] === '') {
            $errors[] = 'আপনার নাম লিখুন।';
        }
        if ($d['contact_gender'] === '') {
            $errors[] = 'জেন্ডার বাছাই করুন।';
        }
        if ($d['phone'] === '') {
            $errors[] = 'মোবাইল নাম্বার লিখুন।';
        } elseif (!preg_match('/^01[3-9][0-9]{8}$/', $d['phone'])) {
            $errors[] = 'মোবাইল নাম্বারটি সঠিক নয়। ১১ সংখ্যার নাম্বার দিন, যেমন 01712345678।';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়।';
        }
        if ($d['address'] === '') {
            $errors[] = 'কাজের ঠিকানা লিখুন।';
        }
        if ($d['division_id'] === 0 || $d['district_id'] === 0 || $d['upazila_id'] === 0) {
            $errors[] = 'বিভাগ, জেলা এবং উপজেলা / থানা নির্বাচন করুন।';
        } elseif (!GeoRepository::isConsistent($d['division_id'], $d['district_id'], $d['upazila_id'])) {
            $errors[] = 'নির্বাচিত বিভাগ, জেলা ও উপজেলা / থানা একে অপরের সঙ্গে মিলছে না। আবার নির্বাচন করুন।';
        }

        $today = date('Y-m-d');
        $start = $this->validDate($d['start_date']);
        if ($d['start_date'] === '') {
            $errors[] = 'প্রচারের তারিখ দিন।';
        } elseif ($start === null) {
            $errors[] = 'প্রচারের তারিখটি সঠিক নয়।';
        } elseif ($start < $today) {
            $errors[] = 'প্রচারের তারিখ আজকের বা পরের কোনো দিন হতে হবে।';
        }
        if ($d['end_date'] !== '') {
            $end = $this->validDate($d['end_date']);
            if ($end === null) {
                $errors[] = 'প্রচার বন্ধের তারিখটি সঠিক নয়।';
            } elseif ($start !== null && $end < $start) {
                $errors[] = 'প্রচার বন্ধের তারিখ প্রচারের তারিখের আগে হতে পারে না।';
            }
        }
        if (mb_strlen($d['details']) < 10) {
            $errors[] = 'কাজ সম্পর্কে বিস্তারিত লিখুন (কমপক্ষে ১০ অক্ষর)।';
        }
        if (!$d['consent']) {
            $errors[] = content('consent.error');
        }

        return $errors;
    }

    /** $value সত্যিকারের তারিখ হলে "Y-m-d" ফেরত দেয়, নইলে null। */
    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    private function clean(mixed $value, int $max, bool $multiline = false): string
    {
        $value = is_string($value) ? $value : '';
        $value = (string) preg_replace($multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u', '', str_replace("\r\n", "\n", $value));

        return mb_substr(trim($value), 0, $max);
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $old
     */
    private function fail(array $errors, array $old = []): never
    {
        Session::flash('job_errors', $errors);
        Session::flash('job_old', $old);
        redirect('/today/post#form');
    }
}
