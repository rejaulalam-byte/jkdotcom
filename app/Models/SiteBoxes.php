<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সেকশনের ছোট হেডিংয়ের বক্সের (ক্যাপসুল, .pill) পেছনের রং। প্রতিটি বক্সের একটা নাম (slot) আর সাইটের সঙ্গে আসা রং।
 * সুপার অ্যাডমিন "লাইভ পেইজ এডিট" থেকে নিচের তালিকার যেকোনো রং বাছতে পারেন। রং CSS ক্লাস দিয়ে
 * (app.css-এর .pill--*), কারণ CSP-তে style="" চলে না; প্রতিটি রঙের সঙ্গে পড়া যায় এমন লেখার রংও ঠিক করা আছে।
 * অবস্থা থাকে লেখার টেবিলে (Content) "box.<slot>" নামে: না থাকলে শুরুর রং।
 */
final class SiteBoxes
{
    /** রঙের নাম => [অ্যাডমিনকে দেখানো নাম, CSS ক্লাস] */
    public const COLORS = [
        'olive'    => ['হালকা জলপাই', 'pill--olive'],
        'amber'    => ['স্বচ্ছ সোনালি', 'pill--amber'],
        'social'   => ['গাঢ় স্বচ্ছ সোনালি', 'pill--social'],
        'tealsoft' => ['হালকা টিল', 'pill--tealsoft'],
        'teal'     => ['গাঢ় টিল', 'pill--teal'],
        'gold'     => ['সোনালি', 'pill--gold'],
        'orange'   => ['কমলা', 'pill--orange'],
        'maroon'   => ['মেরুন', 'pill--maroon'],
        'white'    => ['সাদা', 'pill--white'],
        'none'     => ['রং ছাড়া (স্বচ্ছ)', 'pill--none'],
    ];

    /** slot => [সাইটের সঙ্গে আসা রঙের নাম, অ্যাডমিনকে দেখানো নাম] */
    public const SLOTS = [
        'home.hero'     => ['amber', 'হোম: হিরোর ছোট হেডিং'],
        'home.services' => ['olive', 'হোম: সেবা সেকশনের ছোট হেডিং'],
        'home.about'    => ['olive', 'হোম: পরিচয় সেকশনের ছোট হেডিং'],
        'home.steps'    => ['olive', 'হোম: ধাপ সেকশনের ছোট হেডিং'],
        'home.faq'      => ['olive', 'হোম: প্রশ্ন-উত্তর সেকশনের ছোট হেডিং'],
        'reg.join'      => ['olive', 'রেজিস্ট্রেশন নির্দেশিকা: নিচের সেকশনের ছোট হেডিং'],
        'footer.social' => ['social', 'ফুটার: "সামাজিক যোগাযোগ মাধ্যম"'],
    ];

    public static function exists(string $slot): bool
    {
        return isset(self::SLOTS[$slot]);
    }

    /** এখনকার রঙের নাম (অ্যাডমিনের বাছা, নইলে শুরুর)। */
    public static function color(string $slot): string
    {
        $value = Content::get('box.' . $slot);

        return isset(self::COLORS[$value]) ? $value : self::SLOTS[$slot][0];
    }

    public static function original(string $slot): string
    {
        return self::SLOTS[$slot][0];
    }

    public static function label(string $slot): string
    {
        return self::SLOTS[$slot][1];
    }

    public static function cssClass(string $slot): string
    {
        return self::COLORS[self::color($slot)][1];
    }
}
