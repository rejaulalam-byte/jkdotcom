<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\PageImage;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\ServiceAdmin;
use App\Models\ServiceDirectory;
use App\Models\UserRepository;

/**
 * সেবার তালিকা (/admin/services): নতুন সেবা, নাম বদল, আইকন (নতুন ছবি / সাইটের আইকনে ফেরা), চালু/বন্ধ, ক্রম;
 * আর কর্মীদের নিজের লেখা পেশা (তালিকায় ছিল না): বানান ঠিক করে নতুন সেবা বানানো বা আগের কোনো সেবায় বসানো।
 * বন্ধ সেবা সাইটে (হোম, সেবার তালিকা, ফর্ম, কর্মী-তালিকা) দেখায় না; কর্মীদের পেশা মোছে না। অনুমতি: "services"।
 */
final class ServicesController
{
    public function index(): void
    {
        $admin = AdminAuth::require('services');
        $tab = (string) ($_GET['t'] ?? '');
        $tab = in_array($tab, ['', 'on', 'off', 'custom'], true) ? $tab : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

        $all = ServiceAdmin::all();
        $custom = UserRepository::customProfessions();
        $list = array_values(array_filter($all, static fn (array $s): bool => ($tab === '' || ($tab === 'on') === $s['is_active'])
            && ($q === '' || str_contains(mb_strtolower($s['name']), mb_strtolower($q)) || str_contains($s['slug'], strtolower($q)))));

        header('Cache-Control: no-store');
        View::render('admin/services', [
            'admin' => $admin,
            'title' => 'সেবার তালিকা',
            'tab' => $tab,
            'q' => $q,
            'list' => $tab === 'custom' ? [] : $list,
            'all' => $all,
            'counts' => [
                '' => count($all),
                'on' => count(array_filter($all, static fn (array $s): bool => $s['is_active'])),
                'off' => count(array_filter($all, static fn (array $s): bool => !$s['is_active'])),
                'custom' => count($custom),
            ],
            'workers' => UserRepository::workerCountsByService(),
            'custom' => $custom,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: do = add | rename | icon | icon_reset | on | off | up | down | custom_new | custom_assign, id, back। */
    public function action(): void
    {
        $admin = AdminAuth::require('services');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $do = (string) ($_POST['do'] ?? '');
        $id = (int) ($_POST['id'] ?? 0);
        $log = static fn (string $what, string $detail) => AdminRepository::log((int) $admin['id'], (string) $admin['name'], $what, $detail);

        // "সেবা সমূহ" পেইজের শিরোনাম-বর্ণের তালিকা (অ, আ … ক, খ …): নতুন বর্ণ যোগ, বাদ, ক্রম বদল
        if ($do === 'letters') {
            $error = ServiceDirectory::saveLetters(clean_input($_POST['letters'] ?? '', 500));
            if ($error !== null) {
                $this->fail($back, $error);
            }
            $log('service_letters', implode(' ', ServiceDirectory::letters()));
            Session::flash('admin_ok', 'বর্ণের তালিকা সংরক্ষণ হয়েছে; "সেবা সমূহ" পেইজে এখনই দেখা যাচ্ছে।');
            redirect($back);
        }

        if ($do === 'add' || $do === 'custom_new') {
            $name = clean_input($_POST['name'] ?? '', 120);
            $error = ServiceAdmin::add($name, clean_input($_POST['slug'] ?? '', 80), $slug, !empty($_POST['confirm_similar']));
            if ($error !== null) {
                $this->fail($back, $error);
            }
            $log('service_add', $name . ' (' . $slug . ')');
            $message = '"' . $name . '" সেবা যোগ হয়েছে; সাইটে এখনই দেখা যাচ্ছে।';
            if ($do === 'custom_new') {
                $text = clean_input($_POST['custom'] ?? '', 120);
                $n = UserRepository::assignCustomProfession($text, (string) $slug);
                $log('service_custom_assign', $text . ' -> ' . $slug . ' (' . $n . ')');
                $message .= ' "' . $text . '" লেখা ' . bn($n) . ' জন কর্মী এখন এই সেবায়।';
            }
            Session::flash('admin_ok', $message);
            redirect($back);
        }

        if ($do === 'custom_assign') {
            $text = clean_input($_POST['custom'] ?? '', 120);
            $service = ServiceAdmin::find($id);
            if ($service === null || $text === '') {
                $this->fail($back, 'কোন সেবায় বসাবেন, বাছুন।');
            }
            $n = UserRepository::assignCustomProfession($text, $service['slug']);
            $log('service_custom_assign', $text . ' -> ' . $service['slug'] . ' (' . $n . ')');
            Session::flash('admin_ok', '"' . $text . '" লেখা ' . bn($n) . ' জন কর্মী এখন "' . $service['name'] . '" সেবায়।');
            redirect($back);
        }

        $service = ServiceAdmin::find($id);
        if ($service === null) {
            $this->fail($back, 'সেবাটি পাওয়া যায়নি।');
        }
        switch ($do) {
            case 'rename':
                $name = clean_input($_POST['name'] ?? '', 120);
                $error = ServiceAdmin::rename($id, $name);
                if ($error !== null) {
                    $this->fail($back, $error);
                }
                $log('service_rename', $service['name'] . ' -> ' . $name);
                Session::flash('admin_ok', 'নাম বদলেছে: "' . $name . '"।');
                break;
            case 'icon':
                [$url, $error] = PageImage::store($_FILES['icon'] ?? null);
                if ($url === null || !ServiceAdmin::setIcon($id, $url)) {
                    $this->fail($back, $error ?? 'আইকন সংরক্ষণ করা যায়নি।');
                }
                $log('service_icon', $service['name']);
                Session::flash('admin_ok', '"' . $service['name'] . '"-এর নতুন আইকন বসেছে।');
                break;
            case 'icon_reset':
                if (!ServiceAdmin::setIcon($id, null)) {
                    $this->fail($back, 'আইকন ফেরানো যায়নি।');
                }
                $log('service_icon_reset', $service['name']);
                Session::flash('admin_ok', '"' . $service['name'] . '" সাইটের সঙ্গে আসা আইকনে ফিরেছে।');
                break;
            case 'on':
            case 'off':
                if (!ServiceAdmin::setActive($id, $do === 'on')) {
                    $this->fail($back, 'বদলানো যায়নি।');
                }
                $log('service_' . $do, $service['name']);
                Session::flash('admin_ok', '"' . $service['name'] . '" ' . ($do === 'on' ? 'চালু হয়েছে, সাইটে দেখা যাচ্ছে।' : 'বন্ধ হয়েছে, সাইটে আর দেখা যাবে না (কর্মীদের পেশা মোছেনি)।'));
                break;
            case 'up':
            case 'down':
                if (!ServiceAdmin::move($id, $do === 'up' ? -1 : 1)) {
                    $this->fail($back, 'ক্রম বদলানো যায়নি।');
                }
                break;
            default:
                $this->fail($back, 'অজানা কাজ।');
        }
        redirect($back);
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ, নইলে শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/services';

        return $path === $own || preg_match('#^' . preg_quote($own, '#') . '\?[A-Za-z0-9_=&%.\-+]*$#', $path) === 1 ? $path : $own;
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }
}
