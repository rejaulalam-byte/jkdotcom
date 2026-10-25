<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\GeoRepository;
use App\Models\UserRepository;

/**
 * পুরনো সাইট থেকে আনা কর্মীর প্রথম লগইনের "তথ্য পূর্ণ করুন" ধাপ (/account/setup):
 * নতুন পাসওয়ার্ড, জেন্ডার, ইমেইল, ঠিকানা ও এলাকা, দুই রেফারেন্স (মোবাইল না থাকলে মোবাইলও) ও সম্মতি — সবই আবশ্যক;
 * কোনটা বাকি থাকলে তার নাম ধরে জানানো হয়, তাই অ্যাডমিনের আলাদা যাচাই লাগে না। শেষ না হওয়া পর্যন্ত অন্য পেইজে যাওয়া যায় না
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
            'geo' => GeoRepository::all(),
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
            'gender' => clean_gender($_POST['gender'] ?? null),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
            'reference_1' => clean_input($_POST['reference_1'] ?? '', 300),
            'reference_2' => clean_input($_POST['reference_2'] ?? '', 300),
        ];
        $missing = content('setup.missing');

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
        if ($data['gender'] === '') {
            $errors[] = '"' . content('setup.f.gender') . '" ' . $missing;
        }
        if ($data['email'] === '') {
            $errors[] = '"' . content('setup.f.email') . '" ' . $missing;
        } elseif (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়। সঠিক ইমেইল লিখুন।';
        }
        if ($needsPhone && $data['phone'] === '') {
            $errors[] = '"' . content('setup.f.phone') . '" ' . $missing;
        } elseif ($needsPhone && preg_match('/^01[3-9][0-9]{8}$/', $data['phone']) !== 1) {
            $errors[] = 'মোবাইল নাম্বার ১১ সংখ্যার হতে হবে, যেমন 01712345678।';
        }
        if (mb_strlen($data['address']) < 2) {
            $errors[] = '"' . content('setup.f.address') . '" ' . $missing;
        }
        foreach (['upazila_id' => 'setup.f.upazila', 'district_id' => 'setup.f.district', 'division_id' => 'setup.f.division'] as $field => $labelKey) {
            if ($data[$field] === 0) {
                $errors[] = '"' . content($labelKey) . '" ' . $missing;
            }
        }
        if ($data['upazila_id'] > 0 && $data['district_id'] > 0 && $data['division_id'] > 0
            && !GeoRepository::isConsistent($data['division_id'], $data['district_id'], $data['upazila_id'])) {
            $errors[] = 'উপজেলা/থানা, জেলা ও বিভাগ একটার সঙ্গে আরেকটা মেলেনি। আবার বাছাই করুন।';
        }
        foreach (['reference_1' => 'setup.f.ref1', 'reference_2' => 'setup.f.ref2'] as $field => $labelKey) {
            if ($data[$field] === '') {
                $errors[] = '"' . content($labelKey) . '" ' . $missing;
            }
        }
        if (empty($_POST['consent'])) {
            $errors[] = content('consent.error');
        }
        if ($errors !== []) {
            $this->fail($errors, $data);
        }

        $result = UserRepository::completeSetup((int) $user['id'], $data + ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        if ($result !== 'ok') {
            $this->fail([[
                'duplicate' => 'এই মোবাইল নাম্বারটি পূর্বেই এন্ট্রি করা হয়েছে। অনুগ্রহ করে অন্য কোন নাম্বার ব্যবহার করুন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।',
                'duplicate_email' => 'এই ইমেইল ঠিকানাটি আগেই রেজিস্ট্রেশন করা আছে। অন্য কোন ইমেইল ব্যবহার করুন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।',
            ][$result] ?? 'দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], $data);
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
     * @param array<string,mixed> $old
     */
    private function fail(array $errors, array $old = []): never
    {
        Session::flash('setup_errors', $errors);
        Session::flash('setup_old', $old);
        redirect('/account/setup#form');
    }
}
