<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\RichText;

/**
 * "খোলা পেইজ": যেসব পেইজের মূল লেখা একটাই রিচ-টেক্সট (আমাদের সম্পর্কে, নীতিমালা, শর্তাবলি, রেজিস্ট্রেশনের নিয়ম…)।
 * শুরুর লেখা app/Content/pages/<slug>.html-এ; অ্যাডমিন এডিটরে বদলালে content_blocks-এর "page.<slug>.html" প্রাধান্য পায়।
 * দেখানোর সময় সবসময় RichText দিয়ে ছেঁকে নেওয়া হয়।
 */
final class OpenPages
{
    /** @var array<string,array{title:string,url:string}> slug => অ্যাডমিন-তালিকার নাম ও পাবলিক ঠিকানা */
    public const ALL = [
        'about' => ['title' => 'আমাদের সম্পর্কে', 'url' => '/about'],
        'privacy' => ['title' => 'গোপনীয়তা নীতিমালা', 'url' => '/privacy'],
        'terms' => ['title' => 'ব্যবহারের শর্তাবলি', 'url' => '/terms'],
        'registration-rules' => ['title' => 'রেজিস্ট্রেশন সম্পর্কিত তথ্যাবলী', 'url' => '/registration'],
        'registration-professional' => ['title' => 'পেশাজীবীদের রেজিস্ট্রেশন নির্দেশিকা', 'url' => '/registration/professional'],
        'registration-agent' => ['title' => 'প্রতিনিধি রেজিস্ট্রেশন নির্দেশিকা', 'url' => '/registration/agent'],
    ];

    /** সাইটের নিজের খোলা পেইজ, অথবা অ্যাডমিনের বানানো নতুন পেইজ ("custom-<slug>", App\Models\CustomPages)। */
    public static function exists(string $slug): bool
    {
        return isset(self::ALL[$slug]) || CustomPages::slugFromOpen($slug) !== null;
    }

    /** সংরক্ষিত (বা শুরুর) লেখা, ছাঁকনির আগে — এডিটরে খোলার জন্য। */
    public static function raw(string $slug): string
    {
        return Content::get('page.' . $slug . '.html', self::original($slug));
    }

    /** পাবলিক পেইজে দেখানোর নিরাপদ HTML। */
    public static function html(string $slug): string
    {
        // লাইভ পেইজ এডিটের ফ্রেমে: ঘরটা চেনার লুকানো চিহ্ন (ক্লিক করলে পূর্ণ এডিটর খোলে)
        $mark = live_preview() ? '<span data-jk-rich="' . e($slug) . '" hidden></span>' : '';

        return $mark . RichText::show(self::raw($slug));
    }

    /** সাইটের সঙ্গে আসা শুরুর লেখা। */
    public static function original(string $slug): string
    {
        if (CustomPages::slugFromOpen($slug) !== null) {
            return CustomPages::STARTER;
        }
        $file = BASE_PATH . '/app/Content/pages/' . $slug . '.html';

        return isset(self::ALL[$slug]) && is_file($file) ? (string) file_get_contents($file) : '';
    }

    /** অ্যাডমিন কি লেখাটা বদলেছেন (শুরুর লেখা নয়)? */
    public static function isChanged(string $slug): bool
    {
        return Content::has('page.' . $slug . '.html');
    }
}
