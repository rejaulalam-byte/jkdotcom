<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\PageImage;
use App\Core\Router;
use App\Core\Security;

/**
 * পপ-আপ বিজ্ঞাপন: একটাই ছবি, বাছা পেইজগুলো খুললেই (প্রতিবার, রিফ্রেশেও) মেনুর ঠিক নিচে পেইজের মাঝখানে নিজের মাপে দেখায়;
 * (×) চাপলে বা ২০ সেকেন্ড পরে নিজে সরে যায় (app.js)। ছবি ছোট পর্দায় অনুপাত ঠিক রেখে ছোট হয়।
 * সেটিংগুলো content_blocks-এ "popup.*" কী হিসেবে থাকে (আলাদা টেবিল লাগে না); ছবি PageImage দিয়ে রাখা।
 */
final class PopupAd
{
    /** যেসব পেইজে দেখানো যায়: id => [অ্যাডমিনের নাম, সাইটের পথ] */
    public const PAGES = [
        'home' => ['হোম পেইজ', '/'],
        'today' => ['আজকের কাজ — কাজ সম্পর্কে জানুন', '/today'],
        'today-post' => ['আজকের কাজ — কাজ সম্পর্কে জানান', '/today/post'],
        'services' => ['সেবাসমূহ পেইজ', '/services'],
    ];

    /** না বন্ধ করলে কত সেকেন্ড দেখায়। */
    public const SECONDS = 20;

    /**
     * অ্যাডমিনের জন্য এখনকার সব সেটিং।
     *
     * @return array{active:bool,image:string,width:int,height:int,link:string,title:string,pages:list<string>}
     */
    public static function settings(): array
    {
        return [
            'active' => Content::get('popup.active', '0') === '1',
            'image' => Content::get('popup.image', ''),
            'width' => (int) Content::get('popup.width', '0'),
            'height' => (int) Content::get('popup.height', '0'),
            'link' => Content::get('popup.link', ''),
            'title' => Content::get('popup.title', ''),
            'pages' => array_values(array_intersect(explode(',', Content::get('popup.pages', '')), array_keys(self::PAGES))),
        ];
    }

    /**
     * এই পেইজে দেখানোর বিজ্ঞাপন (চালু, ছবি আছে, পেইজ বাছা), নইলে null।
     *
     * @return array{image:string,width:int,height:int,link:?string,title:string}|null
     */
    public static function forCurrentPage(): ?array
    {
        $s = self::settings();
        if (!$s['active'] || preg_match('#^/page-image/[a-f0-9]{32}$#', $s['image']) !== 1) {
            return null;
        }
        $path = Router::currentPath();
        foreach ($s['pages'] as $id) {
            if (self::PAGES[$id][1] === $path) {
                return [
                    'image' => $s['image'],
                    'width' => max(1, $s['width']),
                    'height' => max(1, $s['height']),
                    'link' => $s['link'] !== '' ? Security::safeUrl($s['link']) : null,
                    'title' => $s['title'] !== '' ? $s['title'] : 'বিজ্ঞাপন',
                ];
            }
        }

        return null;
    }

    /**
     * সেটিং সংরক্ষণ; $image দিলে নতুন ছবি (আগেরটা মোছা হয়), '' দিলে ছবি সরানো, null = ছবি যেমন আছে।
     *
     * @param list<string> $pages
     * @param array{url:string,width:int,height:int}|''|null $image
     */
    public static function save(bool $active, array $pages, string $link, string $title, array|string|null $image): bool
    {
        $old = Content::get('popup.image', '');
        $values = [
            'popup.active' => $active ? '1' : '0',
            'popup.pages' => implode(',', array_values(array_intersect($pages, array_keys(self::PAGES)))),
            'popup.link' => $link,
            'popup.title' => $title,
        ];
        if (is_array($image)) {
            $values += ['popup.image' => $image['url'], 'popup.width' => (string) $image['width'], 'popup.height' => (string) $image['height']];
        } elseif ($image === '') {
            $values += ['popup.image' => '', 'popup.width' => '0', 'popup.height' => '0'];
        }
        foreach ($values as $key => $value) {
            if (!Content::set($key, $value)) {
                return false;
            }
        }
        if ($image !== null && $old !== '' && $old !== ($values['popup.image'] ?? $old)) {
            PageImage::remove($old);
        }

        return true;
    }
}
