<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\JobPostRepository;

/**
 * কাজের বিজ্ঞপ্তির তালিকা (/admin/jobs): দর্শকের জানানো বিজ্ঞপ্তি দেখা, লুকানো/আবার প্রকাশ, "কাজের ধরন"-এর বানান ঠিক করা,
 * চিরতরে মোছা। বিজ্ঞপ্তি জমা দেওয়ামাত্র প্রকাশিত হয় (অনুমোদন লাগে না), তাই এখানে দরকারে লুকানো হয়। অনুমতি: "inbox"।
 */
final class JobsController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $admin = AdminAuth::require('inbox');
        $tab = isset(JobPostRepository::TABS[$_GET['t'] ?? '']) ? (string) $_GET['t'] : '';
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
