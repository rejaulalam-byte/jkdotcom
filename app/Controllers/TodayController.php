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
use App\Models\GeoRepository;
use App\Models\JobPostRepository;
use App\Models\ServiceRepository;

/** "আজকের প্রকাশিত": the form where a visitor posts the work they need done (/today/post). */
final class TodayController
{
    /** @param array<string,mixed> $meta */
    public function postForm(array $meta = []): void
    {
        $done = (bool) Session::pull('job_posted', false);

        View::render('pages/today-post', [
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

        // Hidden "website" field: real people leave it empty, spam bots fill it in
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect('/today/post');
        }

        if (RateLimiter::tooMany('job_post', 5, 3600)) {
            $this->fail(['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }

        $data = [
            'title' => $this->clean($_POST['title'] ?? '', 120),
            'contact_name' => $this->clean($_POST['contact_name'] ?? '', 100),
            'phone' => $this->digits($this->clean($_POST['phone'] ?? '', 20)),
            'email' => $this->clean($_POST['email'] ?? '', 150),
            'address' => $this->clean($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
            'start_date' => $this->clean($_POST['start_date'] ?? '', 10),
            'end_date' => $this->clean($_POST['end_date'] ?? '', 10),
            'details' => $this->clean($_POST['details'] ?? '', 3000, true),
        ];

        // a kind of work that is not in the services list is marked, so the admin can check its spelling
        $known = array_column(ServiceRepository::all(), 'name');
        $data['title_custom'] = in_array($data['title'], $known, true) ? 0 : 1;

        $errors = $this->validate($data);
        if ($errors !== []) {
            $this->fail($errors, $data);
        }

        if (!JobPostRepository::create($data)) {
            $this->fail(['দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], $data);
        }

        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = "নতুন কাজের তথ্য জমা পড়েছে (অনুমোদনের অপেক্ষায়) — jorurikaj.com\n\n"
            . "কাজের ধরন: {$data['title']}\nনাম: {$data['contact_name']}\nমোবাইল: {$data['phone']}\n"
            . "ইমেইল: {$data['email']}\nঠিকানা: {$data['address']}\n"
            . "প্রচারের তারিখ: {$data['start_date']}" . ($data['end_date'] !== '' ? " থেকে {$data['end_date']}" : '') . "\n\n"
            . "{$data['details']}\n";
        $replyTo = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : null;
        if (!Mailer::send($to, 'নতুন কাজের তথ্য: ' . $data['title'], $body, $replyTo)) {
            // Already saved (admin panel), so nothing is lost
            Logger::error('Job post e-mail could not be sent');
        }

        Session::flash('job_posted', true);
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
        if ($d['phone'] === '') {
            $errors[] = 'মোবাইল নাম্বার লিখুন।';
        } elseif (!preg_match('/^(?:\+?88)?01[3-9][0-9]{8}$/', $d['phone'])) {
            $errors[] = 'মোবাইল নাম্বারটি সঠিক নয়। ১১ সংখ্যার নাম্বার দিন, যেমন ০১৭XXXXXXXX।';
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

        return $errors;
    }

    /** Returns "Y-m-d" when $value is a real calendar date, otherwise null. */
    private function validDate(string $value): ?string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $value : null;
    }

    /** Bangla digits -> English digits, spaces/dashes removed (for phone numbers). */
    private function digits(string $value): string
    {
        $value = strtr($value, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);

        return (string) preg_replace('/[\s\-()]/', '', $value);
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
