<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\AuthController;
use App\Core\AdminAuth;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;

/**
 * আটকানো লগইন (/admin/locks): কর্মী/প্রতিনিধির লগইনে ভুল পাসওয়ার্ডের হিসাব দেখা ও "আটকানো খুলুন"। অনুমতি: "locks"।
 * হিসাব storage/ratelimit-এর ফাইলে (RateLimiter); প্রতি লগইন-আইডিতে ৫ ভুলে "নরম লক" (সঠিক পাসওয়ার্ড তখনও ঢোকায়),
 * ১০ ভুলে "কড়া লক"; এক ডিভাইসে ২০ ভুলে ডিভাইস আটকে যায়। ১৫ মিনিট পর নিজেই খোলে; অ্যাডমিন চাইলে আগেই খুলতে পারেন।
 */
final class LocksController
{
    public function index(): void
    {
        $admin = AdminAuth::require('locks');
        $window = AuthController::WINDOW;

        // লগইন-আইডি অনুযায়ী: বালতির নাম "login_<worker|agent>_<আইডি>"; একই আইডি কয়েক ডিভাইস থেকে চেষ্টা হলে একসঙ্গে ধরা
        $ids = [];
        foreach (RateLimiter::find('login_', $window) as $row) {
            if (preg_match('/^login_(worker|agent)_(.+)$/', $row['bucket'], $m) !== 1) {
                continue;
            }
            $key = $row['bucket'];
            $ids[$key] ??= ['bucket' => $key, 'role' => $m[1], 'login_id' => $m[2], 'devices' => 0, 'max' => 0, 'last' => 0, 'free_at' => 0];
            $count = count($row['hits']);
            $ids[$key]['devices']++;
            $ids[$key]['last'] = max($ids[$key]['last'], (int) end($row['hits']));
            if ($count > $ids[$key]['max']) {
                $ids[$key]['max'] = $count;
                // কড়া লক কাটে যখন গোনা সীমার নিচে নামে: সীমা-পূরণ-করা চেষ্টাগুলোর মধ্যে সবচেয়ে পুরনোটার মেয়াদ শেষে
                $ids[$key]['free_at'] = $count >= AuthController::ID_HARD_LIMIT ? $row['hits'][$count - AuthController::ID_HARD_LIMIT] + $window : 0;
            }
        }
        usort($ids, static fn (array $a, array $b): int => [$b['max'], $b['last']] <=> [$a['max'], $a['last']]);

        // ডিভাইস অনুযায়ী (আইপি-ভিত্তিক): বালতি ঠিক "login"
        $devices = [];
        foreach (RateLimiter::find('login', $window) as $row) {
            if ($row['bucket'] !== 'login') {
                continue;
            }
            $count = count($row['hits']);
            $devices[] = [
                'file' => $row['file'], 'count' => $count, 'last' => (int) end($row['hits']),
                'free_at' => $count >= AuthController::DEVICE_HARD_LIMIT ? $row['hits'][$count - AuthController::DEVICE_HARD_LIMIT] + $window : 0,
            ];
        }
        usort($devices, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        header('Cache-Control: no-store');
        View::render('admin/locks', [
            'admin' => $admin,
            'title' => 'আটকানো লগইন',
            'ids' => $ids,
            'devices' => $devices,
            'soft' => AuthController::ID_SOFT_LIMIT,
            'hard' => AuthController::ID_HARD_LIMIT,
            'deviceHard' => AuthController::DEVICE_HARD_LIMIT,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: login_id (হাতে লেখা মোবাইল/ইমেইল), অথবা bucket (তালিকার একটা লগইন-আইডি, সব ডিভাইস থেকে), অথবা file (একটা ডিভাইসের হিসাব)। */
    public function release(): void
    {
        $admin = AdminAuth::require('locks');
        $back = admin_base() . '/locks';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            Session::flash('admin_errors', ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            redirect($back);
        }

        // হাতে লেখা মোবাইল/ইমেইল: দুই পাশের (কর্মী ও প্রতিনিধি) লগইন-আইডির হিসাবই মোছা হয়; তালিকায় না থাকলেও চলে
        $typed = trim((string) ($_POST['login_id'] ?? ''));
        if ($typed !== '') {
            $id = str_contains($typed, '@') ? mb_strtolower(mb_substr($typed, 0, 150)) : normalize_phone($typed);
            $valid = str_contains($typed, '@') ? filter_var($id, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^01[3-9][0-9]{8}$/', $id) === 1;
            if (!$valid) {
                Session::flash('admin_errors', ['মোবাইল (১১ সংখ্যা, যেমন 01712345678) বা সঠিক ইমেইল লিখুন।']);
                redirect($back);
            }
            $removed = RateLimiter::clearAll('login_worker_' . $id) + RateLimiter::clearAll('login_agent_' . $id);
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'lock_release_typed', $id);
            Session::flash('admin_ok', $removed > 0
                ? $id . ' — আটকানো খুলে দেওয়া হয়েছে; এখন আবার লগইন করতে পারবেন।'
                : $id . ' — এখন কোনো আটকানো হিসাব নেই (আগেই খুলে গেছে বা কখনও আটকায়নি), তাই লগইনে কোনো বাধা নেই।');
            redirect($back);
        }

        $bucket = (string) ($_POST['bucket'] ?? '');
        if ($bucket !== '') {
            if (preg_match('/^login_(worker|agent)_.{1,150}$/', $bucket) !== 1 || RateLimiter::clearAll($bucket) === 0) {
                Session::flash('admin_errors', ['এই আইডির কোনো আটকানো হিসাব পাওয়া যায়নি (হয়তো নিজেই খুলে গেছে)।']);
                redirect($back);
            }
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'lock_release', $bucket);
            Session::flash('admin_ok', 'আটকানো খুলে দেওয়া হয়েছে; এখন তিনি আবার লগইন করতে পারবেন।');
            redirect($back);
        }

        if (!RateLimiter::clearFile((string) ($_POST['file'] ?? ''))) {
            Session::flash('admin_errors', ['এই ডিভাইসের হিসাব পাওয়া যায়নি (হয়তো নিজেই খুলে গেছে)।']);
            redirect($back);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'lock_release_device', 'একটি ডিভাইস');
        Session::flash('admin_ok', 'ডিভাইসের আটকানো খুলে দেওয়া হয়েছে।');
        redirect($back);
    }
}
