<?php

declare(strict_types=1);

namespace App\Core;

/**
 * গতি-সীমা: একই ঠিকানা (IP) থেকে অল্প সময়ে অনেক পেইজ চাওয়া বট/স্ক্র্যাপারকে সাময়িক আটকায়।
 * মানুষ এক বসায় বড়জোর ১৫-২০টি পেইজ দেখে, তাই সীমা তার কয়েক গুণ বেশি রাখা হয়েছে
 * (মোবাইল নেটওয়ার্কে অনেকে একই IP ব্যবহার করেন, তাই খুব কম রাখা হয়নি)।
 * গোনা হয় শুধু পেইজ (GET); ছবি/CSS/JS সার্ভার নিজেই দেয়, ফাইল-দেওয়া রুট ও লগইন-করা অ্যাডমিন গোনার বাইরে।
 * Google/Bing-এর মতো সার্চ-বট ছাড় পায়, যাতে সার্চে সাইট না হারায়।
 */
final class TrafficGuard
{
    /** মিনিটে সর্বোচ্চ পেইজ; পার হলে MINUTE_BLOCK সেকেন্ড আটকানো। */
    private const PER_MINUTE = 30;
    private const MINUTE_BLOCK = 900;

    /** ঘণ্টায় সর্বোচ্চ পেইজ; পার হলে HOUR_BLOCK সেকেন্ড আটকানো। */
    private const PER_HOUR = 150;
    private const HOUR_BLOCK = 3600;

    /** এই পথগুলো পেইজ নয় (ছবি, ডেটা, গোনার সংকেত), তাই গোনা হয় না। */
    private const SKIP_PREFIXES = ['/photo/', '/ad-image/', '/page-image/', '/site-theme', '/areas/', '/pv', '/robots.txt', '/sitemap.xml'];

    /** যে সার্চ-বটদের কখনও আটকানো হয় না। */
    private const GOOD_BOTS = '/googlebot|google-inspectiontool|bingbot|duckduckbot|applebot|yandex|baiduspider|facebookexternalhit|twitterbot|linkedinbot|whatsapp/i';

    /** প্রতিটি পেইজ-অনুরোধে চলে; সীমা পার হলে ৪২৯ বার্তা দিয়ে থামিয়ে দেয়। */
    public static function check(): void
    {
        if (PHP_SAPI === 'cli' || ($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !empty($_SESSION['admin_id'])) {
            return;
        }
        $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        foreach (self::SKIP_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }
        if (preg_match(self::GOOD_BOTS, (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')) === 1) {
            return;
        }

        if (RateLimiter::blocked('traffic_block_m', 1, self::MINUTE_BLOCK) || RateLimiter::blocked('traffic_block_h', 1, self::HOUR_BLOCK)) {
            self::deny();
        }

        RateLimiter::hit('traffic_m', 60);
        if (RateLimiter::count('traffic_m', 60) > self::PER_MINUTE) {
            RateLimiter::hit('traffic_block_m', self::MINUTE_BLOCK);
            self::logBlock('মিনিটে ' . self::PER_MINUTE . '-এর বেশি পেইজ');
            self::deny();
        }

        RateLimiter::hit('traffic_h', 3600);
        if (RateLimiter::count('traffic_h', 3600) > self::PER_HOUR) {
            RateLimiter::hit('traffic_block_h', self::HOUR_BLOCK);
            self::logBlock('ঘণ্টায় ' . self::PER_HOUR . '-এর বেশি পেইজ');
            self::deny();
        }
    }

    /** আটকানোর ঘটনাটা (একবার) লগে রাখা: ঠিকানার হ্যাশের শুরু ও ব্রাউজারের পরিচয়, IP নয়। */
    private static function logBlock(string $reason): void
    {
        Logger::error('TrafficGuard আটকেছে (' . $reason . ') ঠিকানা-হ্যাশ ' . substr(Security::ipHash(), 0, 10)
            . ' · ' . substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 120));
    }

    private static function deny(): never
    {
        http_response_code(429);
        header('Retry-After: 900');
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="bn"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>একটু অপেক্ষা করুন</title></head>'
            . '<body><p>' . htmlspecialchars(msg('msg.traffic') ?: 'অনেক বেশি অনুরোধ এসেছে। কিছুক্ষণ পরে আবার চেষ্টা করুন।', ENT_QUOTES, 'UTF-8') . '</p></body></html>';
        exit;
    }
}
