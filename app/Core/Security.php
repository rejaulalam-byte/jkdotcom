<?php

declare(strict_types=1);

namespace App\Core;

/** নিরাপত্তা-হেডার, CSRF সুরক্ষা ও ছোট ছোট নিরাপত্তা-সহায়ক। */
final class Security
{
    public static function isHttps(): bool
    {
        return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public static function sendHeaders(): void
    {
        if (headers_sent()) {
            return;
        }
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Cross-Origin-Opener-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        // সবকিছু (স্ক্রিপ্ট, স্টাইল, ফন্ট, ছবি) আমাদের নিজের ডোমেইন থেকেই আসতে হবে।
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; "
            . "img-src 'self' data:; font-src 'self'; connect-src 'self'; object-src 'none'; "
            . "base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return is_string($token)
            && !empty($_SESSION['_csrf'])
            && hash_equals($_SESSION['_csrf'], $token);
    }

    /** দর্শকের ঠিকানা। শুধু REMOTE_ADDR বিশ্বাস করা হয় (X-Forwarded-For-এর মতো হেডার জাল করা যায়)। */
    public static function clientIp(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    /** আইপির একমুখী হ্যাশ: ঠিকানা না রেখেই সীমা-নিয়ন্ত্রণ ও লগ করা যায়। */
    public static function ipHash(): string
    {
        return hash_hmac('sha256', self::clientIp(), Env::get('APP_KEY', 'change-me') ?? 'change-me');
    }

    /** URL শুধু সাধারণ http/https লিংক হলে ফেরত দেয়; নইলে null। */
    public static function safeUrl(?string $url): ?string
    {
        if ($url === null) {
            return null;
        }
        $url = trim($url);
        if ($url === '' || strlen($url) > 500 || preg_match('/[\x00-\x20<>"\'\\\\]/', $url)) {
            return null;
        }
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])
            || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $url;
    }
}
