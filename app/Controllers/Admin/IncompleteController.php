<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\IncompleteInfo;
use App\Models\UserRepository;

/**
 * "অসম্পূর্ণ তথ্য" (/admin/incomplete): যে কর্মীর তথ্য বাকি তাকে লাল চিহ্নে দেখায়, মোবাইল/ইমেইল/ঠিকানা এই পেইজেই বসানো যায়
 * এবং ইমেইল থাকলে "তথ্য পূর্ণ করুন" ইমেইল পাঠানো যায়। বাকি ঘর (এলাকা, অভিজ্ঞতা, পরিচিতি) সম্পাদনা-পেইজে। অনুমতি: "registrations"।
 */
final class IncompleteController
{
    private const PER_PAGE = 25;

    public function index(): void
    {
        $admin = AdminAuth::require('registrations');
        $filter = isset(IncompleteInfo::LABELS[$_GET['f'] ?? '']) ? (string) $_GET['f'] : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = UserRepository::incompleteList($filter, $q, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        header('Cache-Control: no-store');
        View::render('admin/incomplete', [
            'admin' => $admin,
            'title' => 'অসম্পূর্ণ তথ্য',
            'filter' => $filter,
            'q' => $q,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => UserRepository::incompleteCounts(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: id, phone / email / address (যেগুলোতে লেখা আছে শুধু সেগুলো বসে), back। */
    public function save(): void
    {
        $admin = AdminAuth::require('registrations');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        $this->checkCsrf($back);

        $member = $this->worker($back);
        $fields = [
            'phone' => normalize_phone(clean_input($_POST['phone'] ?? '', 20)),
            'email' => mb_strtolower(clean_input($_POST['email'] ?? '', 150)),
            'address' => clean_input($_POST['address'] ?? '', 250),
        ];
        $errors = [];
        if ($fields['phone'] !== '' && preg_match('/^01[3-9][0-9]{8}$/', $fields['phone']) !== 1) {
            $errors[] = 'মোবাইল নাম্বার ১১ সংখ্যার হতে হবে, যেমন 01712345678।';
        }
        if ($fields['email'] !== '' && filter_var($fields['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়।';
        }
        if ($fields['address'] !== '' && mb_strlen($fields['address']) < 2) {
            $errors[] = 'ঠিকানা অন্তত ২ অক্ষরের হতে হবে।';
        }
        if ($fields['phone'] === '' && $fields['email'] === '' && $fields['address'] === '') {
            $errors[] = 'বসানোর জন্য অন্তত একটা ঘরে লিখুন।';
        }
        if ($errors !== []) {
            $this->fail($back, implode(' ', $errors));
        }

        $result = UserRepository::fillMissing((int) $member['id'], $fields);
        if ($result !== 'ok') {
            $this->fail($back, [
                'duplicate' => 'এই মোবাইল নাম্বার আরেকজন কর্মীর আছে।',
                'duplicate_email' => 'এই ইমেইল আরেকজন কর্মীর আছে।',
            ][$result] ?? 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }

        $done = [];
        foreach (['phone' => 'মোবাইল', 'email' => 'ইমেইল', 'address' => 'ঠিকানা'] as $key => $label) {
            if ($fields[$key] !== '') {
                $done[] = $label;
            }
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'member_fill', $member['name'] . ' — ' . implode(', ', $done));
        $this->ok($back, $member['name'] . ' — ' . implode(', ', $done) . ' বসানো হয়েছে।');
    }

    /** POST: id, back — ইমেইল থাকলে কর্মীকে "তথ্য পূর্ণ করুন" ইমেইল পাঠায় (কোন তথ্য বাকি তার তালিকাসহ)। */
    public function mail(): void
    {
        $admin = AdminAuth::require('registrations');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        $this->checkCsrf($back);

        $member = $this->worker($back);
        $email = (string) ($member['email'] ?? '');
        $missing = IncompleteInfo::missing($member);
        if ($email === '') {
            $this->fail($back, 'এই কর্মীর ইমেইল নেই; ফোনে জানান।');
        }
        if ($missing === []) {
            $this->fail($back, 'এই কর্মীর সব তথ্য আছে।');
        }

        $list = '';
        foreach ($missing as $key) {
            $list .= '• ' . IncompleteInfo::LABELS[$key] . "\n";
        }
        $base = AdminAuth::baseUrl();
        $body = 'প্রিয় ' . honorific_name($member['gender'] ?? null, (string) $member['name']) . ",\n\njorurikaj.com-এ আপনার প্রোফাইলের কিছু তথ্য বাকি আছে:\n\n" . $list
            . "\nলগইন করে প্রোফাইল পেইজে তথ্যগুলো পূরণ করে সংরক্ষণ করুন। এতে সাধারণ মানুষ আপনাকে সহজে খুঁজে পাবেন।\n\n"
            . 'লগইন করুন: ' . $base . "/login\n"
            . "পাসওয়ার্ড ভুলে গেলে লগইন পেইজের \"পাসওয়ার্ড মনে নেই\" লিংকে ক্লিক করুন, ইমেইলে কোড যাবে।\n\n"
            . 'প্রয়োজনে হেল্পলাইনে কল করুন: ' . content('today.post.helpline') . "\n\n— jorurikaj.com";

        if (!AdminAuth::mail($email, 'আপনার তথ্য পূর্ণ করুন — jorurikaj.com', $body)) {
            $this->fail($back, 'ইমেইল পাঠানো যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'member_fill_mail', (string) $member['name']);
        $this->ok($back, $member['name'] . '-কে "তথ্য পূর্ণ করুন" ইমেইল পাঠানো হয়েছে।');
    }

    /**
     * POST-এর id-র অনুমোদিত কর্মী; না পেলে ফিরে যায়।
     *
     * @return array<string,mixed>
     */
    private function worker(string $back): array
    {
        $member = UserRepository::findById((int) ($_POST['id'] ?? 0));
        if ($member === null || $member['role'] !== 'worker') {
            $this->fail($back, 'কর্মীকে পাওয়া যায়নি।');
        }

        return $member;
    }

    private function checkCsrf(string $back): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ, নইলে পেইজের শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/incomplete';

        return $path === $own || preg_match('#^' . preg_quote($own, '#') . '\?[A-Za-z0-9_=&%.\-]*$#', $path) === 1 ? $path : $own;
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }

    private function ok(string $path, string $message): never
    {
        Session::flash('admin_ok', $message);
        redirect($path);
    }
}
