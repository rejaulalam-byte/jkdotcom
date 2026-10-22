<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\PhotoUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\UserRepository;

/**
 * লগইন (পেশাজীবী /login, প্রতিনিধি /login/agent), লগআউট, স্বাগত পেইজ (/welcome)
 * এবং সদস্যের ছবি দেখানো (/photo/<নাম>)।
 */
final class AuthController
{
    /** আসল ব্যবহারকারী না মিললেও একই সময় লাগানোর জন্য নকল হ্যাশ: ফলে মোবাইল-নাম্বার আছে কি না সময় মেপে জানা যায় না। */
    private const DUMMY_HASH = '$2y$10$jAX9q737WKgze0Ox2Hg28OtOoPWtS0.WFdEf2wUrFQMl73taY7U3e';

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function loginForm(array $meta = []): void
    {
        $role = $this->role($meta);
        if (Auth::user() !== null) {
            redirect('/welcome');
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
            $this->fail($back, ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
        }
        // লুকানো "website" ঘর: স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }
        if (RateLimiter::tooMany('login', 10, 900)) {
            $this->fail($back, ['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে ১৫ মিনিট পরে আবার চেষ্টা করুন।']);
        }

        // লগইন আইডি = ইমেইল অথবা রেজিস্টার্ড মোবাইল নাম্বার (@ থাকলে ইমেইল)
        $id = trim((string) ($_POST['login_id'] ?? ''));
        $byEmail = str_contains($id, '@');
        $id = $byEmail ? mb_strtolower(mb_substr($id, 0, 150)) : normalize_phone($id);
        $password = (string) ($_POST['password'] ?? '');
        $validId = $byEmail ? filter_var($id, FILTER_VALIDATE_EMAIL) !== false : preg_match('/^01[3-9][0-9]{8}$/', $id) === 1;
        if (!$validId || $password === '' || strlen($password) > 200) {
            $this->fail($back, ['লগইন আইডি (ইমেইল বা রেজিস্টার্ড মোবাইল নাম্বার) ও পাসওয়ার্ড দিন।'], $id);
        }
        // একই আইডিতে বারবার অনুমান ঠেকাতে আইডি-ভিত্তিক সীমা
        if (RateLimiter::tooMany('login_' . $id, 5, 900)) {
            $this->fail($back, ['এই আইডিতে অনেকবার ভুল চেষ্টা হয়েছে। অনুগ্রহ করে ১৫ মিনিট পরে আবার চেষ্টা করুন।'], $id);
        }

        $user = $byEmail ? UserRepository::findByEmail($id) : UserRepository::findByPhone($id);
        $ok = password_verify($password, $user !== null ? (string) $user['password_hash'] : self::DUMMY_HASH);
        if ($user === null || !$ok) {
            $this->fail($back, ['লগইন আইডি বা পাসওয়ার্ড সঠিক নয়।'], $id);
        }
        // পাসওয়ার্ড ঠিক আছে: এবার অ্যাকাউন্টের অবস্থা বলা নিরাপদ
        if ($user['role'] !== $role) {
            $this->fail($back, [$role === 'agent'
                ? 'এই অ্যাকাউন্টটি পেশাজীবী কর্মীর। পেশাজীবীদের লগইন পেইজ ব্যবহার করুন।'
                : 'এই অ্যাকাউন্টটি প্রতিনিধির। প্রতিনিধিদের লগইন পেইজ ব্যবহার করুন।'], $id);
        }
        if ($user['status'] === 'pending') {
            $this->fail($back, ['আপনার আবেদনটি এখনও অনুমোদনের অপেক্ষায় আছে। অনুমোদন হলে আপনি লগইন করতে পারবেন।'], $id);
        }
        if ($user['status'] !== 'approved') {
            $this->fail($back, ['আপনার অ্যাকাউন্টটি এখন সক্রিয় নেই। অনুগ্রহ করে হেল্পলাইনে যোগাযোগ করুন।'], $id);
        }

        $rehash = password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT) ? password_hash($password, PASSWORD_DEFAULT) : null;
        UserRepository::touchLogin((int) $user['id'], $rehash);
        Auth::login((int) $user['id']);
        redirect('/welcome');
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
        $role = (string) $user['role'];
        $key = 'welcome.' . $role . '.';

        View::render('pages/welcome', [
            'bodyClass' => 'page-welcome',
            'title' => content('welcome.banner') . ' — Jorurikaj.com',
            'heading' => content($key . 'banner'),
            'crumb' => content($key . 'crumb'),
            'role' => $role,
            'name' => honorific_name($user['gender'] ?? null, (string) $user['name']),
            'token' => Security::csrfToken(),
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
        View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
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
