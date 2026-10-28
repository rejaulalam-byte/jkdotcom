<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Models\UserRepository;

/**
 * "পাসওয়ার্ড মনে নেই": ইমেইলে ৬ সংখ্যার কোড → কোড মিলিয়ে → নতুন পাসওয়ার্ড (লগইন পেইজের পপ-আপ, JSON)।
 * কোড ও ধাপের অবস্থা সার্ভারের সেশনে থাকে (ডাটাবেসে কিছু নয়): কোডটা শুধু ইমেইলের মালিকের কাছে যায়,
 * আর অনুমান ঠেকাতে কোডপ্রতি ৫টি চেষ্টা ও ১০ মিনিটের মেয়াদ।
 */
final class PasswordResetController
{
    private const CODE_TTL = 600;
    private const MAX_TRIES = 5;
    private const MIN_PASSWORD = 8;

    /** ধাপ ১: ইমেইল নিয়ে কোড পাঠায়। ইমেইল নিবন্ধিত কি না তা উত্তরে কখনও বোঝা যায় না (সবসময় একই উত্তর)। */
    public function request(): void
    {
        $this->guard('pwreset_request', 6);

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 150) {
            $this->reply(['error' => 'email', 'message' => msg('msg.email_bad_hint')], 422);
        }

        // একই ইমেইলে দুই পাশে অ্যাকাউন্ট থাকতে পারে; পপ-আপ যে পেইজ থেকে খুলেছে সেই পাশেরটার পাসওয়ার্ড বদলায়
        $role = ($_POST['role'] ?? '') === 'agent' ? 'agent' : 'worker';
        $user = UserRepository::findByEmail($email, $role);
        $uid = $user !== null && $user['status'] === 'approved' ? (int) $user['id'] : 0;
        $code = (string) random_int(100000, 999999);
        $salt = bin2hex(random_bytes(8));
        $_SESSION['pr'] = [
            'uid' => $uid,
            'salt' => $salt,
            'hash' => hash('sha256', $salt . $code),
            'exp' => time() + self::CODE_TTL,
            'tries' => 0,
        ];
        unset($_SESSION['pr_ok']);

        // একই ইমেইলে ঘণ্টায় ৩টির বেশি কোড নয়: অন্যের ইনবক্স ভরানো ঠেকায়
        if ($uid > 0 && !RateLimiter::tooMany('pwreset_mail_' . hash('sha256', $email), 3, 3600)) {
            $this->sendCode($email, $code);
        }
        $this->reply(['ok' => true]);
    }

    /** ধাপ ২: কোড মিলিয়ে দেখে; ঠিক হলে নতুন পাসওয়ার্ডের অনুমতি দেয়। */
    public function verify(): void
    {
        $this->guard('pwreset_verify', 30);

        $pr = $_SESSION['pr'] ?? null;
        if (!is_array($pr) || (int) $pr['exp'] < time()) {
            unset($_SESSION['pr']);
            $this->reply(['error' => 'expired', 'message' => msg('forgot.msg.expired')], 422);
        }
        if ((int) $pr['tries'] >= self::MAX_TRIES) {
            unset($_SESSION['pr']);
            $this->reply(['error' => 'tries', 'message' => msg('forgot.msg.too_many')], 429);
        }
        $_SESSION['pr']['tries'] = (int) $pr['tries'] + 1;

        $code = preg_replace('/\D/', '', (string) ($_POST['code'] ?? '')) ?? '';
        if ((int) $pr['uid'] === 0 || !hash_equals((string) $pr['hash'], hash('sha256', $pr['salt'] . $code))) {
            $this->reply(['error' => 'code', 'message' => msg('forgot.msg.wrong_code')], 422);
        }

        $_SESSION['pr_ok'] = ['uid' => (int) $pr['uid'], 'exp' => time() + self::CODE_TTL];
        unset($_SESSION['pr']);
        $this->reply(['ok' => true]);
    }

    /** ধাপ ৩: নতুন পাসওয়ার্ড বসায় (কোড ঠিক হওয়ার অনুমতি থাকলেই)। */
    public function reset(): void
    {
        $this->guard('pwreset_set', 20);

        $ok = $_SESSION['pr_ok'] ?? null;
        if (!is_array($ok) || (int) $ok['exp'] < time()) {
            unset($_SESSION['pr_ok']);
            $this->reply(['error' => 'expired', 'message' => msg('forgot.msg.timeout')], 422);
        }

        $new = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');
        $user = UserRepository::findById((int) $ok['uid']);
        // bcrypt ৭২ বাইটের পরের অংশ উপেক্ষা করে, তাই বেশি লম্বা পাসওয়ার্ড আটকানো হয়
        $error = match (true) {
            mb_strlen($new) < self::MIN_PASSWORD => msg('msg.password_short'),
            strlen($new) > 72 => msg('msg.password_long'),
            $user !== null && $new === (string) $user['phone'] => msg('msg.password_is_phone'),
            $new !== $confirm => msg('msg.password_match'),
            default => null,
        };
        if ($error !== null) {
            $this->reply(['error' => 'password', 'message' => $error], 422);
        }
        if ($user === null || !UserRepository::changePassword((int) $user['id'], password_hash($new, PASSWORD_DEFAULT))) {
            $this->reply(['error' => 'save', 'message' => msg('msg.password_save_failed')], 500);
        }

        unset($_SESSION['pr_ok']);
        $this->reply(['ok' => true]);
    }

    /** প্রতিটি ধাপের সাধারণ পাহারা: CSRF ও দর্শক-প্রতি ঘণ্টায় সীমা। */
    private function guard(string $bucket, int $perHour): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->reply(['error' => 'csrf', 'message' => msg('msg.csrf')], 403);
        }
        if (RateLimiter::tooMany($bucket, $perHour, 3600)) {
            $this->reply(['error' => 'limit', 'message' => msg('msg.rate')], 429);
        }
    }

    /** কোডসহ ইমেইল পাঠায়। ডাটাবেস না থাকলে (লোকাল ডেমো) মেইল না পাঠিয়ে storage/demo-mail.log-এ লেখে। */
    private function sendCode(string $to, string $code): void
    {
        $body = "আপনার পাসওয়ার্ড পুনরুদ্ধারের কোড: {$code}\n\n"
            . "কোডটি ১০ মিনিট পর্যন্ত কাজ করবে। কোডটি কাউকে জানাবেন না।\n"
            . "আপনি এটি চেয়ে না থাকলে এই ইমেইল উপেক্ষা করুন।\n\n— jorurikaj.com";
        if (Database::connection() === null) {
            @file_put_contents(BASE_PATH . '/storage/demo-mail.log', $to . ' | ' . $code . PHP_EOL, FILE_APPEND | LOCK_EX);

            return;
        }
        if (!Mailer::send($to, 'পাসওয়ার্ড পুনরুদ্ধারের কোড — jorurikaj.com', $body)) {
            Logger::error('Password reset e-mail could not be sent');
        }
    }

    /** @param array<string,mixed> $body */
    private function reply(array $body, int $status = 200): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
