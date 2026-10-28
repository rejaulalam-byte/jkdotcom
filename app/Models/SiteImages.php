<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\PageImage;

/**
 * পেইজের বদলানো-যায় এমন ছবি ও ছোট আইকন: লোগো, সেকশনের ছোট হেডিংয়ের আইকন, মানচিত্র, কার্ডের ছবি ইত্যাদি।
 * প্রতিটির একটা নাম (slot) আর সাইটের সঙ্গে আসা ছবি। অ্যাডমিন "লাইভ পেইজ এডিট" থেকে নতুন ছবি দিতে,
 * শুরুর ছবিতে ফিরতে, লুকাতে বা আবার দেখাতে পারেন (শেষ দুটি সুপার অ্যাডমিন)।
 * অবস্থা থাকে লেখার টেবিলে (Content) "image.<slot>" নামে: না থাকলে শুরুর ছবি, "hide" = লুকানো,
 * "/page-image/<৩২ হেক্স>" = অ্যাডমিনের দেওয়া ছবি (PageImage)।
 * নতুন ছবির জায়গা: এখানে একটা slot যোগ করে ভিউতে site_image('slot', [...]) বসান।
 */
final class SiteImages
{
    public const HIDDEN = 'hide';

    /** slot => [সাইটের সঙ্গে আসা ছবি (public/assets-এর ভেতরে), অ্যাডমিনকে দেখানো নাম] */
    public const SLOTS = [
        'site.logo'            => ['img/site/logo-header.png', 'ওপরের মেনুর লোগো'],
        'site.logo_footer'     => ['img/site/logo-figma.png', 'ফুটারের লোগো'],
        'home.hero'            => ['img/icons/badge-hero.svg', 'হোম: হিরোর ছোট হেডিংয়ের আইকন'],
        'home.services'        => ['img/icons/badge-section.svg', 'হোম: সেবা সেকশনের আইকন'],
        'home.about'           => ['img/icons/badge-section.svg', 'হোম: পরিচয় সেকশনের আইকন'],
        'home.about.map'       => ['img/site/bangladesh-map.png', 'হোম: পরিচয় সেকশনের বাংলাদেশের মানচিত্র'],
        'home.steps'           => ['img/icons/badge-section.svg', 'হোম: ধাপ সেকশনের আইকন'],
        'home.faq'             => ['img/icons/badge-section.svg', 'হোম: প্রশ্ন-উত্তর সেকশনের আইকন'],
        'home.map'             => ['img/site/map-location.png', 'হোম: নিচের মানচিত্রের ছবি'],
        'contact.icon.address' => ['img/site/contact-address.svg', 'যোগাযোগ: ঠিকানার কার্ডের আইকন'],
        'contact.icon.phone'   => ['img/site/contact-phone.svg', 'যোগাযোগ: মোবাইলের কার্ডের আইকন'],
        'contact.icon.email'   => ['img/site/contact-email.svg', 'যোগাযোগ: ইমেইলের কার্ডের আইকন'],
        'contact.icon.web'     => ['img/site/contact-web.svg', 'যোগাযোগ: ওয়েবসাইটের কার্ডের আইকন'],
        'contact.ceo'          => ['img/site/ceo.png', 'যোগাযোগ: প্রধান নির্বাহীর ছবি'],
        'contact.map'          => ['img/site/map-contact.jpg', 'যোগাযোগ: মানচিত্রের ছবি'],
        'reg.join'             => ['img/icons/badge-section.svg', 'রেজিস্ট্রেশন নির্দেশিকা: নিচের সেকশনের আইকন'],
        'reg.card.pro'         => ['img/site/reg-professional.jpg', 'রেজিস্ট্রেশন নির্দেশিকা: পেশাজীবী কার্ডের ছবি'],
        'reg.card.post'        => ['img/site/reg-job-poster.jpg', 'রেজিস্ট্রেশন নির্দেশিকা: কাজ জানানোর কার্ডের ছবি'],
    ];

    public static function exists(string $slot): bool
    {
        return isset(self::SLOTS[$slot]);
    }

    /** "default" | "custom" | "hidden" */
    public static function state(string $slot): string
    {
        $value = Content::get('image.' . $slot);
        if ($value === self::HIDDEN) {
            return 'hidden';
        }

        return self::isCustom($value) ? 'custom' : 'default';
    }

    /** এখন দেখানোর ছবির ঠিকানা (লুকানো থাকলেও, লাইভ এডিটে ঝাপসা করে দেখাতে)। */
    public static function src(string $slot): string
    {
        $value = Content::get('image.' . $slot);

        return self::isCustom($value) ? $value : asset(self::SLOTS[$slot][0]);
    }

    /**
     * অ্যাডমিনের দেওয়া ছবির আসল মাপ [চওড়া, উঁচু] (ব্রাউজার আগে থেকেই জায়গা রাখতে পারে); শুরুর ছবিতে [0, 0]।
     *
     * @return array{0:int,1:int}
     */
    public static function customSize(string $slot): array
    {
        $value = Content::get('image.' . $slot);

        return self::isCustom($value) ? PageImage::dimensions($value) : [0, 0];
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
