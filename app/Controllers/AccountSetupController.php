<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\UserRepository;

/**
 * পুরনো সাইট থেকে আনা কর্মীর প্রথম লগইনের "তথ্য পূর্ণ করুন" ধাপ (/account/setup):
 * নতুন পাসওয়ার্ড, ইমেইল, ঠিকানা (মোবাইল না থাকলে মোবাইলও) ও সম্মতি। শেষ না হওয়া পর্যন্ত অন্য পেইজে যাওয়া যায় না
 * (লগইন ও প্রোফাইল এখানে পাঠায়)। সাধারণ নতুন সদস্যের জন্য এই ধাপ নেই।
 */
final class AccountSetupController
{
    public function form(): void
    {
        $user = $this->member();

        View::render('pages/setup', [
            'bodyClass' => 'page-login',
            'title' => content('setup.crumb') . ' — Jorurikaj.com',
            'heading' => content('setup.banner'),
            'user' => $user,
            'old' => Session::pull('setup_old', []),
            'errors' => Session::pull('setup_errors', []),
        ]);
    }

    public function store(): void
    {
        $user = $this->member();

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail(['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect('/account/setup');
        }
        if (RateLimiter::tooMany('setup_' . $user['id'], 20, 3600)) {
            $this->fail(['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }

        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $needsPhone = (string) ($user['phone'] ?? '') === '';
        $data = [
            'email' => mb_strtolower(clean_input($_POST['email'] ?? '', 150)),
            'address' => clean_input($_POST['address'] ?? '', 250),
            'phone' => $needsPhone ? normalize_phone(clean_input($_POST['phone'] ?? '', 20)) : '',
        ];

        $errors = [];
        // bcrypt ৭২ বাইটের পরের অংশ উপেক্ষা করে, তাই বেশি লম্বা পাসওয়ার্ড আটকানো হয়
        if (mb_strlen($new) < 8) {
            $errors[] = 'নতুন পাসওয়ার্ড অবশ্যই ৮ (আট) অক্ষর বা তার বেশি হতে হবে।';
        } elseif (strlen($new) > 72) {
            $errors[] = 'নতুন পাসওয়ার্ড সর্বোচ্চ ৭২ বাইট (প্রায় ৭০ ইংরেজি অক্ষর) হতে পারে।';
        } elseif ($new === (string) $user['phone'] || ($needsPhone && $new === $data['phone'])) {
            $errors[] = 'মোবাইল নাম্বারকে পাসওয়ার্ড হিসেবে ব্যবহার করবেন না।';
        } elseif ($new !== $confirm) {
            $errors[] = 'দুইবার লেখা নতুন পাসওয়ার্ড একই নয়।';
        }
        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়। সঠিক ইমেইল লিখুন।';
        }
        if ($needsPhone && preg_match('/^01[3-9][0-9]{8}$/', $data['phone']) !== 1) {
            $errors[] = 'মোবাইল নাম্বার ১১ সংখ্যার হতে হবে, যেমন 01712345678।';
        }
        if (mb_strlen($data['address']) < 2) {
            $errors[] = 'বর্তমান ঠিকানা লিখুন।';
        }
        if (empty($_POST['consent'])) {
            $errors[] = content('consent.error');
        }
        if ($errors !== []) {
            $this->fail($errors, ['email' => $data['email'], 'address' => $data['address'], 'phone' => $data['phone']]);
        }

        $result = UserRepository::completeSetup((int) $user['id'], $data + ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        if ($result !== 'ok') {
            $this->fail([[
                'duplicate' => 'এই মোবাইল নাম্বারটি পূর্বেই এন্ট্রি করা হয়েছে। অনুগ্রহ করে অন্য কোন নাম্বার ব্যবহার করুন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।',
                'duplicate_email' => 'এই ইমেইল ঠিকানাটি আগেই রেজিস্ট্রেশন করা আছে। অন্য কোন ইমেইল ব্যবহার করুন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।',
            ][$result] ?? 'দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], ['email' => $data['email'], 'address' => $data['address'], 'phone' => $data['phone']]);
        }

        Auth::login((int) $user['id']); // সেশন-id নতুন
        Session::flash('setup_done', true);
        redirect('/profile');
    }

    /**
     * লগইন-করা ও "তথ্য পূর্ণ করা বাকি" সদস্য; অন্য কেউ হলে যথাস্থানে পাঠায়।
     *
     * @return array<string,mixed>
     */
    private function member(): array
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!UserRepository::needsSetup($user)) {
            redirect('/profile');
        }

        return $user;
    }

    /**
     * @param list<string> $errors
     * @param array<string,string> $old
     */
    private function fail(array $errors, array $old = []): never
    {
        Session::flash('setup_errors', $errors);
        Session::flash('setup_old', $old);
        redirect('/account/setup#form');
    }
}
