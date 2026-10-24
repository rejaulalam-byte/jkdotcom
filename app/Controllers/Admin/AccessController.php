<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Env;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminPermissions;
use App\Models\AdminRepository;

/**
 * অ্যাডমিন প্যানেলে ঢোকার পথ: প্রথম সুপার অ্যাডমিন বানানো (/admin/setup, একবারই), লগইন + ইমেইলের কোড,
 * লগআউট, পাসওয়ার্ড ভুলে যাওয়া ও এককালীন লিংকে পাসওয়ার্ড বসানো (নতুন অ্যাডমিনের আমন্ত্রণও এই পথে)।
 */
final class AccessController
{
    /** আইডি না মিললেও একই সময় লাগানোর নকল হ্যাশ: ইমেইল আছে কি না সময় মেপে জানা যায় না। */
    private const DUMMY_HASH = '$2y$10$jAX9q737WKgze0Ox2Hg28OtOoPWtS0.WFdEf2wUrFQMl73taY7U3e';
    private const WINDOW = 900;

    // ---- প্রথম সুপার অ্যাডমিন ------------------------------------------------------------------

    public function setupForm(): void
    {
        $this->plainHeaders();
        if (AdminRepository::count() > 0) {
            $this->notFound();
        }
        View::render('admin/setup', [
            'title' => 'প্রথম সুপার অ্যাডমিন',
            'enabled' => $this->setupKey() !== '',
            'errors' => Session::pull('admin_errors', []),
            'old' => Session::pull('admin_old', []),
        ], 'layouts/admin');
    }

    public function setupStore(): void
    {
        $this->plainHeaders();
        if (AdminRepository::count() > 0 || $this->setupKey() === '') {
            $this->notFound();
        }
        $this->guardPost('adm_setup', 10, '/admin/setup');

        $key = (string) ($_POST['setup_key'] ?? '');
        $name = clean_input($_POST['name'] ?? '', 100);
        $email = mb_strtolower(clean_input($_POST['email'] ?? '', 150));
        $password = (string) ($_POST['password'] ?? '');
        $errors = [];
        if (!hash_equals($this->setupKey(), $key)) {
            $errors[] = 'সেটআপ-কী সঠিক নয়।';
        }
        if (mb_strlen($name) < 2) {
            $errors[] = 'নাম লিখুন।';
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়।';
        }
        if (($error = AdminAuth::passwordError($password, (string) ($_POST['password_confirm'] ?? ''), $email)) !== null) {
            $errors[] = $error;
        }
        if ($errors !== []) {
            $this->back('/admin/setup', $errors, ['name' => $name, 'email' => $email]);
        }

        $id = AdminRepository::create($name, $email, 'super', [], password_hash($password, PASSWORD_DEFAULT));
        if ($id === null) {
            $this->back('/admin/setup', ['সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।']);
        }
        AdminRepository::log($id, $name, 'setup', 'প্রথম সুপার অ্যাডমিন তৈরি');
        Session::flash('admin_ok', 'সুপার অ্যাডমিন তৈরি হয়েছে। এখন লগইন করুন।');
        redirect('/admin/login');
    }

    // ---- লগইন + ইমেইলের কোড --------------------------------------------------------------------

    public function loginForm(): void
    {
        $this->plainHeaders();
        if (AdminAuth::current() !== null) {
            redirect('/admin');
        }
        View::render('admin/login', [
            'title' => 'অ্যাডমিন লগইন',
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
            'old' => Session::pull('admin_old', []),
        ], 'layouts/admin');
    }

    public function loginStore(): void
    {
        $this->plainHeaders();
        $this->checkPost('/admin/login');
        if (RateLimiter::count('adm_login_ip', self::WINDOW) >= 20) {
            $this->back('/admin/login', ['অনেকবার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পরে আবার চেষ্টা করুন।']);
        }

        $email = mb_strtolower(clean_input($_POST['email'] ?? '', 150));
        $password = (string) ($_POST['password'] ?? '');
        $bucket = 'adm_login_' . hash('sha256', $email);
        if (RateLimiter::count($bucket, self::WINDOW) >= 5) {
            $this->back('/admin/login', ['এই ইমেইলে ৫ বার ভুল চেষ্টা হয়েছে। ১৫ মিনিট পরে আবার চেষ্টা করুন।'], ['email' => $email]);
        }

        $admin = $email !== '' && strlen($password) <= 200 ? AdminRepository::findByEmail($email) : null;
        $hash = $admin !== null && (string) $admin['password_hash'] !== '' ? (string) $admin['password_hash'] : self::DUMMY_HASH;
        $ok = password_verify($password, $hash) && $admin !== null && (string) $admin['password_hash'] !== '';
        if (!$ok) {
            RateLimiter::hit('adm_login_ip', self::WINDOW);
            RateLimiter::hit($bucket, self::WINDOW);
            $this->back('/admin/login', ['ইমেইল বা পাসওয়ার্ড সঠিক নয়।'], ['email' => $email]);
        }
        if ($admin['status'] !== 'active') {
            $this->back('/admin/login', ['এই অ্যাকাউন্টটি বন্ধ আছে। সুপার অ্যাডমিনের সঙ্গে যোগাযোগ করুন।'], ['email' => $email]);
        }
        RateLimiter::clear($bucket);

        if (RateLimiter::tooMany('adm_code_' . $admin['id'], 5, 3600)) {
            $this->back('/admin/login', ['অনেকবার কোড চাওয়া হয়েছে। এক ঘণ্টা পরে আবার চেষ্টা করুন।']);
        }
        if (!AdminAuth::issueCode('login', (string) $admin['email'], ['admin_id' => (int) $admin['id']])) {
            $this->back('/admin/login', ['ইমেইল পাঠানো যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }
        redirect('/admin/verify');
    }

    public function verifyForm(): void
    {
        $this->plainHeaders();
        if (!AdminAuth::codePending('login')) {
            redirect('/admin/login');
        }
        $admin = AdminRepository::findById((int) ($_SESSION['admin_code']['login']['payload']['admin_id'] ?? 0));
        View::render('admin/verify', [
            'title' => 'ইমেইলের কোড',
            'maskedEmail' => $admin !== null ? mask_email((string) $admin['email']) : '',
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    public function verifyStore(): void
    {
        $this->plainHeaders();
        $this->checkPost('/admin/login');
        $result = AdminAuth::checkCode('login', (string) ($_POST['code'] ?? ''));
        if (!$result['ok']) {
            $this->back(AdminAuth::codePending('login') ? '/admin/verify' : '/admin/login', [(string) $result['message']]);
        }
        $admin = AdminRepository::findById((int) ($result['payload']['admin_id'] ?? 0));
        if ($admin === null || $admin['status'] !== 'active') {
            $this->back('/admin/login', ['এই অ্যাকাউন্টটি এখন চালু নেই।']);
        }
        AdminAuth::login($admin);
        redirect('/admin');
    }

    /** কোড আবার পাঠানো (ঘণ্টায় ৫টির বেশি নয়)। */
    public function verifyResend(): void
    {
        $this->plainHeaders();
        $this->checkPost('/admin/login');
        $admin = AdminRepository::findById((int) ($_SESSION['admin_code']['login']['payload']['admin_id'] ?? 0));
        if ($admin === null || $admin['status'] !== 'active') {
            redirect('/admin/login');
        }
        if (RateLimiter::tooMany('adm_code_' . $admin['id'], 5, 3600)) {
            $this->back('/admin/verify', ['অনেকবার কোড চাওয়া হয়েছে। এক ঘণ্টা পরে আবার চেষ্টা করুন।']);
        }
        if (!AdminAuth::issueCode('login', (string) $admin['email'], ['admin_id' => (int) $admin['id']])) {
            $this->back('/admin/verify', ['ইমেইল পাঠানো যায়নি।']);
        }
        Session::flash('admin_ok', 'নতুন কোড পাঠানো হয়েছে।');
        redirect('/admin/verify');
    }

    public function logout(): void
    {
        if (Security::verifyCsrf($_POST['_token'] ?? null)) {
            AdminAuth::logout();
        }
        redirect('/admin/login');
    }

    // ---- পাসওয়ার্ড ভুলে যাওয়া ও এককালীন লিংক ------------------------------------------------

    public function forgotForm(): void
    {
        $this->plainHeaders();
        View::render('admin/forgot', [
            'title' => 'পাসওয়ার্ড ভুলে গেছেন',
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** সবসময় একই উত্তর: ইমেইল নিবন্ধিত কি না বাইরে থেকে জানা যায় না। */
    public function forgotStore(): void
    {
        $this->plainHeaders();
        $this->guardPost('adm_forgot', 6, '/admin/forgot');
        $email = mb_strtolower(clean_input($_POST['email'] ?? '', 150));
        $admin = filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? AdminRepository::findByEmail($email) : null;
        if ($admin !== null && $admin['status'] === 'active' && !RateLimiter::tooMany('adm_forgot_' . $admin['id'], 3, 3600)) {
            $token = AdminAuth::newToken((int) $admin['id'], 3600);
            AdminAuth::mail((string) $admin['email'], 'পাসওয়ার্ড বদলের লিংক — jorurikaj.com', "অ্যাডমিন প্যানেলের পাসওয়ার্ড বদলাতে এই লিংকে যান (১ ঘণ্টা কাজ করবে, একবারই):\n\n" . AdminAuth::baseUrl() . '/admin/set-password/' . $token . "\n\nআপনি চেয়ে না থাকলে এই ইমেইল উপেক্ষা করুন।\n\n— jorurikaj.com");
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'forgot_password');
        }
        Session::flash('admin_ok', 'ইমেইলটি নিবন্ধিত থাকলে পাসওয়ার্ড বদলের লিংক পাঠানো হয়েছে। ইনবক্স (ও স্প্যাম) দেখুন।');
        redirect('/admin/forgot');
    }

    /** @param array<string,mixed> $meta slug = ইমেইলের লিংকের টোকেন */
    public function setPasswordForm(array $meta = []): void
    {
        $this->plainHeaders();
        $admin = $this->adminOfToken((string) ($meta['slug'] ?? ''));
        View::render('admin/set-password', [
            'title' => 'পাসওয়ার্ড বসান',
            'valid' => $admin !== null,
            'adminName' => $admin !== null ? (string) $admin['name'] : '',
            'token' => (string) ($meta['slug'] ?? ''),
            'errors' => Session::pull('admin_errors', []),
        ], 'layouts/admin');
    }

    /** @param array<string,mixed> $meta */
    public function setPasswordStore(array $meta = []): void
    {
        $this->plainHeaders();
        $token = (string) ($meta['slug'] ?? '');
        $this->guardPost('adm_setpw', 20, '/admin/set-password/' . $token);
        $admin = $this->adminOfToken($token);
        if ($admin === null) {
            $this->back('/admin/set-password/' . $token, ['লিংকটির মেয়াদ শেষ বা ব্যবহার হয়ে গেছে।']);
        }
        $password = (string) ($_POST['password'] ?? '');
        if (($error = AdminAuth::passwordError($password, (string) ($_POST['password_confirm'] ?? ''), (string) $admin['email'])) !== null) {
            $this->back('/admin/set-password/' . $token, [$error]);
        }
        AdminRepository::update((int) $admin['id'], [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'token_hash' => null, 'token_expires' => null,
        ]);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'password_set');
        Session::flash('admin_ok', 'পাসওয়ার্ড বসানো হয়েছে। এখন লগইন করুন।');
        redirect('/admin/login');
    }

    // ---- ভেতরের সহায়ক -------------------------------------------------------------------------

    /** @return array<string,mixed>|null */
    private function adminOfToken(string $token): ?array
    {
        $admin = preg_match('/^[a-f0-9]{64}$/', $token) === 1 ? AdminRepository::findByTokenHash(hash('sha256', $token)) : null;

        return $admin !== null && $admin['status'] === 'active' ? $admin : null;
    }

    private function setupKey(): string
    {
        return trim(Env::get('ADMIN_SETUP_KEY', '') ?? '');
    }

    private function plainHeaders(): void
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
    }

    /** CSRF ও লুকানো ফাঁদ-ঘর দেখে; ঠিক না হলে $back-এ ফেরত পাঠায়। */
    private function checkPost(string $back): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->back($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
    }

    /** checkPost + ঘণ্টায় অনুরোধের সীমা। */
    private function guardPost(string $bucket, int $perHour, string $back): void
    {
        $this->checkPost($back);
        if (RateLimiter::tooMany($bucket, $perHour, 3600)) {
            $this->back($back, ['অনেকবার চেষ্টা করা হয়েছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
        }
    }

    /**
     * @param list<string> $errors
     * @param array<string,string> $old
     */
    private function back(string $path, array $errors, array $old = []): never
    {
        Session::flash('admin_errors', $errors);
        Session::flash('admin_old', $old);
        redirect($path);
    }

    private function notFound(): never
    {
        http_response_code(404);
        View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
        exit;
    }
}
