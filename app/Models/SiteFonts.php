<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের বাংলা ফন্ট (শুধু সুপার অ্যাডমিন, <admin>/theme)। শুরুর ফন্ট Hind Siliguri; তার কাছাকাছি চেহারার তিনটি খোলা
 * লাইসেন্সের ফন্ট থেকে একটা বাছা যায় (ফাইল ও লাইসেন্স public/assets/fonts-এ)। ইংরেজি লেখা সবসময় Poppins।
 * বাছলে /site-theme --font বদলে দেয়: বাছা ফন্ট Hind Siliguri-র আগে বসে, তাই কোনো অক্ষর না থাকলে Hind Siliguri আঁকে।
 * কী: "theme.font" = নাম ('' = শুরুর ফন্ট)।
 */
final class SiteFonts
{
    /** নাম => [অ্যাডমিনে নাম, CSS-এর ফন্ট-পরিবার, ফাইল (fonts ফোল্ডারে), চেহারার ছোট বর্ণনা]। */
    public const FONTS = [
        '' => ['Hind Siliguri (শুরুর ফন্ট)', 'Hind Siliguri', 'hind-siliguri-400.woff2', 'সাইটের নকশার ফন্ট।'],
        'noto' => ['Noto Sans Bengali', 'Noto Sans Bengali', 'noto-sans-bengali.woff2', 'সবচেয়ে কাছাকাছি; পরিষ্কার, সংখ্যা ও যুক্তাক্ষর স্পষ্ট।'],
        'anek' => ['Anek Bangla', 'Anek Bangla', 'anek-bangla.woff2', 'আধুনিক, একটু চওড়া ও পরিষ্কার।'],
        'baloo' => ['Baloo Da 2', 'Baloo Da 2', 'baloo-da-2.woff2', 'একটু গোলাকার, বন্ধুসুলভ।'],
    ];

    private const KEY = 'theme.font';

    /** app.css-এর --font-এর বাকি অংশ (বাছা ফন্টের পরে)। */
    private const REST = '"Hind Siliguri", "Noto Sans Bengali", "Nirmala UI", "Siyam Rupali", system-ui, sans-serif';

    public static function current(): string
    {
        $v = Content::get(self::KEY);

        return $v !== '' && isset(self::FONTS[$v]) ? $v : '';
    }

    public static function set(string $name): bool
    {
        if (!isset(self::FONTS[$name])) {
            return false;
        }

        return $name === '' ? Content::forget(self::KEY) : Content::set(self::KEY, $name);
    }

    /** আগে থেকে নামানোর (preload) ফাইল: বাছা ফন্টের, নইলে শুরুর ফন্টের। */
    public static function preloadFile(): string
    {
        return self::FONTS[self::current()][2];
    }

    /** /site-theme-এর ফন্টের অংশ; শুরুর ফন্ট হলে খালি। */
    public static function css(): string
    {
        $name = self::current();
        if ($name === '') {
            return '';
        }

        return ":root {\n    --font: \"Bangla Digits\", \"Poppins\", \"" . self::FONTS[$name][1] . '", ' . self::REST . ";\n}\n";
    }
}
