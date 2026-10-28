<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সেকশনের ছোট হেডিংয়ের (ক্যাপসুলের) পাশের ছোট আইকন। প্রতিটির একটা নাম (slot) আর সাইটের সঙ্গে আসা ছবি।
 * অ্যাডমিন "লাইভ পেইজ এডিট" থেকে নতুন ছবি দিতে, শুরুর ছবিতে ফিরতে, লুকাতে বা আবার দেখাতে পারেন।
 * অবস্থা থাকে লেখার টেবিলে (Content) "icon.<slot>" নামে: না থাকলে শুরুর ছবি, "hide" = লুকানো,
 * "/page-image/<৩২ হেক্স>" = অ্যাডমিনের দেওয়া ছবি (PageImage)।
 */
final class SiteIcons
{
    public const HIDDEN = 'hide';

    /** slot => [সাইটের সঙ্গে আসা ছবি (public/assets-এর ভেতরে), অ্যাডমিনকে দেখানো নাম] */
    public const SLOTS = [
        'home.hero'     => ['img/icons/badge-hero.svg', 'হোম: হিরোর ছোট হেডিংয়ের আইকন'],
        'home.services' => ['img/icons/badge-section.svg', 'হোম: সেবা সেকশনের আইকন'],
        'home.about'    => ['img/icons/badge-section.svg', 'হোম: পরিচয় সেকশনের আইকন'],
        'home.steps'    => ['img/icons/badge-section.svg', 'হোম: ধাপ সেকশনের আইকন'],
        'home.faq'      => ['img/icons/badge-section.svg', 'হোম: প্রশ্ন-উত্তর সেকশনের আইকন'],
        'reg.join'      => ['img/icons/badge-section.svg', 'রেজিস্ট্রেশন নির্দেশিকা: নিচের সেকশনের আইকন'],
    ];

    public static function exists(string $slot): bool
    {
        return isset(self::SLOTS[$slot]);
    }

    /** "default" | "custom" | "hidden" */
    public static function state(string $slot): string
    {
        $value = Content::get('icon.' . $slot);
        if ($value === self::HIDDEN) {
            return 'hidden';
        }

        return self::isCustom($value) ? 'custom' : 'default';
    }

    /** এখন দেখানোর ছবির ঠিকানা (লুকানো থাকলেও, লাইভ এডিটে ঝাপসা করে দেখাতে)। */
    public static function src(string $slot): string
    {
        $value = Content::get('icon.' . $slot);

        return self::isCustom($value) ? $value : asset(self::SLOTS[$slot][0]);
    }

    public static function original(string $slot): string
    {
        return asset(self::SLOTS[$slot][0]);
    }

    public static function label(string $slot): string
    {
        return self::SLOTS[$slot][1];
    }

    /** অ্যাডমিনের দেওয়া ছবি: শুধু আমাদের নিজের /page-image/<৩২ হেক্স> ঠিকানা। */
    public static function isCustom(string $value): bool
    {
        return preg_match('#^/page-image/[a-f0-9]{32}$#', $value) === 1;
    }
}
