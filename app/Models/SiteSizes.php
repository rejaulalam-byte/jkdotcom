<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের লেখার আকার (শুধু সুপার অ্যাডমিন, <admin>/theme): ছয়টি দলের প্রতিটিতে একটা শতাংশ, নকশার মাপের ওপর গুণ হয়।
 * app.css-এর প্রতিটি font-size একটা গুণকে বাঁধা (--k-body, --k-head, …; শুরুর মান ১); বদলালে /site-theme সেগুলো বদলে দেয়।
 * শতাংশ বলে প্রতিটি পেইজের নিজের মাপের অনুপাত ঠিক থাকে; ফোন ও ডেস্কটপ দুটোতেই একই অনুপাতে বদলায়।
 * কী: "theme.size.<দল>" = শতাংশ (যেমন 110)।
 */
final class SiteSizes
{
    /** দল => [অ্যাডমিনে নাম, কোথায় লাগে]। */
    public const GROUPS = [
        'body' => ['সাধারণ ও ছোট লেখা', 'পেইজের সাধারণ লেখা, ফর্মের ঘর, তালিকা, কার্ডের লেখা, ছোট শিরোনাম।'],
        'head' => ['বড় শিরোনাম', 'হিরোর বড় লেখা, সেকশনের বড় শিরোনাম, পেইজের ব্যানারের শিরোনাম।'],
        'pill' => ['সেকশনের ছোট শিরোনাম (ক্যাপসুল)', 'লোগোসহ ছোট ক্যাপসুল-শিরোনাম।'],
        'nav' => ['প্রধান মেনু', 'ওপরের মেনুর লেখা।'],
        'btn' => ['বাটন', 'সাধারণ বাটনের লেখা।'],
        'foot' => ['ফুটার', 'নিচের ফুটারের লেখা।'],
    ];

    /** বাছা যায় এমন শতাংশ (খুব ছোট বা বড় হলে লেখা লাইন ভেঙে নকশা নষ্ট করে, তাই সীমা)। */
    public const STEPS = [80, 85, 90, 95, 100, 105, 110, 115, 120, 125, 130];

    private const PREFIX = 'theme.size.';

    public static function percent(string $group): int
    {
        $v = (int) Content::get(self::PREFIX . $group);

        return in_array($v, self::STEPS, true) ? $v : 100;
    }

    public static function anyChanged(): bool
    {
        foreach (array_keys(self::GROUPS) as $group) {
            if (self::percent($group) !== 100) {
                return true;
            }
        }

        return false;
    }

    /** একটা দলের শতাংশ রাখে; ১০০ হলে কী মুছে ফেলে। */
    public static function set(string $group, int $percent): bool
    {
        if (!isset(self::GROUPS[$group]) || !in_array($percent, self::STEPS, true)) {
            return false;
        }

        return $percent === 100 ? Content::forget(self::PREFIX . $group) : Content::set(self::PREFIX . $group, (string) $percent);
    }

    /** /site-theme-এর আকারের অংশ: শুধু বদলানো দলের গুণক। কিছু বদলানো না থাকলে খালি। */
    public static function css(): string
    {
        $vars = [];
        foreach (array_keys(self::GROUPS) as $group) {
            $p = self::percent($group);
            if ($p !== 100) {
                $vars[] = '--k-' . $group . ': ' . rtrim(rtrim(number_format($p / 100, 2, '.', ''), '0'), '.');
            }
        }

        return $vars === [] ? '' : ":root {\n    " . implode(";\n    ", $vars) . ";\n}\n";
    }
}
