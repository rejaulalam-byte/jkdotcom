<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\RichText;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\GeoRepository;
use App\Models\JobPostRepository;
use App\Models\ServiceRepository;

/**
 * কাজের বিজ্ঞপ্তির তালিকা (/admin/jobs): দর্শকের জানানো বিজ্ঞপ্তি দেখা, লুকানো/আবার প্রকাশ, "কাজের ধরন"-এর বানান ঠিক করা,
 * চিরতরে মোছা; অ্যাডমিন থেকে নতুন বিজ্ঞপ্তি যোগ ও পূর্ণ এডিটরে সম্পাদনা (/admin/job-add, /admin/job-edit/<id>)।
 * বিজ্ঞপ্তি জমা দেওয়ামাত্র প্রকাশিত হয় (অনুমোদন লাগে না), তাই এখানে দরকারে লুকানো হয়। অনুমতি: "inbox"।
 */
final class JobsController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $admin = AdminAuth::require('inbox');
        $tab = isset(JobPostRepository::TABS[$_GET['t'] ?? '']) ? (string) ($_GET['t'] ?? '') : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = JobPostRepository::adminList($tab, $q, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        header('Cache-Control: no-store');
        View::render('admin/jobs', [
            'admin' => $admin,
            'title' => 'কাজের বিজ্ঞপ্তি',
            'tab' => $tab,
            'q' => $q,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => JobPostRepository::adminCounts(),
            'open' => (int) ($_GET['open'] ?? 0),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** নতুন বিজ্ঞপ্তির ফর্ম (slug নেই) বা সম্পাদনা (slug = id)। @param array<string,mixed> $meta */
    public function edit(array $meta = []): void
    {
        $admin = AdminAuth::require('inbox');
        $id = ctype_digit((string) ($meta['slug'] ?? '')) ? (int) $meta['slug'] : 0;
        $item = $id > 0 ? JobPostRepository::adminFind($id) : null;
        if ($id > 0 && $item === null) {
            $this->fail(admin_base() . '/jobs', 'বিজ্ঞপ্তিটি পাওয়া যায়নি।');
        }
        $old = Session::pull('admin_old', []);
        $v = $old + ($item ?? ['status' => 'approved', 'start_date' => date('Y-m-d')]);
        // এডিটরের লেখা: ভুলের পর লেখা মান; না হলে সাজানো রূপ; দর্শকের সাদা লেখা হলে অনুচ্ছেদে বদলে
        $html = (string) ($old['html'] ?? (string) ($v['details_html'] ?? ''));
        if ($html === '' && (string) ($v['details'] ?? '') !== '') {
            $html = '<p>' . nl2br(e((string) $v['details']), false) . '</p>';
        }

        Security::sendCsp([], true); // পূর্ণ এডিটর নিজের ভেতরে style ও blob: ছবি ব্যবহার করে
        header('Cache-Control: no-store');
        View::render('admin/job-edit', [
            'admin' => $admin,
            'title' => $id > 0 ? 'বিজ্ঞপ্তি সম্পাদনা' : 'নতুন কাজের বিজ্ঞপ্তি',
            'id' => $id,
            'v' => $v,
            'html' => $html,
            'geo' => GeoRepository::all(),
            'services' => ServiceRepository::all(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => null,
        ], 'layouts/admin');
    }

    /** POST: id (০ = নতুন), title, html, contact_*, phone, email, address, এলাকা, start_date, end_date, status। */
    public function save(): void
    {
        $admin = AdminAuth::require('inbox');
        $id = (int) ($_POST['id'] ?? 0);
        $back = admin_base() . ($id > 0 ? '/job-edit/' . $id : '/job-add');
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        if ($id > 0 && JobPostRepository::adminFind($id) === null) {
            $this->fail(admin_base() . '/jobs', 'বিজ্ঞপ্তিটি পাওয়া যায়নি (হয়তো আগেই মোছা হয়েছে)।');
        }

        // পূর্ণ এডিটরের লেখা সবসময় ছেঁকে রাখা হয়; সাদা রূপটা খোঁজা ও অ্যাডমিন-তালিকার জন্য
        $html = RichText::sanitize((string) ($_POST['html'] ?? ''));
        $plain = trim((string) preg_replace('/\n{3,}/u', "\n\n", html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>', '</h2>', '</h3>', '</h4>', '</tr>'], "\n", $html)), ENT_QUOTES, 'UTF-8')));
        $data = [
            'title' => clean_input($_POST['title'] ?? '', 190),
            'details' => mb_substr($plain, 0, 3000),
            'details_html' => $html,
            'contact_name' => clean_input($_POST['contact_name'] ?? '', 100),
            'contact_gender' => clean_gender($_POST['contact_gender'] ?? ''),
            'phone' => normalize_phone(clean_input($_POST['phone'] ?? '', 20)),
            'email' => mb_strtolower(clean_input($_POST['email'] ?? '', 150)),
            'address' => clean_input($_POST['address'] ?? '', 250),
            'upazila_id' => (int) ($_POST['upazila_id'] ?? 0),
            'district_id' => (int) ($_POST['district_id'] ?? 0),
            'division_id' => (int) ($_POST['division_id'] ?? 0),
            'start_date' => (string) ($_POST['start_date'] ?? ''),
            'end_date' => (string) ($_POST['end_date'] ?? ''),
            'status' => ($_POST['status'] ?? '') === 'pending' ? 'pending' : 'approved',
        ];

        $errors = [];
        if (mb_strlen($data['title']) < 2) {
            $errors[] = '"কাজের ধরন" অন্তত ২ অক্ষরের হতে হবে।';
        }
        if ($plain === '' && !str_contains($html, '<img')) {
            $errors[] = 'বিস্তারিত লিখুন।';
        }
        if (mb_strlen($data['contact_name']) < 2) {
            $errors[] = 'যোগাযোগের নাম লিখুন।';
        }
        if (preg_match('/^01[3-9][0-9]{8}$/', $data['phone']) !== 1) {
            $errors[] = 'মোবাইল নাম্বার ১১ সংখ্যার হতে হবে, যেমন 01712345678।';
        }
        if ($data['email'] !== '' && filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয় (না থাকলে ঘরটা ফাঁকা রাখুন)।';
        }
        if ($data['address'] === '') {
            $errors[] = 'ঠিকানা লিখুন।';
        }
        if (!GeoRepository::isConsistent($data['division_id'], $data['district_id'], $data['upazila_id'])) {
            $errors[] = 'বিভাগ, জেলা ও উপজেলা/থানা বেছে নিন এবং সেগুলো একে অপরের সঙ্গে মেলাতে হবে।';
        }
        $validDate = static fn (string $d): bool => ($t = \DateTimeImmutable::createFromFormat('!Y-m-d', $d)) !== false && $t->format('Y-m-d') === $d;
        if (!$validDate($data['start_date'])) {
            $errors[] = 'প্রচার শুরুর তারিখ দিন।';
        }
        if ($data['end_date'] !== '' && (!$validDate($data['end_date']) || $data['end_date'] < $data['start_date'])) {
            $errors[] = 'প্রচার শেষের তারিখ শুরুর তারিখের পরে হতে হবে (না চাইলে ফাঁকা রাখুন)।';
        }
        if ($errors !== []) {
            Session::flash('admin_old', $data + ['html' => $html]);
            Session::flash('admin_errors', $errors);
            redirect($back);
        }

        $saved = JobPostRepository::adminSave($id, $data);
        if ($saved === null) {
            Session::flash('admin_old', $data + ['html' => $html]);
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $id === 0 ? 'job_add' : 'job_edit', $data['title'] . ' — ' . $data['contact_name']);
        $this->ok(admin_base() . '/jobs?open=' . $saved, $data['title'] . ($id === 0 ? ' — বিজ্ঞপ্তি যোগ হয়েছে' : ' — সংরক্ষণ হয়েছে') . ($data['status'] === 'approved' ? '; সাইটে দেখা যাচ্ছে।' : ' (লুকানো)।'));
    }

    /** POST: days (১–৩০) — শেষ তারিখ না থাকা বিজ্ঞপ্তি শুরুর তারিখ থেকে কত দিন "আজকের কাজ"-এ দেখাবে। */
    public function days(): void
    {
        $admin = AdminAuth::require('inbox');
        $back = admin_base() . '/jobs';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $days = (string) ($_POST['days'] ?? '');
        if (!ctype_digit($days) || (int) $days < 1 || (int) $days > 30) {
            $this->fail($back, 'দিনের সংখ্যা ১ থেকে ৩০-এর মধ্যে দিন।');
        }
        if (!Content::set('settings.jobs_visible_days', (string) (int) $days)) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'jobs_days', bn((int) $days) . ' দিন');
        $this->ok($back, 'এখন থেকে বিজ্ঞপ্তি ' . bn((int) $days) . ' দিন দেখানো হবে।');
    }

    /** POST: id, do (hide | show | title | delete), title (do=title হলে), back। */
    public function action(): void
    {
        $admin = AdminAuth::require('inbox');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $item = JobPostRepository::adminFind($id);
        if ($item === null) {
            $this->fail($back, 'বিজ্ঞপ্তিটি পাওয়া যায়নি (হয়তো আগেই মোছা হয়েছে)।');
        }
        $name = (string) $item['title'];
        $do = (string) ($_POST['do'] ?? '');

        if ($do === 'title') {
            $title = clean_input($_POST['title'] ?? '', 190);
            if (mb_strlen($title) < 2) {
                $this->fail($back, '"কাজের ধরন" অন্তত ২ অক্ষরের হতে হবে।');
            }
            $done = JobPostRepository::setTitle($id, $title);
            $log = 'job_title';
            $message = 'কাজের ধরন "' . $title . '" করা হয়েছে।';
            $name .= ' → ' . $title;
        } else {
            [$done, $log, $message] = match ($do) {
                'hide' => [JobPostRepository::setStatus($id, 'pending'), 'job_hide', 'লুকানো হলো; তালিকায় আর দেখা যাবে না।'],
                'show' => [JobPostRepository::setStatus($id, 'approved'), 'job_show', 'আবার প্রকাশ করা হলো।'],
                'delete' => [JobPostRepository::delete($id), 'job_delete', 'চিরতরে মুছে গেছে।'],
                default => [false, '', ''],
            };
        }
        if ($log === '') {
            $this->fail($back, 'অজানা কাজ।');
        }
        if (!$done) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }

        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $log, $name);
        $this->ok($back, $name . ' — ' . $message);
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ (ট্যাব, খোঁজা, পৃষ্ঠা-সহ), নইলে পেইজের শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/jobs';

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
