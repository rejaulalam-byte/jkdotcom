<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের থিমের মূল রং (শুধু সুপার অ্যাডমিন, <admin>/theme)। app.css-এর :root-এ শুরুর মান; অ্যাডমিন কোনোটা বদলালে
 * /site-theme (ThemeController) সেগুলো নতুন মানে বসিয়ে দেয়, app.css-এর পরে লোড হয় বলে সেটাই চলে।
 * একটা রং থেকে তার সঙ্গী মানও বানানো হয় (যেমন টিলের কমা-রূপ --teal-rgb, যা দিয়ে কার্ডের হালকা টিল; আর একটু গাঢ় --teal-dark)।
 * কী: "theme.color.<নাম>" = #rrggbb।
 */
final class SiteTheme
{
    /**
     * নাম => [অ্যাডমিনে নাম, শুরুর রং, কোথায় লাগে]।
     *
     * @var array<string,array{0:string,1:string,2:string}>
     */
    public const COLORS = [
        'teal' => ['প্রধান রং (গাঢ় টিল)', '#125a6c', 'ওপরের পট্টি, পেইজের ব্যানার, হিরো, ফুটার, টিল বাটন ও বাক্স; কার্ডের হালকা রং এর থেকেই স্বচ্ছ রূপে আসে।'],
        'amber' => ['বিশেষ চিহ্নের রং (কমলা)', '#d08a00', 'মেনুতে মাউস রাখলে ও বাছা মেনুর লেখা, কিবোর্ডে ঘরে গেলে তার চারপাশের দাগ, কিছু ছোট চিহ্ন।'],
        'gold' => ['গাঢ় পটভূমির উজ্জ্বল লেখা (সোনালি)', '#f0d772', 'টিল পটভূমিতে সাইটের নাম, পথ-নির্দেশ, কিছু শিরোনাম ও বাটন।'],
        'ink' => ['মূল লেখার রং', '#222222', 'সাদা পটভূমির সাধারণ লেখা ও অনেক শিরোনাম।'],
    ];

    private const PREFIX = 'theme.color.';

    /** বর্তমান রং (#rrggbb, ছোট হাতের)। */
    public static function color(string $name): string
    {
        $v = strtolower(Content::get(self::PREFIX . $name));

        return self::valid($v) ? $v : self::COLORS[$name][1];
    }

    public static function isChanged(string $name): bool
    {
        return self::color($name) !== self::COLORS[$name][1];
    }

    public static function anyChanged(): bool
    {
        foreach (array_keys(self::COLORS) as $name) {
            if (self::isChanged($name)) {
                return true;
            }
        }

        return false;
    }

    public static function valid(string $hex): bool
    {
        return preg_match('/^#[0-9a-f]{6}$/', $hex) === 1;
    }

    /** একটা রং রাখে; শুরুর রং হলে কী মুছে ফেলে। */
    public static function set(string $name, string $hex): bool
    {
        $hex = strtolower($hex);
        if (!isset(self::COLORS[$name]) || !self::valid($hex)) {
            return false;
        }

        return $hex === self::COLORS[$name][1] ? Content::forget(self::PREFIX . $name) : Content::set(self::PREFIX . $name, $hex);
    }

    /** /site-theme-এর লেখা: শুধু বদলানো রং ও তাদের সঙ্গী মান। কিছু বদলানো না থাকলে খালি। */
    public static function css(): string
    {
        $vars = [];
        foreach (array_keys(self::COLORS) as $name) {
            if (!self::isChanged($name)) {
                continue;
            }
            $hex = self::color($name);
            $vars[] = '--' . $name . ': ' . $hex;
            if ($name === 'teal' || $name === 'ink') {
                $vars[] = '--' . $name . '-rgb: ' . implode(', ', self::rgb($hex));
            }
            if ($name === 'teal') {
                $vars[] = '--teal-dark: ' . self::darker($hex, 0.8);
            }
        }

        return $vars === [] ? '' : ":root {\n    " . implode(";\n    ", $vars) . ";\n}\n";
    }

    /** লিংকের সংস্করণ (রং বদলালে ঠিকানা বদলায়, তাই ব্রাউজার পুরনো ফাইল রাখে না)। */
    public static function version(): string
    {
        return substr(hash('sha256', self::css()), 0, 12);
    }

    /**
     * দুই রঙের পড়ার সুবিধা (WCAG contrast ratio, ১ থেকে ২১)। সাধারণ লেখার জন্য অন্তত ৪.৫ ভালো, বড় লেখায় ৩।
     */
    public static function contrast(string $a, string $b): float
    {
        $la = self::luminance($a);
        $lb = self::luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    /**
     * সতর্কতা (লেখা পড়া কঠিন হতে পারে এমন জোড়া); খালি তালিকা = সব ঠিক।
     *
     * @param array<string,string> $colors নাম => #rrggbb
     * @return list<string>
     */
    public static function warnings(array $colors): array
    {
        $out = [];
        $checks = [
            ['teal', '#ffffff', 4.5, 'প্রধান রঙের ওপর সাদা লেখা (ব্যানার, ফুটার) পড়া কঠিন হবে; প্রধান রং আরও গাঢ় করুন।'],
            ['gold', $colors['teal'], 3.0, 'প্রধান রঙের ওপর সোনালি লেখা স্পষ্ট দেখাবে না; সোনালি আরও উজ্জ্বল বা প্রধান রং আরও গাঢ় করুন।'],
            ['ink', '#ffffff', 4.5, 'সাদা পটভূমিতে মূল লেখা হালকা দেখাবে; লেখার রং আরও গাঢ় করুন।'],
            ['amber', '#ffffff', 2.5, 'সাদা মেনুতে কমলা লেখা (মাউস রাখলে) স্পষ্ট দেখাবে না; রং আরও গাঢ় করুন।'],
        ];
        foreach ($checks as [$name, $against, $min, $message]) {
            if (self::contrast($colors[$name], $against) < $min) {
                $out[] = $message;
            }
        }

        return $out;
    }

    /** @return array{0:int,1:int,2:int} */
    private static function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }

    private static function darker(string $hex, float $factor): string
    {
        return '#' . implode('', array_map(static fn (int $c): string => str_pad(dechex((int) round($c * $factor)), 2, '0', STR_PAD_LEFT), self::rgb($hex)));
    }

    private static function luminance(string $hex): float
    {
        $lin = static function (int $c): float {
            $v = $c / 255;

            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        };
        [$r, $g, $b] = self::rgb($hex);

        return 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
    }
}
