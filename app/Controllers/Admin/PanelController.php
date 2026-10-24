<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminPermissions;
use App\Models\AdminRepository;
use App\Models\UserRepository;

/**
 * অ্যাডমিন প্যানেলের ভেতরের পেইজ (লগইনের পর): ড্যাশবোর্ড, নিজের অ্যাকাউন্ট (নাম, পাসওয়ার্ড, ইমেইল),
 * অ্যাডমিনদের তালিকা ও অনুমতি (শুধু সুপার অ্যাডমিন), কার্যলগ (শুধু সুপার অ্যাডমিন)।
 */
final class PanelController
{
    public function dashboard(): void
    {
        $admin = AdminAuth::require();
        $counts = UserRepository::countByRoleStatus();
        $this->render('admin/dashboard', $admin, 'ড্যাশবোর্ড', ['counts' => $counts]);
    }

    // ---- নিজের অ্যাকাউন্ট ----------------------------------------------------------------------

    public function account(): void
    {
        $admin = AdminAuth::require();
        $this->render('admin/account', $admin, 'আমার অ্যাকাউন্ট', [
            'emailCodePending' => AdminAuth::codePending('email-change'),
            'newEmail' => (string) ($_SESSION['admin_code']['email-change']['payload']['email'] ?? ''),
        ]);
    }

    public function accountName(): void
    {
        $admin = $this->post('/admin/account');
        $name = clean_input($_POST['name'] ?? '', 100);
        if (mb_strlen($name) < 2) {
            $this->back('/admin/account', ['নাম লিখুন।']);
        }
        AdminRepository::update((int) $admin['id'], ['name' => $name]);
        AdminRepository::log((int) $admin['id'], $name, 'name_changed', (string) $admin['name'] . ' → ' . $name);
        $this->ok('/admin/account', 'নাম বদলানো হয়েছে।');
    }

    public function accountPassword(): void
    {
        $admin = $this->post('/admin/account');
        $this->checkCurrentPassword($admin, '/admin/account');
        $new = (string) ($_POST['password'] ?? '');
        if (($error = AdminAuth::passwordError($new, (string) ($_POST['password_confirm'] ?? ''), (string) $admin['email'])) !== null) {
            $this->back('/admin/account', [$error]);
        }
        AdminRepository::update((int) $admin['id'], ['password_hash' => password_hash($new, PASSWORD_DEFAULT)]);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'password_changed');
        session_regenerate_id(true);
        $this->ok('/admin/account', 'পাসওয়ার্ড বদলানো হয়েছে।');
    }

    /** নতুন ইমেইল চাওয়া: এখনকার পাসওয়ার্ড লাগে, কোড যায় নতুন ইমেইলে (ভুল ঠিকানায় লক হয়ে যাওয়া ঠেকাতে)। */
    public function accountEmail(): void
    {
        $admin = $this->post('/admin/account');
        $this->checkCurrentPassword($admin, '/admin/account');
        $email = mb_strtolower(clean_input($_POST['email'] ?? '', 150));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->back('/admin/account', ['ইমেইল ঠিকানাটি সঠিক নয়।']);
        }
        $other = AdminRepository::findByEmail($email);
        if ($other !== null && (int) $other['id'] !== (int) $admin['id']) {
            $this->back('/admin/account', ['এই ইমেইলে আরেকজন অ্যাডমিন আছেন।']);
        }
        if (RateLimiter::tooMany('adm_code_' . $admin['id'], 5, 3600)) {
            $this->back('/admin/account', ['অনেকবার কোড চাওয়া হয়েছে। এক ঘণ্টা পরে আবার চেষ্টা করুন।']);
        }
        if (!AdminAuth::issueCode('email-change', $email, ['email' => $email])) {
            $this->back('/admin/account', ['ইমেইল পাঠানো যায়নি।']);
        }
        $this->ok('/admin/account', 'নতুন ইমেইলে একটি কোড পাঠানো হয়েছে। নিচে কোডটি লিখুন।');
    }

    public function accountEmailConfirm(): void
    {
        $admin = $this->post('/admin/account');
        $result = AdminAuth::checkCode('email-change', (string) ($_POST['code'] ?? ''));
        if (!$result['ok']) {
            $this->back('/admin/account', [(string) $result['message']]);
        }
        $email = (string) ($result['payload']['email'] ?? '');
        $other = AdminRepository::findByEmail($email);
        if ($email === '' || ($other !== null && (int) $other['id'] !== (int) $admin['id'])) {
            $this->back('/admin/account', ['এই ইমেইল ব্যবহার করা যাচ্ছে না।']);
        }
        AdminRepository::update((int) $admin['id'], ['email' => $email]);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'email_changed', (string) $admin['email'] . ' → ' . $email);
        $this->ok('/admin/account', 'ইমেইল বদলানো হয়েছে।');
    }

    // ---- অ্যাডমিনদের তালিকা (শুধু সুপার অ্যাডমিন) ----------------------------------------------

    public function admins(): void
    {
        $admin = AdminAuth::require(null, true);
        $this->render('admin/admins', $admin, 'অ্যাডমিন', ['admins' => AdminRepository::all()]);
    }

    public function adminsCreate(): void
    {
        $admin = $this->post('/admin/admins', true);
        $name = clean_input($_POST['name'] ?? '', 100);
        $email = mb_strtolower(clean_input($_POST['email'] ?? '', 150));
        $role = ($_POST['role'] ?? '') === 'super' ? 'super' : 'admin';
        $permissions = AdminPermissions::clean($_POST['permissions'] ?? []);
        if (mb_strlen($name) < 2 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->back('/admin/admins', ['নাম ও সঠিক ইমেইল দিন।']);
        }
        if ($role === 'admin' && $permissions === []) {
            $this->back('/admin/admins', ['অ্যাডমিনিস্ট্রেটরের জন্য অন্তত একটি অনুমতি বাছুন।']);
        }
        $id = AdminRepository::create($name, $email, $role, $permissions, null);
        if ($id === null) {
            $this->back('/admin/admins', ['এই ইমেইলে আগেই একজন আছেন, অথবা সংরক্ষণ করা যায়নি।']);
        }
        $sent = $this->sendInvite($id, $name, $email);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'admin_created', $name . ' (' . AdminAuth::roleLabel($role) . ')');
        $sent
            ? $this->ok('/admin/admins', 'তৈরি হয়েছে। ' . $email . '-এ পাসওয়ার্ড বসানোর লিংক পাঠানো হয়েছে (৩ দিন কাজ করবে)।')
            : $this->back('/admin/admins', ['অ্যাকাউন্ট তৈরি হয়েছে, কিন্তু ইমেইল যায়নি। "আমন্ত্রণ আবার পাঠান" চাপুন।']);
    }

    /** পাসওয়ার্ড বসানোর লিংক আবার পাঠানো (যিনি এখনও লিংক ব্যবহার করেননি বা হারিয়ে ফেলেছেন)। */
    public function adminsInvite(): void
    {
        $admin = $this->post('/admin/admins', true);
        $target = AdminRepository::findById((int) ($_POST['id'] ?? 0));
        if ($target === null || $target['status'] !== 'active') {
            $this->back('/admin/admins', ['অ্যাকাউন্টটি পাওয়া যায়নি বা বন্ধ আছে।']);
        }
        $this->sendInvite((int) $target['id'], (string) $target['name'], (string) $target['email'])
            ? $this->ok('/admin/admins', 'লিংক আবার পাঠানো হয়েছে।')
            : $this->back('/admin/admins', ['ইমেইল পাঠানো যায়নি।']);
    }

    public function adminsStatus(): void
    {
        $admin = $this->post('/admin/admins', true);
        $target = AdminRepository::findById((int) ($_POST['id'] ?? 0));
        $disable = ($_POST['action'] ?? '') === 'disable';
        if ($target === null) {
            $this->back('/admin/admins', ['অ্যাকাউন্টটি পাওয়া যায়নি।']);
        }
        if ($disable && (int) $target['id'] === (int) $admin['id']) {
            $this->back('/admin/admins', ['নিজের অ্যাকাউন্ট নিজে বন্ধ করা যায় না। অন্য সুপার অ্যাডমিনকে দিয়ে বন্ধ করান।']);
        }
        if ($disable && $target['role'] === 'super' && AdminRepository::activeSuperCount() <= 1) {
            $this->back('/admin/admins', ['শেষ চালু সুপার অ্যাডমিনকে বন্ধ করা যাবে না।']);
        }
        AdminRepository::update((int) $target['id'], ['status' => $disable ? 'disabled' : 'active']);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $disable ? 'admin_disabled' : 'admin_enabled', (string) $target['name']);
        $this->ok('/admin/admins', $disable ? 'অ্যাকাউন্ট বন্ধ করা হয়েছে।' : 'অ্যাকাউন্ট আবার চালু হয়েছে।');
    }

    public function adminsPermissions(): void
    {
        $admin = $this->post('/admin/admins', true);
        $target = AdminRepository::findById((int) ($_POST['id'] ?? 0));
        $permissions = AdminPermissions::clean($_POST['permissions'] ?? []);
        if ($target === null || $target['role'] !== 'admin') {
            $this->back('/admin/admins', ['শুধু অ্যাডমিনিস্ট্রেটরের অনুমতি বদলানো যায়।']);
        }
        if ($permissions === []) {
            $this->back('/admin/admins', ['অন্তত একটি অনুমতি রাখুন। কাউকে একেবারে আটকাতে চাইলে অ্যাকাউন্ট বন্ধ করুন।']);
        }
        AdminRepository::update((int) $target['id'], ['permissions' => json_encode($permissions)]);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'permissions_changed', (string) $target['name'] . ': ' . implode(', ', $permissions));
        $this->ok('/admin/admins', 'অনুমতি বদলানো হয়েছে।');
    }

    // ---- কার্যলগ (শুধু সুপার অ্যাডমিন) ---------------------------------------------------------

    public function log(): void
    {
        $admin = AdminAuth::require(null, true);
        $this->render('admin/log', $admin, 'কার্যলগ', ['entries' => AdminRepository::recentLog(200)]);
    }

    // ---- ভেতরের সহায়ক -------------------------------------------------------------------------

    /**
     * প্যানেলের কাঠামোয় ভিউ দেখায়।
     *
     * @param array<string,mixed> $admin
     * @param array<string,mixed> $data
     */
    private function render(string $view, array $admin, string $title, array $data = []): void
    {
        View::render($view, $data + [
            'admin' => $admin,
            'title' => $title,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /**
     * POST অনুরোধের পাহারা: লগইন, (ঐচ্ছিক) সুপার অ্যাডমিন, CSRF।
     *
     * @return array<string,mixed>
     */
    private function post(string $back, bool $superOnly = false): array
    {
        $admin = AdminAuth::require(null, $superOnly);
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->back($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }

        return $admin;
    }

    /** এখনকার পাসওয়ার্ড মেলায়; ভুল হলে ৫ বারের পর ১৫ মিনিট আটকায়। @param array<string,mixed> $admin */
    private function checkCurrentPassword(array $admin, string $back): void
    {
        $bucket = 'adm_pw_' . $admin['id'];
        if (RateLimiter::blocked($bucket, 5, 900)) {
            $this->back($back, ['এখনকার পাসওয়ার্ড ৫ বার ভুল লেখা হয়েছে। ১৫ মিনিট পরে আবার চেষ্টা করুন।']);
        }
        if (!password_verify((string) ($_POST['current_password'] ?? ''), (string) $admin['password_hash'])) {
            RateLimiter::hit($bucket, 900);
            $this->back($back, ['এখনকার পাসওয়ার্ডটি সঠিক নয়।']);
        }
        RateLimiter::clear($bucket);
    }

    /** নতুন অ্যাডমিনকে (বা লিংক হারানো অ্যাডমিনকে) পাসওয়ার্ড বসানোর এককালীন লিংক ইমেইল করে (৩ দিন)। */
    private function sendInvite(int $id, string $name, string $email): bool
    {
        $token = AdminAuth::newToken($id, 3 * 86400);

        return AdminAuth::mail($email, 'অ্যাডমিন প্যানেলে আপনাকে আমন্ত্রণ — jorurikaj.com', "{$name}, jorurikaj.com-এর অ্যাডমিন প্যানেলে আপনার অ্যাকাউন্ট খোলা হয়েছে।\nআপনার পাসওয়ার্ড বসাতে এই লিংকে যান (৩ দিন কাজ করবে, একবারই):\n\n" . AdminAuth::baseUrl() . '/admin/set-password/' . $token . "\n\nতারপর " . AdminAuth::baseUrl() . "/admin থেকে লগইন করবেন। লগইনে ইমেইলে একটি ৬ সংখ্যার কোড আসবে।\n\n— jorurikaj.com");
    }

    /** @param list<string> $errors */
    private function back(string $path, array $errors): never
    {
        Session::flash('admin_errors', $errors);
        redirect($path);
    }

    private function ok(string $path, string $message): never
    {
        Session::flash('admin_ok', $message);
        redirect($path);
    }
}
