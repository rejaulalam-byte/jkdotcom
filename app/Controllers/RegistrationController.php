<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\PhotoUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\GeoRepository;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * রেজিস্ট্রেশন ফর্ম: পেশাজীবী কর্মী (/registration/professional/form, Figma 2658-504)
 * ও প্রতিনিধি (/registration/agent/form, Figma 2682-2)। আবেদন 'pending' থাকে, অ্যাডমিন অনুমোদন দিলে লগইন চলে।
 */
final class RegistrationController
{
    private const MIN_PASSWORD = 12;

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function form(array $meta = []): void
    {
        $role = $this->role($meta);
        $key = 'regform.' . $role . '.';

        View::render('pages/register', [
            'bodyClass' => 'page-regform',
            'title' => content($key . 'crumb') . ' — Jorurikaj.com',
            'description' => content($key . 'intro'),
            'heading' => content($key . 'banner'),
            'crumb' => content($key . 'crumb'),
            'role' => $role,
            'action' => $role === 'agent' ? '/registration/agent/form' : '/registration/professional/form',
            'done' => (bool) Session::pull('reg_done', false),
            'errors' => Session::pull('reg_errors', []),
            'old' => Session::pull('reg_old', []),
            'geo' => GeoRepository::all(),
            'services' => $role === 'worker' ? ServiceRepository::all() : [],
        ]);
    }

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function store(array $meta = []): void
    {
        $role = $this->role($meta);
        $back = $role === 'agent' ? '/registration/agent/form' : '/registration/professional/form';

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        // লুকানো "website" ঘর: আসল মানুষ ফাঁকা রাখে, স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        if (RateLimiter::tooMany('register', 5, 3600)) {
            $this->fail($back, ['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }

        $data = [
            'role' => $role,
            'name' => $this->clean($_POST['name'] ?? '', 100),
            'phone' => normalize_phone($this->clean($_POST['phone'] ?? '', 20)),
            'address' => $this->clean($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
        ];
        if ($role === 'worker') {
            $data['service_slug'] = $this->clean($_POST['service'] ?? '', 80);
            $data['experience_years'] = trim((string) ($_POST['experience'] ?? '')) === '' ? null : (int) $_POST['experience'];
            $data['bio'] = $this->clean($_POST['bio'] ?? '', 2000, true);
        } else {
            $data['nid_number'] = normalize_digits($this->clean($_POST['nid'] ?? '', 30));
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = $this->validate($data, $password, $confirm);
        [$photo, $photoError] = PhotoUpload::store($_FILES['photo'] ?? null);
        if ($photoError !== null) {
            $errors[] = $photoError;
        }
        if ($errors !== []) {
            $this->discard($photo);
            $this->fail($back, $errors, $data);
        }

        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $data['photo'] = $photo;
        $result = UserRepository::create($data);
        if ($result !== 'ok') {
            $this->discard($photo);
            $this->fail($back, [$result === 'duplicate'
                ? 'এই মোবাইল নাম্বারে আগেই রেজিস্ট্রেশন করা আছে। লগইন করুন, অথবা সাহায্যের জন্য হেল্পলাইনে কল করুন।'
                : 'দুঃখিত, তথ্যগুলো সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন অথবা হেল্পলাইনে কল করুন।'], $data);
        }

        // অ্যাডমিনকে জানানো (পাসওয়ার্ড কখনও নয়); না গেলেও আবেদন জমা থাকে
        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = 'নতুন ' . ($role === 'worker' ? 'পেশাজীবী কর্মীর' : 'প্রতিনিধির') . " রেজিস্ট্রেশন আবেদন — jorurikaj.com\n\n"
            . "নাম: {$data['name']}\nমোবাইল: {$data['phone']}\nঠিকানা: {$data['address']}\n"
            . ($role === 'worker' ? 'পেশা: ' . ($data['service_slug'] ?? '') . "\n" : '')
            . "\nআবেদনটি অনুমোদনের অপেক্ষায় আছে।\n";
        if (!Mailer::send($to, 'নতুন রেজিস্ট্রেশন আবেদন: ' . $data['name'], $body)) {
            Logger::error('Registration e-mail could not be sent');
        }

        Session::flash('reg_done', true);
        redirect($back);
    }

    /**
     * @param array<string,mixed> $d
     * @return list<string>
     */
    private function validate(array $d, string $password, string $confirm): array
    {
        $errors = [];
        if (mb_strlen((string) $d['name']) < 2) {
            $errors[] = 'আপনার নাম লিখুন।';
        }
        if ($d['role'] === 'worker') {
            $known = array_column(ServiceRepository::all(), 'slug');
            if ($d['service_slug'] === '' || !in_array($d['service_slug'], $known, true)) {
                $errors[] = 'আপনার পেশা তালিকা থেকে বাছাই করুন।';
            }
            if ($d['experience_years'] !== null && ($d['experience_years'] < 0 || $d['experience_years'] > 70)) {
                $errors[] = 'কাজের অভিজ্ঞতা ০ থেকে ৭০ বছরের মধ্যে লিখুন।';
            }
        } elseif (!preg_match('/^([0-9]{10}|[0-9]{13}|[0-9]{17})$/', (string) $d['nid_number'])) {
            $errors[] = 'জাতীয় পরিচয়পত্র নম্বর সঠিক নয়। ১০, ১৩ বা ১৭ সংখ্যার নম্বর দিন।';
        }
        if ($d['phone'] === '') {
            $errors[] = 'মোবাইল নম্বর লিখুন।';
        } elseif (!preg_match('/^01[3-9][0-9]{8}$/', (string) $d['phone'])) {
            $errors[] = 'মোবাইল নম্বরটি সঠিক নয়। ১১ সংখ্যার নম্বর দিন, যেমন 01712345678।';
        }
        if ($d['address'] === '') {
            $errors[] = 'বর্তমান ঠিকানা লিখুন।';
        }
        if ($d['division_id'] === 0 || $d['district_id'] === 0 || $d['upazila_id'] === 0) {
            $errors[] = 'বিভাগ, জেলা এবং উপজেলা / থানা নির্বাচন করুন।';
        } elseif (!GeoRepository::isConsistent($d['division_id'], $d['district_id'], $d['upazila_id'])) {
            $errors[] = 'নির্বাচিত বিভাগ, জেলা ও উপজেলা / থানা একে অপরের সঙ্গে মিলছে না। আবার নির্বাচন করুন।';
        }

        // bcrypt ৭২ বাইটের পরের অংশ উপেক্ষা করে, তাই বেশি লম্বা পাসওয়ার্ড আটকানো হয়
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            $errors[] = 'লগইন পাসওয়ার্ড অবশ্যই ১২ অক্ষরের হতে হবে।';
        } elseif (strlen($password) > 72) {
            $errors[] = 'লগইন পাসওয়ার্ড সর্বোচ্চ ৭২ বাইট (প্রায় ৭০ ইংরেজি অক্ষর) হতে পারে।';
        } elseif ($password === (string) $d['phone']) {
            $errors[] = 'মোবাইল নাম্বারকে পাসওয়ার্ড হিসেবে ব্যবহার করবেন না।';
        } elseif ($password !== $confirm) {
            $errors[] = 'দুইবার লেখা পাসওয়ার্ড একই নয়।';
        }

        return $errors;
    }

    /** ত্রুটির কারণে সংরক্ষিত কিন্তু আর দরকার-নেই ছবি মুছে ফেলে। */
    private function discard(?string $photo): void
    {
        if ($photo !== null) {
            @unlink(PhotoUpload::directory() . '/' . $photo);
        }
    }

    /** @param array<string,mixed> $meta */
    private function role(array $meta): string
    {
        return ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';
    }

    private function clean(mixed $value, int $max, bool $multiline = false): string
    {
        $value = is_string($value) ? $value : '';
        $value = (string) preg_replace($multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u', '', str_replace("\r\n", "\n", $value));

        return mb_substr(trim($value), 0, $max);
    }

    /**
     * @param list<string> $errors
     * @param array<string,mixed> $old পাসওয়ার্ড কখনও ফেরত যায় না
     */
    private function fail(string $back, array $errors, array $old = []): never
    {
        Session::flash('reg_errors', $errors);
        Session::flash('reg_old', $old);
        redirect($back . '#form');
    }
}
