<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\AreaRepository;
use App\Models\GeoRepository;

/**
 * "এলাকার নাম" তালিকা (<admin>/area-names, অনুমতি "registrations"): সদস্যরা তালিকার বাইরে যে এলাকার নাম লিখেছেন (যাচাইয়ের অপেক্ষায়),
 * সেগুলো অনুমোদন, বানান ঠিক করে সংরক্ষণ বা বাদ; আর অনুমোদিত অতিরিক্ত নামের তালিকা ও নতুন নাম যোগ। শুরুর তালিকা (ইউনিয়ন ইত্যাদি) ফাইলে থাকে।
 */
final class AreaNamesController
{
    public function index(): void
    {
        $admin = AdminAuth::require('registrations');
        $tab = ($_GET['tab'] ?? '') === 'approved' ? 'approved' : 'pending';
        header('Cache-Control: no-store');
        View::render('admin/area-names', [
            'admin' => $admin,
            'title' => 'এলাকার নাম',
            'tab' => $tab,
            'rows' => AreaRepository::listFor($tab),
            'pendingCount' => AreaRepository::pendingCount(),
            'geo' => GeoRepository::all(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: do (approve | rename | delete | add), id, name, district_id, tab। */
    public function action(): void
    {
        $admin = AdminAuth::require('registrations');
        $tab = ($_POST['tab'] ?? '') === 'approved' ? 'approved' : 'pending';
        $back = admin_base() . '/area-names?tab=' . $tab;
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            Session::flash('admin_errors', ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            redirect($back);
        }

        $id = (int) ($_POST['id'] ?? 0);
        $name = AreaRepository::clean($_POST['name'] ?? '');
        $do = (string) ($_POST['do'] ?? '');
        $problem = null;
        $done = false;
        $log = '';
        $message = '';
        switch ($do) {
            case 'approve':
                $done = AreaRepository::approve($id);
                [$log, $message] = ['area_approve', 'তালিকায় যোগ হয়েছে; এখন থেকে সবার সাজেশনে আসবে।'];
                break;
            case 'rename':
                if ($name === '') {
                    $problem = 'নতুন নামটা লিখুন।';
                    break;
                }
                $done = AreaRepository::rename($id, $name);
                [$log, $message] = ['area_rename', 'নাম ঠিক হয়েছে (যাঁরা পুরনো বানানে লিখেছিলেন তাঁদেরটাও বদলেছে) এবং তালিকায় যোগ হয়েছে।'];
                break;
            case 'delete':
                $done = AreaRepository::delete($id);
                [$log, $message] = ['area_delete', 'তালিকা থেকে বাদ গেছে (সদস্যদের লেখা নাম যেমন আছে তেমনই থাকবে)।'];
                break;
            case 'add':
                if ($name === '') {
                    $problem = 'নামটা লিখুন।';
                } elseif (AreaRepository::isKnown($name)) {
                    $problem = 'এই নাম তালিকায় আগে থেকেই আছে।';
                } else {
                    $done = AreaRepository::add($name, 0, (int) ($_POST['district_id'] ?? 0));
                    [$log, $message] = ['area_add', 'নতুন নাম তালিকায় যোগ হয়েছে।'];
                }
                break;
            default:
                $problem = 'অজানা কাজ।';
        }
        if ($problem !== null || !$done) {
            Session::flash('admin_errors', [$problem ?? 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।']);
            redirect($back);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $log, $name !== '' ? $name : '#' . $id);
        Session::flash('admin_ok', $message);
        redirect($back);
    }
}
