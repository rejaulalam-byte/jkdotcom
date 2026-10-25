<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\LegacyActivation;
use App\Models\UserRepository;

/**
 * পুরনো সাইট থেকে আনা কর্মীদের সক্রিয় করা (/admin/old-workers): কমন প্রাথমিক পাসওয়ার্ড বসানো এবং ফোনে জানানোর পর
 * "ফোন করা হয়েছে" টিক। টিকের ১৫ দিন পর্যন্ত কমন পাসওয়ার্ড কাজ করে (LegacyActivation)। অনুমতি: "registrations"।
 */
final class LegacyController
{
    private const PER_PAGE = 25;
    private const MIN_PASSWORD = 8;

    public function index(): void
    {
        $admin = AdminAuth::require('registrations');
        $state = isset(LegacyActivation::STATES[$_GET['state'] ?? '']) ? (string) $_GET['state'] : '';
        $filter = ['state' => $state, 'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100)];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = UserRepository::legacyList($filter, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        header('Cache-Control: no-store');
        View::render('admin/old-workers', [
            'admin' => $admin,
            'title' => 'পুরনো কর্মী সক্রিয় করা',
            'filter' => $filter,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => UserRepository::legacyCounts(),
            'common' => LegacyActivation::info(),
            'minPassword' => self::MIN_PASSWORD,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: id, do (call = টিক দিন বা নতুন করে ১৫ দিন, undo = টিক তুলুন), back। */
    public function call(): void
    {
        $admin = AdminAuth::require('registrations');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        $this->checkCsrf($back);

        $member = UserRepository::findById((int) ($_POST['id'] ?? 0));
        if ($member === null || LegacyActivation::state($member) === '' || LegacyActivation::state($member) === 'active') {
            $this->fail($back, 'এই কর্মীর জন্য টিক চলে না (পুরনো সাইটের কর্মী নন, বা আগেই সক্রিয়)।');
        }
        $call = ($_POST['do'] ?? '') !== 'undo';
        if ($call && LegacyActivation::state($member) === 'todo' && LegacyActivation::info() === null) {
            $this->fail($back, 'আগে উপরে কমন পাসওয়ার্ড বসান, তারপর টিক দিন।');
        }
        if (!UserRepository::markCalled((int) $member['id'], $call)) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }

        $name = (string) $member['name'];
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $call ? 'legacy_called' : 'legacy_call_undone', $name);
        $this->ok($back, $call
            ? $name . ' — "ফোন করা হয়েছে" টিক দেওয়া হলো; আগামী ' . bn(LegacyActivation::DAYS) . ' দিন কমন পাসওয়ার্ডে লগইন চলবে।'
            : $name . ' — টিক তুলে নেওয়া হলো; কমন পাসওয়ার্ড এখন কাজ করবে না।');
    }

    /** POST: password — সব পুরনো কর্মীর জন্য নতুন কমন প্রাথমিক পাসওয়ার্ড (আগেরটা বাতিল)। */
    public function password(): void
    {
        $admin = AdminAuth::require('registrations');
        $back = admin_base() . '/old-workers';
        $this->checkCsrf($back);

        $password = (string) ($_POST['common_password'] ?? '');
        if (strlen($password) < self::MIN_PASSWORD || strlen($password) > 72) {
            $this->fail($back, 'কমন পাসওয়ার্ড ' . bn(self::MIN_PASSWORD) . '–৭২ অক্ষরের হতে হবে।');
        }
        if (!LegacyActivation::setCommon(password_hash($password, PASSWORD_DEFAULT), (string) $admin['name'])) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'legacy_common_password', 'কমন প্রাথমিক পাসওয়ার্ড বসানো/বদলানো হয়েছে');
        $this->ok($back, 'কমন পাসওয়ার্ড বসানো হয়েছে। এটা মনে রাখুন বা নিরাপদ জায়গায় লিখে রাখুন; পরে আর দেখা যাবে না।');
    }

    private function checkCsrf(string $back): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ, নইলে পেইজের শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/old-workers';

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
