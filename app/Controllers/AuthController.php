<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\PhotoUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\LegacyActivation;
use App\Models\UserRepository;

/**
 * লগইন (পেশাজীবী /login, প্রতিনিধি /login/agent), লগআউট, স্বাগত পেইজ (/welcome)
 * এবং সদস্যের ছবি দেখানো (/photo/<নাম>)।
 */
final class AuthController
{
    /** ব্যর্থ-চেষ্টার হিসাবের মেয়াদ (১৫ মিনিট) ও সীমা: আইডি-প্রতি নরম ৫, কড়া ১০; ডিভাইস-প্রতি কড়া ২০। */
    /** লগইনের ভুল-চেষ্টার সীমা (অ্যাডমিনের "আটকানো লগইন" পেইজও এগুলো ব্যবহার করে)। */
    public const WINDOW = 900;
    public const ID_SOFT_LIMIT = 5;
    public const ID_HARD_LIMIT = 10;
    public const DEVICE_HARD_LIMIT = 20;

    /** আসল ব্যবহারকারী না মিললেও একই সময় লাগানোর জন্য নকল হ্যাশ: ফলে মোবাইল-নাম্বার আছে কি না সময় মেপে জানা যায় না। */
    private const DUMMY_HASH = '$2y$10$jAX9q737WKgze0Ox2Hg28OtOoPWtS0.WFdEf2wUrFQMl73taY7U3e';

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function loginForm(array $meta = []): void
    {
        $role = $this->role($meta);
        if (Auth::user() !== null) {
            redirect('/profile');
        }

        $key = 'login.' . $role . '.';
        View::render('pages/login', [
            'bodyClass' => 'page-login',
            'title' => content($key . 'crumb') . ' — Jorurikaj.com',
            'heading' => content($key . 'banner'),
            'subheading' => content($key . 'heading'),
            'crumb' => content($key . 'crumb'),
            'role' => $role,
            'action' => $role === 'agent' ? '/login/agent' : '/login',
            'errors' => Session::pull('login_errors', []),
            'oldPhone' => (string) Session::pull('login_phone', ''),
        ]);
    }

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function loginStore(array $meta = []): void
    {
        $role = $this->role($meta);
        $back = $role === 'agent' ? '/login/agent' : '/login';

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, [msg('msg.csrf')]);
        }
        // লুকানো "website" ঘর: স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        // সীমা শুধু ব্যর্থ লগইনে গোনা হয়: সঠিক লগইন কখনও আটকায় না
        if (RateLimiter::count('login', self::WINDOW) >= self::DEVICE_HARD_LIMIT) {
            $this->fail($back, [msg('login.msg.device_locked')]);
        }

        // লগইন আইডি = ইমেইল অথবা রেজিস্টার্ড মোবাইল নাম্বার (@ থাকলে ইমেইল)
        $id = trim((string) ($_POST['login_id'] ?? ''));
        $byEmail = str_contains($id, '@');
        $id = $byEmail ? mb_strtolower(mb_substr($id, 0, 150)) : normalize_phone($id);
        $password = (string) ($_POST['password'] ?? '');
        $validId = $byEmail ? filter_var($id, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^01[3-9][0-9]{8}$/', $id) === 1;
        if (!$validId || $password === '' || strlen($password) > 200) {
            $this->fail($back, [msg('login.msg.empty')], $id);
        }
        // একই আইডিতে বারবার অনুমান ঠেকাতে আইডি-ভিত্তিক সীমা: ৫ ভুলে "নরম লক" (সঠিক পাসওয়ার্ড তখনও ঢুকতে দেয়,
        // কিন্তু বার্তা দেখায়), ১০ ভুলে "কড়া লক" (সঠিক পাসওয়ার্ডও ঢোকে না)
        $bucket = 'login_' . $role . '_' . $id;
        $fails = RateLimiter::count($bucket, self::WINDOW);
        if ($fails >= self::ID_HARD_LIMIT) {
            $this->fail($back, [msg('login.msg.hard_locked')], $id);
        }

        // একই ইমেইল/মোবাইলে দুই পাশেই অ্যাকাউন্ট থাকতে পারে, তাই এই পেইজের পাশেরটাই খোঁজা হয়
        $find = static fn (?string $r): ?array => $byEmail ? UserRepository::findByEmail($id, $r) : UserRepository::findByPhone($id, $r);
        $user = $find($role);
        $ok = password_verify($password, $user !== null ? (string) $user['password_hash'] : self::DUMMY_HASH);
        // পুরনো সাইটের কর্মী: অ্যাডমিন ফোন করে টিক দিলে ১৫ দিন কমন প্রাথমিক পাসওয়ার্ডে ঢোকা যায় (তারপর /account/setup-এ নিজের পাসওয়ার্ড)
        $viaCommon = false;
        if ($user !== null && !$ok && LegacyActivation::accepts($user, $password)) {
            $ok = $viaCommon = true;
        }
        if ($user === null || !$ok) {
            // এই পাশে নেই কিন্তু অন্য পাশে আছে ও পাসওয়ার্ড ঠিক: ভুল লগইন-পেইজের কথা জানানো নিরাপদ
            $other = $user === null ? $find($role === 'agent' ? 'worker' : 'agent') : null;
            if ($other !== null && password_verify($password, (string) $other['password_hash'])) {
                $this->fail($back, [$role === 'agent'
                    ? msg('login.msg.worker_account')
                    : msg('login.msg.agent_account')], $id);
            }
            RateLimiter::hit('login', self::WINDOW);
            RateLimiter::hit($bucket, self::WINDOW);
            $this->fail($back, [$fails + 1 >= self::ID_SOFT_LIMIT
                ? msg('login.msg.soft_locked')
                : msg('login.msg.wrong')], $id);
        }
        // পাসওয়ার্ড ঠিক আছে: এবার অ্যাকাউন্টের অবস্থা বলা নিরাপদ
        if ($user['status'] === 'pending') {
            $this->fail($back, [msg('login.msg.pending')], $id);
        }
        if ($user['status'] !== 'approved') {
            $this->fail($back, [msg('login.msg.inactive')], $id);
        }

        // নরম লকে থাকা সদস্য সঠিক পাসওয়ার্ড দিলে ঢোকে; বার্তাটা প্রোফাইলে দেখানো হয়, গোনা শূন্য হয়
        if ($fails >= self::ID_SOFT_LIMIT) {
            Session::flash('login_unlocked', true);
        }
        RateLimiter::clear($bucket);

        // কমন পাসওয়ার্ডে ঢুকলে সেটা কর্মীর নিজের হ্যাশ হিসেবে কখনও রাখা হয় না
        $rehash = !$viaCommon && password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null;
        UserRepository::touchLogin((int) $user['id'], $rehash);
        Auth::login((int) $user['id']);
        redirect(UserRepository::needsSetup($user) ? '/account/setup' : ((int) ($user['must_change_password'] ?? 0) === 1 ? '/account/password' : '/profile'));
    }

    /** পাসওয়ার্ড পরিবর্তনের পেইজ (লগইন না থাকলে লগইনে পাঠায়)। */
    public function passwordForm(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (UserRepository::needsSetup($user)) {
            redirect('/account/setup');
        }

        View::render('pages/password', [
            'bodyClass' => 'page-login',
            'title' => content('password.crumb') . ' — Jorurikaj.com',
            'heading' => content('password.banner'),
            'forced' => (int) ($user['must_change_password'] ?? 0) === 1,
            'errors' => Session::pull('password_errors', []),
        ]);
    }

    /** পাসওয়ার্ড বদলায়: বর্তমান পাসওয়ার্ড মিলতে হবে; সফল হলে সেশন-id নতুন হয় ও must_change_password মুছে যায়। */
    public function passwordStore(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        $back = '/account/password';

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->failPassword([msg('msg.csrf')]);
        }
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        // বর্তমান পাসওয়ার্ড অনুমানের চেষ্টা ঠেকাতে সদস্য-ভিত্তিক সীমা
        if (RateLimiter::blocked('pwchange_' . $user['id'], 5, 900)) {
            $this->failPassword([msg('password.msg.locked')]);
        }

        $current = (string) ($_POST['current_password'] ?? '');
        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        $errors = [];
        if (!password_verify($current, (string) $user['password_hash'])) {
            RateLimiter::hit('pwchange_' . $user['id'], 900);
            $errors[] = msg('password.msg.current_wrong');
        }
        // bcrypt ৭২ বাইটের পরের অংশ উপেক্ষা করে, তাই বেশি লম্বা পাসওয়ার্ড আটকানো হয়
        if (mb_strlen($new) < 8) {
            $errors[] = msg('msg.new_password_short');
        } elseif (strlen($new) > 72) {
            $errors[] = msg('msg.new_password_long');
        } elseif ($new === $current) {
            $errors[] = msg('password.msg.same');
        } elseif ($new === (string) $user['phone']) {
            $errors[] = msg('msg.password_is_phone');
        } elseif ($new !== $confirm) {
            $errors[] = msg('msg.new_password_match');
        }
        if ($errors !== []) {
            $this->failPassword($errors);
        }

        if (!UserRepository::changePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT))) {
            $this->failPassword([msg('msg.password_save_failed')]);
        }
        Auth::login((int) $user['id']);
        Session::flash('password_changed', true);
        redirect('/profile');
    }

    /** @param list<string> $errors */
    private function failPassword(array $errors): never
    {
        Session::flash('password_errors', $errors);
        redirect('/account/password#form');
    }

    /** লগআউট শুধু POST + CSRF দিয়ে: অন্য সাইট থেকে লিংকের মাধ্যমে কাউকে বের করে দেওয়া যায় না। */
    public function logout(): void
    {
        if (Security::verifyCsrf($_POST['_token'] ?? null)) {
            Auth::logout();
        }
        redirect('/');
    }

    /** লগইনের পরের স্বাগত পেইজ (Figma 2663-981 পেশাজীবী, 2686-740 প্রতিনিধি)। */
    public function welcome(): void
    {
        $user = Auth::user();
        if ($user === null) {
            redirect('/login');
        }
        if (UserRepository::needsSetup($user)) {
            redirect('/account/setup');
        }
        if ((int) ($user['must_change_password'] ?? 0) === 1) {
            redirect('/account/password');
        }
        $role = (string) $user['role'];
        $key = 'welcome.' . $role . '.';

        View::render('pages/welcome', [
            'bodyClass' => 'page-welcome',
            'title' => content('welcome.banner') . ' — Jorurikaj.com',
            'heading' => content($key . 'banner'),
            'crumb' => content($key . 'crumb'),
            'role' => $role,
            'name' => honorific_name($user['gender'] ?? null, (string) $user['name']),
        ]);
    }

    /**
     * সংরক্ষিত ছবি (storage/uploads/photos) পাঠায়। নাম ৩২ অক্ষরের এলোমেলো হেক্স, তাই অনুমান করা যায় না;
     * পাথে অন্য কিছু ঢোকার সুযোগ নেই (শুধু a-f0-9)।
     *
     * @param array<string,mixed> $meta
     */
    public function photo(array $meta = []): void
    {
        $slug = (string) ($meta['slug'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $slug) === 1) {
            foreach (['jpg', 'png', 'webp'] as $ext) {
                $file = PhotoUpload::directory() . '/' . $slug . '.' . $ext;
                if (is_file($file)) {
                    header('Content-Type: ' . PhotoUpload::mimeOf($slug . '.' . $ext));
                    header('Content-Length: ' . (string) filesize($file));
                    header('Cache-Control: public, max-age=86400');
                    readfile($file);

                    return;
                }
            }
        }
        http_response_code(404);
        View::render('pages/error', ['title' => msg('msg.not_found'), 'code' => 404]);
    }

    /** @param array<string,mixed> $meta */
    private function role(array $meta): string
    {
        return ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';
    }

    /** @param list<string> $errors */
    private function fail(string $back, array $errors, string $id = ''): never
    {
        Session::flash('login_errors', $errors);
        Session::flash('login_phone', $id);
        redirect($back . '#form');
    }
}
