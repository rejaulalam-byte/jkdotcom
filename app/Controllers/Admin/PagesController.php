<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\CustomPages;

/**
 * নতুন পেইজ (<admin>/pages): বানানো, নাম/শিরোনাম/বর্ণনা বদল, প্রকাশ ও লুকানো, মোছা। লেখা নিজে লাইভ পেইজ এডিটে
 * (পূর্ণ এডিটর)। অনুমতি "pages": সুপার অ্যাডমিন ও অ্যাডমিনিস্ট্রেটর দুজনেই সব পারেন (মোছাও, প্রশ্ন করে নিশ্চিত হয়ে)।
 */
final class PagesController
{
    public function index(): void
    {
        $admin = AdminAuth::require('pages');
        header('Cache-Control: no-store');
        View::render('admin/pages', [
            'admin' => $admin,
            'title' => 'নতুন পেইজ',
            'pages' => CustomPages::all(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: do = add | update | publish | hide | delete, slug, name, heading, address (নতুন হলে), description। */
    public function action(): void
    {
        $admin = AdminAuth::require('pages');
        $back = admin_base() . '/pages';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $do = (string) ($_POST['do'] ?? '');
        $slug = (string) ($_POST['slug'] ?? '');
        $name = clean_input($_POST['name'] ?? '', 80);
        $heading = clean_input($_POST['heading'] ?? '', 150);
        $description = clean_input($_POST['description'] ?? '', 300);
        $log = static fn (string $what, string $detail) => AdminRepository::log((int) $admin['id'], (string) $admin['name'], $what, $detail);

        if ($do === 'add') {
            $error = CustomPages::add($name, $heading, clean_input($_POST['address'] ?? '', 60), $description, $made);
            if ($error !== null) {
                $this->fail($back, $error);
            }
            $log('page_add', $name . ' (' . CustomPages::url((string) $made) . ')');
            Session::flash('admin_ok', '"' . $name . '" পেইজ বানানো হয়েছে, এখনও লুকানো। "লেখা লিখুন" চেপে লেখা দিন, তারপর "প্রকাশ করুন"।');
            redirect($back . '#p-' . $made);
        }

        $page = CustomPages::find($slug);
        if ($page === null) {
            $this->fail($back, 'পেইজটি পাওয়া যায়নি।');
        }
        $error = match ($do) {
            'update' => CustomPages::update($slug, $name, $heading, $description),
            'publish' => CustomPages::setHidden($slug, false),
            'hide' => CustomPages::setHidden($slug, true),
            'delete' => CustomPages::delete($slug),
            default => 'অজানা কাজ।',
        };
        if ($error !== null) {
            $this->fail($back . '#p-' . $slug, $error);
        }
        $log('page_' . $do, $page['name'] . ' (' . CustomPages::url($slug) . ')');
        Session::flash('admin_ok', match ($do) {
            'update' => '"' . $name . '" পেইজের নাম/শিরোনাম সংরক্ষণ হয়েছে।',
            'publish' => '"' . $page['name'] . '" প্রকাশিত হয়েছে; এখন সবাই দেখতে পাবেন।',
            'hide' => '"' . $page['name'] . '" লুকানো হয়েছে; সাধারণ দর্শক আর দেখবেন না।',
            default => '"' . $page['name'] . '" পেইজ মুছে ফেলা হয়েছে। মেনু বা অন্য পেইজে এর লিংক থাকলে সেটা সরিয়ে দিন।',
        });
        redirect($back . ($do === 'delete' ? '' : '#p-' . $slug));
    }

    private function fail(string $back, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($back);
    }
}
