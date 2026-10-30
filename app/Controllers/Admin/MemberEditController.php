<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\PhotoUpload;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\GeoRepository;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * অ্যাডমিন একজন কর্মী বা প্রতিনিধির তথ্য হাতে ঠিক করে (/admin/member-edit/<id>), অথবা নতুন কর্মী (/admin/member-add) বা প্রতিনিধি (/admin/member-add/agent) যোগ করে।
 * অনুমতি: "registrations"। সম্পাদনায় সদস্যকে কোনো ইমেইল যায় না; নতুন মোবাইল/ইমেইল সেই পাশে (কর্মী/প্রতিনিধি) আর কারও না হলে তবেই চলে।
 * অ্যাডমিনের যোগ করা কর্মী সঙ্গে সঙ্গে অনুমোদিত ও তালিকায়; ইমেইল থাকলে অনুমোদনের ইমেইল যায় (পাসওয়ার্ড কখনও নয়)।
 * কোন কোন ঘর বদলাল তা কার্যলগে লেখা হয়।
 */
final class MemberEditController
{
    /** কর্মীর "আরও পেশা"-র ঘর ও কাজের জেলার ঘর কয়টা দেখানো হয়। */
    private const EXTRA_SLOTS = 4;
    private const DISTRICT_SLOTS = 5;

    /** @param array<string,mixed> $meta slug = সদস্যের id */
    public function form(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $member = $this->member($meta['slug'] ?? '');
        if ($member === null) {
            http_response_code(404);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'পাওয়া যায়নি', 'message' => 'এই সদস্যকে পাওয়া যায়নি।'], 'layouts/admin');

            return;
        }

        $work = UserRepository::workDistricts($member);
        // ভুল জমার পর ব্যবহারকারীর লেখা মান ফিরে আসে, নইলে সংরক্ষিত মান
        $v = Session::pull('admin_old', []) + $member + [
            'extra_services' => UserRepository::extraServices($member),
            'work_district_ids' => $work['ids'],
            'work_all_districts' => $work['all'] ? 1 : 0,
        ];

        header('Cache-Control: no-store');
        View::render('admin/member-edit', [
            'admin' => $admin,
            'title' => 'সম্পাদনা: ' . $member['name'],
            'member' => $member,
            'v' => $v,
            'geo' => GeoRepository::all(),
            'services' => ServiceRepository::all(),
            'photoUrl' => PhotoUpload::url($member['photo'] ?? null),
            'extraSlots' => self::EXTRA_SLOTS,
            'districtSlots' => self::DISTRICT_SLOTS,
            'errors' => Session::pull('admin_errors', []),
            'ok' => null,
        ], 'layouts/admin');
    }

    /** নতুন কর্মী বা প্রতিনিধি যোগের ফর্ম: সম্পাদনার ফর্মই, সব ঘর খালি। @param array<string,mixed> $meta role = worker | agent */
    public function addForm(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $role = ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';
        $member = ['id' => 0, 'role' => $role, 'name' => '', 'photo' => null];
        header('Cache-Control: no-store');
        View::render('admin/member-edit', [
            'admin' => $admin,
            'title' => $role === 'agent' ? 'নতুন প্রতিনিধি যোগ করুন' : 'নতুন কর্মী যোগ করুন',
            'member' => $member,
            'v' => Session::pull('admin_old', []) + $member + ['extra_services' => [], 'work_district_ids' => [], 'work_all_districts' => 0],
            'geo' => GeoRepository::all(),
            'services' => ServiceRepository::all(),
            'photoUrl' => null,
            'extraSlots' => self::EXTRA_SLOTS,
            'districtSlots' => self::DISTRICT_SLOTS,
            'errors' => Session::pull('admin_errors', []),
            'ok' => null,
        ], 'layouts/admin');
    }

    /**
     * POST নতুন কর্মী: মোবাইল আবশ্যক (লগইন ও যোগাযোগ)। পাসওয়ার্ড: ইমেইল থাকলে ফাঁকা রাখা যায় (কর্মী "পাসওয়ার্ড মনে নেই" দিয়ে
     * নিজে বসান); ইমেইল না থাকলে অ্যাডমিন একটা প্রাথমিক পাসওয়ার্ড দেন ও ফোনে জানান। দিলে প্রথম লগইনে বদলাতে হয়।
     */
    /** @param array<string,mixed> $meta role = worker | agent */
    public function addSave(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $role = ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';
        $back = admin_base() . '/member-add' . ($role === 'agent' ? '/agent' : '');
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        [$data, $errors, $newPhoto] = $this->collect($role === 'worker');
        if ($data['phone'] === '') {
            $errors[] = 'মোবাইল নাম্বার দিন (কর্মীর লগইন ও যোগাযোগ এতেই হয়)।';
        }
        $password = (string) ($_POST['password'] ?? '');
        if ($password === '' && $data['email'] === '') {
            $errors[] = 'ইমেইল না থাকলে একটা প্রাথমিক পাসওয়ার্ড দিন (ফোনে কর্মীকে জানাবেন)।';
        }
        if ($password !== '' && (strlen($password) < 8 || strlen($password) > 72)) {
            $errors[] = 'প্রাথমিক পাসওয়ার্ড কমপক্ষে ৮ অক্ষরের হতে হবে।';
        }
        if ($errors !== []) {
            $this->discard($newPhoto);
            Session::flash('admin_old', $this->oldValues($data));
            $this->fail($back, $errors);
        }

        // পাসওয়ার্ড না দিলে কেউ অনুমান করতে পারবে না এমন এলোমেলো হ্যাশ; কর্মী ইমেইলের কোডে নিজের পাসওয়ার্ড বসান
        $data += [
            'role' => $role,
            'password_hash' => password_hash($password !== '' ? $password : bin2hex(random_bytes(24)), PASSWORD_DEFAULT),
            'must_change_password' => $password !== '' ? 1 : 0,
        ];
        $result = UserRepository::create($data);
        if ($result !== 'ok') {
            $this->discard($newPhoto);
            Session::flash('admin_old', $this->oldValues($data));
            $who = $role === 'agent' ? 'প্রতিনিধি' : 'কর্মী';
            $this->fail($back, [str_starts_with($result, 'duplicate_email') ? "এই ইমেইলে আরেকজন {$who} আছেন।" : (str_starts_with($result, 'duplicate') ? "এই মোবাইল নাম্বারে আরেকজন {$who} আছেন।" : 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।')]);
        }
        $member = UserRepository::findByPhone($data['phone'], $role);
        if ($member === null || !UserRepository::setStatus((int) $member['id'], 'approved')) {
            $this->fail(admin_base() . ($role === 'agent' ? '/agents' : '/workers'), ['কর্মী যোগ হয়েছে, কিন্তু অনুমোদন করা যায়নি; তালিকা থেকে অনুমোদন করুন।']);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'member_added', ($role === 'agent' ? 'প্রতিনিধি: ' : '') . $data['name'] . ' — ' . format_phone($data['phone']));

        $mailed = $data['email'] !== '' && (new MembersController())->sendApprovalMail($member);
        $note = $data['email'] === ''
            ? ' ইমেইল নেই, তাই প্রাথমিক পাসওয়ার্ডটি ফোনে জানান; প্রথম লগইনে তাঁকে বদলাতে হবে।'
            : ($mailed ? ' অনুমোদনের ইমেইল পাঠানো হয়েছে; পাসওয়ার্ড ভুলে-যাওয়ার লিংকে তিনি নিজের পাসওয়ার্ড বসাতে পারবেন।' : ' অনুমোদনের ইমেইল পাঠানো যায়নি।');
        Session::flash('admin_ok', $data['name'] . ' যোগ হয়েছেন' . ($role === 'agent' ? ' (প্রতিনিধি, অনুমোদিত)।' : ' ও তালিকায় দেখা যাচ্ছেন।') . $note);
        redirect(admin_base() . '/members/' . (int) $member['id']);
    }

    public function save(): void
    {
        $admin = AdminAuth::require('registrations');
        $member = $this->member($_POST['id'] ?? '');
        if ($member === null) {
            redirect(admin_base() . '/workers');
        }
        $back = admin_base() . '/member-edit/' . (int) $member['id'];
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }

        [$data, $errors, $newPhoto] = $this->collect($member['role'] === 'worker');

        if ($errors !== []) {
            $this->discard($newPhoto);
            Session::flash('admin_old', $this->oldValues($data));
            $this->fail($back, $errors);
        }

        $result = UserRepository::adminUpdate((int) $member['id'], $data);
        if ($result !== 'ok') {
            $this->discard($newPhoto);
            Session::flash('admin_old', $this->oldValues($data));
            $this->fail($back, [[
                'duplicate' => 'এই মোবাইল নাম্বার এই পাশে (কর্মী/প্রতিনিধি) আরেকজনের আছে।',
                'duplicate_email' => 'এই ইমেইল এই পাশে (কর্মী/প্রতিনিধি) আরেকজনের আছে।',
            ][$result] ?? 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।']);
        }
        if (array_key_exists('photo', $data) && (string) ($member['photo'] ?? '') !== '') {
            $this->discard((string) $member['photo']); // পুরনো ছবির ফাইল আর লাগে না
        }

        $changed = $this->changedLabels($member, $data);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'member_edit', (string) $member['name'] . ($changed !== [] ? ' — ' . implode(', ', $changed) : ' — কিছু বদলায়নি'));
        Session::flash('admin_ok', $changed !== [] ? 'সংরক্ষণ হয়েছে। বদলেছে: ' . implode(', ', $changed) . '।' : 'সংরক্ষণ হয়েছে (কিছু বদলায়নি)।');
        redirect(admin_base() . '/members/' . (int) $member['id']);
    }

    /**
     * ফর্মের ঘরগুলো পড়ে যাচাই করে (সম্পাদনা ও নতুন কর্মী দুটোতেই)।
     *
     * @return array{0:array<string,mixed>,1:list<string>,2:?string} [মান, ভুলের তালিকা, নতুন ছবির ফাইল]
     */
    private function collect(bool $isWorker): array
    {
        $data = [
            'name' => clean_input($_POST['name'] ?? '', 100),
            'gender' => clean_gender($_POST['gender'] ?? ''),
            'phone' => normalize_phone(clean_input($_POST['phone'] ?? '', 20)),
            'email' => mb_strtolower(clean_input($_POST['email'] ?? '', 150)),
            'address' => clean_input($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
        ];
        $errors = [];

        if (mb_strlen($data['name']) < 2) {
            $errors[] = 'নাম লিখুন (কমপক্ষে ২ অক্ষর)।';
        }
        if ($data['phone'] !== '' && preg_match('/^01[3-9][0-9]{8}$/', $data['phone']) !== 1) {
            $errors[] = 'মোবাইল নাম্বার ১১ সংখ্যার হতে হবে, যেমন 01712345678 (না থাকলে ঘরটা ফাঁকা রাখুন)।';
        }
        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয় (না থাকলে ঘরটা ফাঁকা রাখুন)।';
        }
        if (!GeoRepository::isConsistent($data['division_id'], $data['district_id'], $data['upazila_id'])) {
            $errors[] = 'বিভাগ, জেলা ও উপজেলা/থানা বেছে নিন এবং সেগুলো একে অপরের সঙ্গে মেলাতে হবে।';
        }

        if ($isWorker) {
            // প্রধান পেশা: তালিকা থেকে, নইলে নিজের লেখা (service_custom)
            $slug = (string) ($_POST['service_slug'] ?? '');
            $custom = clean_input($_POST['service_custom'] ?? '', 100);
            if ($slug !== '' && ServiceRepository::findBySlug($slug) === null) {
                $errors[] = 'পেশাটি তালিকায় নেই।';
                $slug = '';
            }
            if ($slug === '' && $custom === '') {
                $errors[] = 'প্রধান পেশা বাছুন, অথবা নিজের লেখা পেশা লিখুন।';
            }
            $data['service_slug'] = $slug !== '' ? $slug : null;
            $data['service_custom'] = $slug !== '' ? null : $custom;

            $extra = ServiceRepository::parseExtra(array_filter((array) ($_POST['extra_services'] ?? []), 'strlen'), '', $data['service_slug']);
            $data['extra_services'] = $extra['slugs'];
            if ($extra['error'] !== null) {
                $errors[] = $extra['error'];
            }

            $exp = trim((string) ($_POST['experience'] ?? ''));
            $data['experience_years'] = $exp === '' ? null : (int) normalize_digits($exp);
            if ($data['experience_years'] !== null && ($data['experience_years'] < 0 || $data['experience_years'] > 70)) {
                $errors[] = 'অভিজ্ঞতা ০ থেকে ৭০ বছরের মধ্যে লিখুন।';
            }
            $data['bio'] = clean_input($_POST['bio'] ?? '', 2000, true);
            $data['reference_1'] = clean_input($_POST['reference_1'] ?? '', 300);
            $data['reference_2'] = clean_input($_POST['reference_2'] ?? '', 300);

            $work = GeoRepository::parseWorkDistricts(array_filter((array) ($_POST['work_districts'] ?? []), 'strlen'), !empty($_POST['work_all']) ? '1' : '', '');
            $data['work_district_ids'] = $work['ids'];
            $data['work_all_districts'] = $work['all'] ? 1 : 0;
            if ($work['error'] !== null) {
                $errors[] = $work['error'];
            }
        } else {
            $nid = normalize_digits(clean_input($_POST['nid'] ?? '', 30));
            if ($nid !== '' && preg_match('/^([0-9]{10}|[0-9]{13}|[0-9]{17})$/', $nid) !== 1) {
                $errors[] = 'জাতীয় পরিচয়পত্র নম্বর ১০, ১৩ বা ১৭ সংখ্যার হতে হবে (না থাকলে ঘরটা ফাঁকা রাখুন)।';
            }
            $data['nid_number'] = $nid;
        }

        // ছবি: নতুন ফাইল দিলে বদলায়; "ছবি মুছুন" টিক দিলে মুছে যায়; কিছু না করলে আগেরটাই থাকে
        $newPhoto = null;
        [$uploaded, $photoError] = PhotoUpload::store($_FILES['photo'] ?? null);
        if ($photoError !== null) {
            $errors[] = $photoError;
        } elseif ($uploaded !== null) {
            $newPhoto = $uploaded;
            $data['photo'] = $uploaded;
        } elseif (!empty($_POST['photo_remove'])) {
            $data['photo'] = null;
        }

        return [$data, $errors, $newPhoto];
    }

    /** @return array<string,mixed>|null */
    private function member(mixed $id): ?array
    {
        $id = ctype_digit((string) $id) ? (int) $id : 0;

        return $id > 0 ? UserRepository::findById($id) : null;
    }

    /**
     * ফর্মে ফিরিয়ে দেওয়ার মান (ভুলের পর): যা ফর্ম থেকে এসেছিল।
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function oldValues(array $data): array
    {
        return $data + [
            'experience' => $_POST['experience'] ?? '',
            'nid_number' => $data['nid_number'] ?? '',
        ];
    }

    /**
     * কোন ঘরগুলো বদলাল তার বাংলা নাম (কার্যলগ ও বার্তার জন্য)। ছবির ফাইলের নাম লগে যায় না।
     *
     * @param array<string,mixed> $old আগের সারি
     * @param array<string,mixed> $new নতুন মান
     * @return list<string>
     */
    private function changedLabels(array $old, array $new): array
    {
        $labels = [
            'name' => 'নাম', 'gender' => 'জেন্ডার', 'phone' => 'মোবাইল', 'email' => 'ইমেইল', 'address' => 'ঠিকানা',
            'upazila_id' => 'উপজেলা', 'district_id' => 'জেলা', 'division_id' => 'বিভাগ', 'service_slug' => 'প্রধান পেশা',
            'service_custom' => 'নিজের লেখা পেশা', 'experience_years' => 'অভিজ্ঞতা', 'bio' => 'কাজের বিবরণ',
            'reference_1' => 'রেফারেন্স ১', 'reference_2' => 'রেফারেন্স ২', 'nid_number' => 'NID', 'photo' => 'ছবি',
        ];
        $changed = [];
        foreach ($labels as $key => $label) {
            if (array_key_exists($key, $new) && (string) ($old[$key] ?? '') !== (string) ($new[$key] ?? '')) {
                $changed[] = $label;
            }
        }
        if (array_key_exists('extra_services', $new) && $new['extra_services'] !== UserRepository::extraServices($old)) {
            $changed[] = 'আরও পেশা';
        }

        return $changed;
    }

    /** সংরক্ষিত কিন্তু আর-লাগে-না ছবির ফাইল মুছে ফেলে। */
    private function discard(?string $photo): void
    {
        if ($photo !== null && $photo !== '' && PhotoUpload::url($photo) !== null) {
            @unlink(PhotoUpload::directory() . '/' . $photo);
        }
    }

    /** @param list<string> $errors */
    private function fail(string $path, array $errors): never
    {
        Session::flash('admin_errors', $errors);
        redirect($path);
    }
}
