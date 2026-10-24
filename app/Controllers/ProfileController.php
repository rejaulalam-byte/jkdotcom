<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\PhotoUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\GeoRepository;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * সদস্যের নিজের প্রোফাইল (/profile): ছবি, মোবাইল, ঠিকানা, এলাকা এবং (কর্মীর) পেশা, অভিজ্ঞতা, বিবরণ বদলানো যায়।
 * নাম, ইমেইল, জেন্ডার ও প্রতিনিধির NID বদলানো যায় না (বদলাতে নতুন রেজিস্ট্রেশন)। মোবাইল লগইন-আইডি,
 * তাই বদলাতে বর্তমান পাসওয়ার্ড লাগে; বদলালেই লগইন ও খোঁজার জন্য নতুন নাম্বারই চলে (মোবাইল শুধু users টেবিলে থাকে)।
 */
final class ProfileController
{
    public function form(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            redirect('/account/password');
        }

        $service = (string) ($user['service_slug'] ?? '') !== '' ? ServiceRepository::findBySlug((string) $user['service_slug']) : null;
        // ভুল জমার পর ব্যবহারকারীর লেখা মান ফিরে আসে, নইলে সংরক্ষিত মান
        $work = UserRepository::workDistricts($user);
        $old = Session::pull('profile_old', []) + $user + [
            'service_name' => $service['name'] ?? (string) ($user['service_custom'] ?? ''),
            'work_district_ids' => $work['ids'],
            'work_all_districts' => $work['all'] ? 1 : 0,
            'extra_services' => UserRepository::extraServices($user),
        ];

        View::render('pages/profile', [
            'bodyClass' => 'page-regform',
            'title' => content('profile.crumb') . ' — Jorurikaj.com',
            'heading' => content('profile.banner'),
            'user' => $user,
            'old' => $old,
            'role' => (string) $user['role'],
            'done' => Session::pull('profile_done', false) === true,
            'unlocked' => Session::pull('login_unlocked', false) === true,
            'passwordChanged' => Session::pull('password_changed', false) === true,
            'errors' => Session::pull('profile_errors', []),
            'geo' => GeoRepository::all(),
            'services' => $user['role'] === 'worker' ? ServiceRepository::all() : [],
            'photoUrl' => PhotoUpload::url($user['photo'] ?? null),
        ]);
    }

    /** পুরনো "ড্যাশবোর্ড" ঠিকানা (/dashboard) এখন প্রোফাইলে যায়। */
    public function dashboardRedirect(): void
    {
        redirect('/profile');
    }

    public function update(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail(['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect('/profile');
        }
        if (RateLimiter::tooMany('profile_' . $user['id'], 30, 3600)) {
            $this->fail(['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }

        $isWorker = $user['role'] === 'worker';
        $data = [
            'phone' => normalize_phone(clean_input($_POST['phone'] ?? '', 20)),
            'address' => clean_input($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
        ];
        if ($isWorker) {
            $data['service_name'] = clean_input($_POST['service_name'] ?? '', 100);
            $data['service_slug'] = ServiceRepository::slugByName($data['service_name']);
            $data['service_custom'] = $data['service_slug'] === null && $data['service_name'] !== '' ? $data['service_name'] : null;
            $data['experience_years'] = trim((string) ($_POST['experience'] ?? '')) === '' ? null : (int) $_POST['experience'];
            $data['bio'] = clean_input($_POST['bio'] ?? '', 2000, true);
            $data['reference_1'] = clean_input($_POST['reference_1'] ?? '', 300);
            $data['reference_2'] = clean_input($_POST['reference_2'] ?? '', 300);
            $work = GeoRepository::parseWorkDistricts($_POST['work_districts'] ?? [], $_POST['work_all'] ?? '', $_POST['work_district_pick'] ?? '');
            $data['work_district_ids'] = $work['ids'];
            $data['work_all_districts'] = $work['all'] ? 1 : 0;
            $data['work_error'] = $work['error'];
            $extra = ServiceRepository::parseExtra($_POST['extra_services'] ?? [], $_POST['extra_service_pick'] ?? '', $data['service_slug']);
            $data['extra_services'] = $extra['slugs'];
            $data['extra_error'] = $extra['error'];
        }

        $errors = $this->validate($user, $data, (string) ($_POST['current_password'] ?? ''), $isWorker);
        [$photo, $photoError] = PhotoUpload::store($_FILES['photo'] ?? null);
        if ($photoError !== null) {
            $errors[] = $photoError;
        }
        if ($errors !== []) {
            $this->discard($photo);
            $this->fail($errors, $data);
        }

        if ($photo !== null) {
            $data['photo'] = $photo;
        }
        unset($data['work_error'], $data['extra_error']);
        $result = UserRepository::updateProfile((int) $user['id'], $data);
        if ($result !== 'ok') {
            $this->discard($photo);
            $this->fail([$result === 'duplicate'
                ? 'এই মোবাইল নাম্বারে অন্য একটি অ্যাকাউন্ট আছে। অন্য নাম্বার দিন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।'
                : 'দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], $data);
        }
        if ($photo !== null) {
            $this->discard((string) ($user['photo'] ?? '') !== '' ? (string) $user['photo'] : null); // পুরনো ছবি আর লাগে না
        }

        Session::flash('profile_done', true);
        redirect('/profile');
    }

    /**
     * @param array<string,mixed> $user
     * @param array<string,mixed> $d
     * @return list<string>
     */
    private function validate(array $user, array $d, string $currentPassword, bool $isWorker): array
    {
        $errors = [];
        if ($d['phone'] === '') {
            $errors[] = 'মোবাইল নম্বর লিখুন।';
        } elseif (!preg_match('/^01[3-9][0-9]{8}$/', (string) $d['phone'])) {
            $errors[] = 'মোবাইল নম্বরটি সঠিক নয়। ১১ সংখ্যার নম্বর দিন, যেমন 01712345678।';
        } elseif ($d['phone'] !== (string) $user['phone']) {
            // মোবাইল লগইন-আইডি: বদলাতে বর্তমান পাসওয়ার্ড লাগে (ভুল পাসওয়ার্ডের সীমা পাসওয়ার্ড-পরিবর্তনের মতোই)
            $bucket = 'pwchange_' . $user['id'];
            if (RateLimiter::blocked($bucket, 5, 900)) {
                $errors[] = 'আপনি বর্তমান পাসওয়ার্ড ৫ বার ভুল লিখেছেন। অনুগ্রহ করে ১৫ মিনিট পরে আবার চেষ্টা করুন।';
            } elseif ($currentPassword === '' || !password_verify($currentPassword, (string) $user['password_hash'])) {
                if ($currentPassword !== '') {
                    RateLimiter::hit($bucket, 900);
                }
                $errors[] = 'মোবাইল নাম্বার বদলাতে আপনার বর্তমান পাসওয়ার্ডটি সঠিকভাবে লিখুন।';
            }
        }
        if ($d['address'] === '') {
            $errors[] = 'বর্তমান ঠিকানা লিখুন।';
        }
        if ($d['division_id'] === 0 || $d['district_id'] === 0 || $d['upazila_id'] === 0) {
            $errors[] = 'বিভাগ, জেলা এবং উপজেলা / থানা নির্বাচন করুন।';
        } elseif (!GeoRepository::isConsistent($d['division_id'], $d['district_id'], $d['upazila_id'])) {
            $errors[] = 'নির্বাচিত বিভাগ, জেলা ও উপজেলা / থানা একে অপরের সঙ্গে মিলছে না। আবার নির্বাচন করুন।';
        }
        if ($isWorker) {
            if (mb_strlen((string) $d['service_name']) < 2) {
                $errors[] = 'আপনার পেশা লিখুন বা তালিকা থেকে বাছাই করুন।';
            }
            if ($d['experience_years'] !== null && ($d['experience_years'] < 0 || $d['experience_years'] > 70)) {
                $errors[] = 'কাজের অভিজ্ঞতা ০ থেকে ৭০ বছরের মধ্যে লিখুন।';
            }
            if ($d['reference_1'] === '' || $d['reference_2'] === '') {
                $errors[] = 'দুইজন রেফারেন্সের তথ্যই লিখুন।';
            }
            if ($d['work_error'] !== null) {
                $errors[] = $d['work_error'];
            }
            if ($d['extra_error'] !== null) {
                $errors[] = $d['extra_error'];
            }
        }

        return $errors;
    }

    /** সংরক্ষিত কিন্তু আর দরকার-নেই ছবির ফাইল মুছে ফেলে। */
    private function discard(?string $photo): void
    {
        if ($photo !== null && PhotoUpload::url($photo) !== null) {
            @unlink(PhotoUpload::directory() . '/' . $photo);
        }
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $old
     */
    private function fail(array $errors, array $old = []): never
    {
        Session::flash('profile_errors', $errors);
        Session::flash('profile_old', $old);
        redirect('/profile#form');
    }
}
