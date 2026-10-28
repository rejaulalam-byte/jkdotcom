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
use App\Models\CustomFields;
use App\Models\FormFields;
use App\Models\GeoRepository;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * রেজিস্ট্রেশন ফর্ম: পেশাজীবী কর্মী (/registration/professional/form, Figma 2658-504)
 * ও প্রতিনিধি (/registration/agent/form, Figma 2682-2)। আবেদন 'pending' থাকে, অ্যাডমিন অনুমোদন দিলে লগইন চলে।
 */
final class RegistrationController
{
    private const MIN_PASSWORD = 8;

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
            'done' => Session::pull('reg_done', null),
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
            $this->fail($back, [msg('msg.csrf')]);
        }
        // লুকানো "website" ঘর: আসল মানুষ ফাঁকা রাখে, স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        if (RateLimiter::tooMany('register', 5, 3600)) {
            $this->fail($back, [msg('msg.rate')]);
        }

        $data = [
            'role' => $role,
            'name' => $this->clean($_POST['name'] ?? '', 100),
            'gender' => clean_gender($_POST['gender'] ?? null),
            'phone' => normalize_phone($this->clean($_POST['phone'] ?? '', 20)),
            'email' => mb_strtolower($this->clean($_POST['email'] ?? '', 150)),
            'address' => $this->clean($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
            // সম্মতির টিক (না থাকলে validate() আটকায়); টিক থাকলে কখন দেওয়া হলো জমা থাকে
            'consent' => !empty($_POST['consent']) ? 1 : 0,
        ];
        if ($role === 'worker') {
            // তালিকার নামের সঙ্গে মিললে সেবার slug; না মিললে লেখাটা service_custom-এ জমা হয় (অ্যাডমিন যাচাই করবেন)
            $data['service_name'] = $this->clean($_POST['service_name'] ?? '', 100);
            $data['service_slug'] = ServiceRepository::slugByName($data['service_name']);
            $data['service_custom'] = $data['service_slug'] === null && $data['service_name'] !== '' ? $data['service_name'] : null;
            $data['experience_years'] = trim((string) ($_POST['experience'] ?? '')) === '' ? null : (int) $_POST['experience'];
            $data['bio'] = $this->clean($_POST['bio'] ?? '', 2000, true);
            $work = GeoRepository::parseWorkDistricts($_POST['work_districts'] ?? [], $_POST['work_all'] ?? '', $_POST['work_district_pick'] ?? '');
            $data['work_district_ids'] = $work['ids'];
            $data['work_all_districts'] = $work['all'] ? 1 : 0;
            $data['work_error'] = $work['error'];
            $extra = ServiceRepository::parseExtra($_POST['extra_services'] ?? [], $_POST['extra_service_pick'] ?? '', $data['service_slug']);
            $data['extra_services'] = $extra['slugs'];
            $data['extra_error'] = $extra['error'];
            $data['reference_1'] = $this->clean($_POST['reference_1'] ?? '', 300);
            $data['reference_2'] = $this->clean($_POST['reference_2'] ?? '', 300);
        } else {
            $data['nid_number'] = normalize_digits($this->clean($_POST['nid'] ?? '', 30));
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        // সুপার অ্যাডমিনের লুকানো ঘর (FormFields): খালি জমা হয়, validate()-ও আবশ্যক ধরে না
        $hidden = static fn (string $field): bool => FormFields::hidden($role, $field);
        if ($hidden('gender')) {
            $data['gender'] = '';
        }
        if ($hidden('address')) {
            $data['address'] = '';
        }
        if ($role === 'worker') {
            if ($hidden('experience')) {
                $data['experience_years'] = null;
            }
            if ($hidden('bio')) {
                $data['bio'] = '';
            }
            if ($hidden('work_districts')) {
                [$data['work_district_ids'], $data['work_all_districts'], $data['work_error']] = [[], 0, null];
            }
            if ($hidden('extra_services')) {
                [$data['extra_services'], $data['extra_error']] = [[], null];
            }
            if ($hidden('references')) {
                [$data['reference_1'], $data['reference_2']] = ['', ''];
            }
        } elseif ($hidden('nid')) {
            $data['nid_number'] = '';
        }

        $errors = $this->validate($data, $password, $confirm);
        // অ্যাডমিনের যোগ করা নতুন ঘর
        [$custom, $customErrors] = CustomFields::read($role, is_array($_POST['cf'] ?? null) ? $_POST['cf'] : []);
        $errors = array_merge($errors, $customErrors);
        $data['cf'] = $custom;
        [$photo, $photoError] = $hidden('photo') ? [null, null] : PhotoUpload::store($_FILES['photo'] ?? null);
        if ($photoError !== null) {
            $errors[] = $photoError;
        }
        if ($errors !== []) {
            $this->discard($photo);
            $this->fail($back, $errors, $data);
        }

        $data['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
        $data['consent_at'] = date('Y-m-d H:i:s');
        $data['photo'] = $photo;
        $result = UserRepository::create($data);
        if ($result !== 'ok') {
            $this->discard($photo);
            $messages = [
                'duplicate' => msg('msg.duplicate_phone'),
                'duplicate_email' => msg('msg.duplicate_email'),
                'duplicate_legacy' => msg('regform.msg.duplicate_legacy'),
                'duplicate_email_legacy' => msg('regform.msg.duplicate_email_legacy'),
            ];
            $this->fail($back, [$messages[$result] ?? msg('msg.save_failed')], $data);
        }

        $member = UserRepository::findByPhone((string) $data['phone'], $role);
        CustomFields::saveValues($role, (int) ($member['id'] ?? 0), $custom);

        $this->notifyAdmin($data);

        // পপ-আপে আবেদনকারীর নাম ও এলাকা দেখানো হয় (প্রতিনিধির লেখায়)
        $geo = GeoRepository::names($data['upazila_id'], $data['district_id'], $data['division_id']);
        Session::flash('reg_done', [
            'name' => honorific_name($data['gender'], $data['name']),
            'area' => implode(', ', array_filter([$geo['upazila'], $geo['district'], $geo['division']])),
        ]);
        redirect($back);
    }

    /**
     * registration@ ঠিকানায় ইমেইল: আবেদনের তথ্য ও আবেদনকারীর ছোট ছবি, যাতে এক নজরে বোঝা যায় কার আবেদন।
     * পাসওয়ার্ড কখনও নয়। না গেলেও আবেদন ডাটাবেসে জমা থাকে (অ্যাডমিন প্যানেল সেখান থেকেই দেখাবে)।
     *
     * @param array<string,mixed> $d
     */
    private function notifyAdmin(array $d): void
    {
        $geo = GeoRepository::names((int) $d['upazila_id'], (int) $d['district_id'], (int) $d['division_id']);
        $service = (string) ($d['service_name'] ?? '');
        if (($d['service_custom'] ?? null) !== null) {
            $service .= ' (তালিকায় নেই — নাম যাচাই করে সেবা-তালিকায় যোগ করুন)';
        }
        $isWorker = $d['role'] === 'worker';
        $rows = [
            'আবেদনের ধরন' => $isWorker ? 'পেশাজীবী কর্মী' : 'প্রতিনিধি',
            'নাম' => (string) $d['name'],
            'জেন্ডার' => ['male' => 'পুরুষ', 'female' => 'মহিলা', 'other' => 'অন্যান্য'][$d['gender']] ?? '',
            'ইমেইল' => (string) $d['email'],
            'মোবাইল' => format_phone((string) $d['phone']),
        ];
        if ($isWorker) {
            $rows['পেশা'] = $service;
            $extraNames = array_map(static fn (string $s): string => ServiceRepository::findBySlug($s)['name'] ?? $s, (array) $d['extra_services']);
            $rows['আরও পেশা'] = implode(', ', $extraNames);
            $rows['কাজের অভিজ্ঞতা'] = $d['experience_years'] === null ? '' : bn((int) $d['experience_years']) . ' বছর';
            $rows['কাজ করতে আগ্রহী জেলা'] = GeoRepository::workDistrictsText((array) $d['work_district_ids'], (bool) $d['work_all_districts']);
            $rows['রেফারেন্স-১'] = (string) $d['reference_1'];
            $rows['রেফারেন্স-২'] = (string) $d['reference_2'];
        } else {
            $rows['জাতীয় পরিচয়পত্র নম্বর'] = (string) $d['nid_number'];
        }
        $rows['ঠিকানা'] = (string) $d['address'];
        $rows['থানা/উপজেলা, জেলা, বিভাগ'] = implode(', ', array_filter([$geo['upazila'], $geo['district'], $geo['division']]));
        if ($isWorker && (string) $d['bio'] !== '') {
            $rows['সংক্ষিপ্ত বিবরণ'] = (string) $d['bio'];
        }
        foreach (CustomFields::forForm((string) $d['role']) as $f) {
            if (isset($d['cf'][$f['id']])) {
                $rows[$f['label']] = (string) $d['cf'][$f['id']];
            }
        }
        $rows['আবেদনের সময়'] = bn_date(date('Y-m-d')) . ', ' . bn_time(date('Y-m-d H:i:s'));

        $photo = isset($d['photo']) ? PhotoUpload::directory() . '/' . $d['photo'] : null;
        $image = $photo !== null && is_file($photo) ? ['path' => $photo, 'mime' => PhotoUpload::mimeOf((string) $d['photo'])] : null;

        $html = '<div style="font-family:Arial,Helvetica,sans-serif;font-size:15px;color:#222;max-width:560px">'
            . '<h2 style="color:#125a6c;margin:0 0 12px">নতুন রেজিস্ট্রেশন আবেদন — jorurikaj.com</h2>'
            . ($image !== null ? '<p style="margin:0 0 14px"><img src="cid:photo" alt="" width="110" style="border-radius:8px;display:block"></p>' : '')
            . '<table cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%">';
        $text = "নতুন রেজিস্ট্রেশন আবেদন — jorurikaj.com\n\n";
        foreach ($rows as $label => $value) {
            $html .= '<tr><td style="border-bottom:1px solid #dfe6e9;color:#555;width:38%;vertical-align:top">' . e($label) . '</td>'
                . '<td style="border-bottom:1px solid #dfe6e9">' . nl2br(e($value)) . '</td></tr>';
            $text .= $label . ': ' . $value . "\n";
        }
        $html .= '</table><p style="margin-top:14px;color:#555">আবেদনটি অনুমোদনের অপেক্ষায় আছে।</p></div>';
        $text .= "\nআবেদনটি অনুমোদনের অপেক্ষায় আছে।\n";

        $to = Env::get('MAIL_REGISTRATION_TO', 'registration@jorurikaj.com') ?? 'registration@jorurikaj.com';
        if (!Mailer::sendHtml($to, 'নতুন রেজিস্ট্রেশন আবেদন: ' . $d['name'], $html, $text, $image, $d['email'])) {
            Logger::error('Registration e-mail could not be sent');
        }
    }

    /**
     * @param array<string,mixed> $d
     * @return list<string>
     */
    private function validate(array $d, string $password, string $confirm): array
    {
        $errors = [];
        if (mb_strlen((string) $d['name']) < 2) {
            $errors[] = msg('msg.name');
        }
        if ($d['role'] === 'worker') {
            if (mb_strlen((string) $d['service_name']) < 2) {
                $errors[] = msg('msg.service');
            }
            if ($d['experience_years'] !== null && ($d['experience_years'] < 0 || $d['experience_years'] > 70)) {
                $errors[] = msg('msg.experience');
            }
        } elseif (!FormFields::hidden('agent', 'nid') && !preg_match('/^([0-9]{10}|[0-9]{13}|[0-9]{17})$/', (string) $d['nid_number'])) {
            $errors[] = msg('regform.msg.nid');
        }
        if ($d['gender'] === '' && !FormFields::hidden($d['role'], 'gender')) {
            $errors[] = msg('msg.gender');
        }
        if ($d['role'] === 'worker') {
            if (($d['reference_1'] === '' || $d['reference_2'] === '') && !FormFields::hidden('worker', 'references')) {
                $errors[] = msg('regform.msg.references');
            }
            if ($d['work_error'] !== null) {
                $errors[] = $d['work_error'];
            }
            if ($d['extra_error'] !== null) {
                $errors[] = $d['extra_error'];
            }
        }
        if (!$d['consent']) {
            $errors[] = content('consent.error');
        }
        if ($d['email'] === '') {
            $errors[] = msg('msg.email_empty');
        } elseif (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = msg('msg.email_bad');
        }
        if ($d['phone'] === '') {
            $errors[] = msg('msg.mobile_empty');
        } elseif (!preg_match('/^01[3-9][0-9]{8}$/', (string) $d['phone'])) {
            $errors[] = msg('msg.mobile_bad');
        }
        if ($d['address'] === '' && !FormFields::hidden($d['role'], 'address')) {
            $errors[] = msg('msg.address');
        }
        if ($d['division_id'] === 0 || $d['district_id'] === 0 || $d['upazila_id'] === 0) {
            $errors[] = msg('msg.area');
        } elseif (!GeoRepository::isConsistent($d['division_id'], $d['district_id'], $d['upazila_id'])) {
            $errors[] = msg('msg.area_mismatch');
        }

        // bcrypt ৭২ বাইটের পরের অংশ উপেক্ষা করে, তাই বেশি লম্বা পাসওয়ার্ড আটকানো হয়
        if (mb_strlen($password) < self::MIN_PASSWORD) {
            $errors[] = msg('regform.msg.password_short');
        } elseif (strlen($password) > 72) {
            $errors[] = msg('regform.msg.password_long');
        } elseif ($password === (string) $d['phone']) {
            $errors[] = msg('msg.password_is_phone');
        } elseif ($password !== $confirm) {
            $errors[] = msg('msg.password_match');
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
        return clean_input($value, $max, $multiline);
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
