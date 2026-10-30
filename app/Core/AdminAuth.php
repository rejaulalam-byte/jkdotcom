<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\AdminRepository;

/**
 * অ্যাডমিন প্যানেলের লগইন-অবস্থা, অনুমতি ও ইমেইলের ৬-সংখ্যার কোড।
 * সদস্যের (কর্মী/প্রতিনিধি) লগইন (Auth) থেকে সম্পূর্ণ আলাদা: সেশনে আলাদা কী, আলাদা নিয়ম।
 */
final class AdminAuth
{
    /** নিষ্ক্রিয় থাকলে এতক্ষণ পর (সেকেন্ড) আবার লগইন লাগে। */
    private const IDLE_SECONDS = 1800;
    /** ইমেইলের কোডের মেয়াদ (সেকেন্ড) ও প্রতি কোডে সর্বোচ্চ ভুল চেষ্টা। */
    private const CODE_TTL = 600;
    private const CODE_TRIES = 5;

    /** @var array<string,mixed>|null|false false = এখনও খোঁজা হয়নি */
    private static array|null|false $admin = false;

    /**
     * এখন লগইন-করা অ্যাডমিন; লগইন না থাকলে, অ্যাকাউন্ট বন্ধ হলে বা ৩০ মিনিট নিষ্ক্রিয় থাকলে null।
     *
     * @return array<string,mixed>|null
     */
    public static function current(): ?array
    {
        if (self::$admin !== false) {
            return self::$admin;
        }
        $id = (int) ($_SESSION['admin_id'] ?? 0);
        $row = $id > 0 ? AdminRepository::findById($id) : null;
        if ($row !== null && ($row['status'] !== 'active' || time() - (int) ($_SESSION['admin_seen'] ?? 0) > self::IDLE_SECONDS)) {
            $row = null;
        }
        if ($row === null) {
            unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
        } else {
            $_SESSION['admin_seen'] = time();
        }

        return self::$admin = $row;
    }

    /** সফল লগইনের পর: সেশন-id বদলে অ্যাডমিনকে লগইন-করা রাখে এবং লগে লেখে। @param array<string,mixed> $admin */
    public static function login(array $admin): void
    {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int) $admin['id'];
        $_SESSION['admin_seen'] = time();
        self::$admin = false;
        AdminRepository::update((int) $admin['id'], ['last_login_at' => date('Y-m-d H:i:s')]);
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'login');
    }

    public static function logout(): void
    {
        $admin = self::current();
        if ($admin !== null) {
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'logout');
        }
        unset($_SESSION['admin_id'], $_SESSION['admin_seen']);
        session_regenerate_id(true);
        self::$admin = false;
    }

    /**
     * প্যানেলের পেইজের পাহারা: লগইন না থাকলে লগইনে পাঠায়; $permission বা $superOnly না মিললে "অনুমতি নেই" দেখায়।
     *
     * @return array<string,mixed>
     */
    public static function require(?string $permission = null, bool $superOnly = false): array
    {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex, nofollow');
        $admin = self::current();
        if ($admin === null) {
            redirect(admin_base() . '/login');
        }
        if (($superOnly && !self::isSuper($admin)) || ($permission !== null && !self::can($admin, $permission))) {
            http_response_code(403);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'অনুমতি নেই'], 'layouts/admin');
            exit;
        }

        return $admin;
    }

    /** @param array<string,mixed> $admin */
    public static function isSuper(array $admin): bool
    {
        return $admin['role'] === 'super';
    }

    /** @param array<string,mixed> $admin */
    public static function isMain(array $admin): bool
    {
        return $admin['role'] === 'main';
    }

    /** সুপার বা মেইন অ্যাডমিন: সব দৈনন্দিন কাজ ও অ্যাকাউন্ট তৈরি (কাঠামোর কাজ শুধু সুপার)। @param array<string,mixed> $admin */
    public static function isTop(array $admin): bool
    {
        return self::isSuper($admin) || self::isMain($admin);
    }

    /** সুপার ও মেইন অ্যাডমিনের সব অনুমতি; সাব-অ্যাডমিনের শুধু তালিকায় দেওয়াগুলো। @param array<string,mixed> $admin */
    public static function can(array $admin, string $permission): bool
    {
        return self::isTop($admin) || in_array($permission, (array) ($admin['permission_list'] ?? []), true);
    }

    /** ভূমিকার বাংলা নাম। */
    public static function roleLabel(string $role): string
    {
        return match ($role) {
            'super' => 'সুপার অ্যাডমিন',
            'main' => 'মেইন অ্যাডমিন (ওনার)',
            default => 'সাব-অ্যাডমিন',
        };
    }

    /**
     * অ্যাকাউন্টের পেইজের পাহারা: শুধু সুপার ও মেইন অ্যাডমিন; অন্যরা "অনুমতি নেই" দেখেন।
     *
     * @return array<string,mixed>
     */
    public static function requireTop(): array
    {
        $admin = self::require();
        if (!self::isTop($admin)) {
            http_response_code(403);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'অনুমতি নেই'], 'layouts/admin');
            exit;
        }

        return $admin;
    }

    // ---- ইমেইলের কোড ---------------------------------------------------------------------------

    /**
     * ৬ সংখ্যার কোড বানিয়ে ইমেইল করে; সেশনে শুধু কোডের হ্যাশ ও $payload থাকে। সফল হলে true।
     * $purpose আলাদা কাজের কোড আলাদা রাখে ('login', 'email-change')।
     *
     * @param array<string,mixed> $payload কোড ঠিক হলে ফেরত দেওয়া তথ্য (যেমন অ্যাডমিনের id)
     */
    public static function issueCode(string $purpose, string $to, array $payload): bool
    {
        $code = (string) random_int(100000, 999999);
        $salt = bin2hex(random_bytes(8));
        $_SESSION['admin_code'][$purpose] = [
            'salt' => $salt, 'hash' => hash('sha256', $salt . $code), 'exp' => time() + self::CODE_TTL, 'tries' => 0, 'payload' => $payload,
        ];

        return self::mail($to, 'অ্যাডমিন প্যানেলের কোড — jorurikaj.com', "আপনার যাচাইয়ের কোড: {$code}\n\nকোডটি ১০ মিনিট কাজ করবে। কাউকে জানাবেন না।\nআপনি এটি চেয়ে না থাকলে এই ইমেইল উপেক্ষা করুন এবং পাসওয়ার্ড বদলে ফেলুন।\n\n— jorurikaj.com");
    }

    /** এই কাজের জন্য কোড চাওয়া হয়েছে এবং মেয়াদ আছে কি না। */
    public static function codePending(string $purpose): bool
    {
        $c = $_SESSION['admin_code'][$purpose] ?? null;

        return is_array($c) && (int) $c['exp'] > time() && (int) $c['tries'] < self::CODE_TRIES;
    }

    /**
     * কোড মেলায়। ঠিক হলে ['ok' => true, 'payload' => ...] (কোড আর চলে না); নইলে ['ok' => false, 'message' => ...]।
     *
     * @return array{ok:bool,message?:string,payload?:array<string,mixed>}
     */
    public static function checkCode(string $purpose, string $input): array
    {
        $c = $_SESSION['admin_code'][$purpose] ?? null;
        if (!is_array($c) || (int) $c['exp'] < time()) {
            unset($_SESSION['admin_code'][$purpose]);

            return ['ok' => false, 'message' => 'কোডের মেয়াদ শেষ হয়ে গেছে। আবার শুরু করুন।'];
        }
        if ((int) $c['tries'] >= self::CODE_TRIES) {
            unset($_SESSION['admin_code'][$purpose]);

            return ['ok' => false, 'message' => 'অনেকবার ভুল কোড দেওয়া হয়েছে। আবার শুরু করুন।'];
        }
        $_SESSION['admin_code'][$purpose]['tries'] = (int) $c['tries'] + 1;
        $digits = preg_replace('/\D/', '', strtr($input, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9'])) ?? '';
        if (!hash_equals((string) $c['hash'], hash('sha256', $c['salt'] . $digits))) {
            return ['ok' => false, 'message' => 'কোডটি সঠিক নয়। ইমেইলে পাঠানো ৬ সংখ্যার কোডটি আবার লিখুন।'];
        }
        unset($_SESSION['admin_code'][$purpose]);

        return ['ok' => true, 'payload' => (array) $c['payload']];
    }

    // ---- ইমেইল ---------------------------------------------------------------------------------

    /** ইমেইল পাঠায়। ডাটাবেস না থাকলে (লোকাল প্রিভিউ) পাঠানোর বদলে storage/demo-mail.log-এ লেখে। */
    public static function mail(string $to, string $subject, string $body): bool
    {
        if (Database::connection() === null) {
            @file_put_contents(BASE_PATH . '/storage/demo-mail.log', $to . ' | ' . $subject . ' | ' . str_replace("\n", ' / ', $body) . PHP_EOL, FILE_APPEND | LOCK_EX);

            return true;
        }

        return Mailer::send($to, $subject, $body);
    }

    /** ইমেইলের লিংকের ভিত্তি-ঠিকানা (.env-এর APP_URL; নেই হলে লোকাল)। */
    public static function baseUrl(): string
    {
        return rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? 'http://localhost:8000', '/');
    }

    /** নতুন এককালীন লিংকের টোকেন বানিয়ে অ্যাডমিনের ঘরে হ্যাশ রাখে; আসল টোকেন ফেরত দেয় (শুধু ইমেইলে যায়)। */
    public static function newToken(int $adminId, int $validSeconds): string
    {
        $token = bin2hex(random_bytes(32));
        AdminRepository::update($adminId, [
            'token_hash' => hash('sha256', $token),
            'token_expires' => date('Y-m-d H:i:s', time() + $validSeconds),
        ]);

        return $token;
    }

    /** পাসওয়ার্ডের নিয়ম: ১০-৭২ বাইট, ইমেইলের সমান নয়, দুবার একই। ঠিক থাকলে null, নইলে বাংলা বার্তা। */
    public static function passwordError(string $new, string $confirm, string $email): ?string
    {
        if (mb_strlen($new) < 10) {
            return 'পাসওয়ার্ড অবশ্যই ১০ অক্ষর বা তার বেশি হতে হবে।';
        }
        if (strlen($new) > 72) {
            return 'পাসওয়ার্ড সর্বোচ্চ ৭২ বাইট (প্রায় ৭০ ইংরেজি অক্ষর) হতে পারে।';
        }
        if (mb_strtolower($new) === mb_strtolower($email)) {
            return 'ইমেইলকে পাসওয়ার্ড হিসেবে ব্যবহার করবেন না।';
        }
        if ($new !== $confirm) {
            return 'দুইবার লেখা পাসওয়ার্ড একই নয়।';
        }

        return null;
    }
}
