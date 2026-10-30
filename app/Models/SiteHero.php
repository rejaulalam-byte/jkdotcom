<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * হোমের হিরো (একদম ওপরের বড় অংশ) সাজানো — শুধু সুপার অ্যাডমিন (<admin>/hero):
 *   ১) লেখার অ্যানিমেশনের ধরন (তিনটির একটা);
 *   ২) পটভূমি: সাধারণ (থিমের প্রধান রং) / নিজের বাছা রং (একটা বা দুটো রঙের গ্র্যাডিয়েন্ট) / একটা ছবি;
 *   ৩) ডানের "আজকের কাজের তালিকা" দেখানো না লুকানো।
 * সেটিং content_blocks-এর "hero.*" কী; পটভূমির CSS /site-theme-এর সঙ্গে যায় (SiteTheme::stylesheet()),
 * কারণ ইনলাইন style CSP আটকায়।
 */
final class SiteHero
{
    /** অ্যানিমেশন: কী => নাম। 'scatter' শুরুর (চারদিক থেকে উড়ে এসে)। */
    public const ANIMS = [
        'scatter' => 'চারদিক থেকে অক্ষরগুলো উড়ে এসে বসা (শুরুর)',
        'right' => 'ডান দিক থেকে এক-একটা অক্ষর উড়ে এসে বাঁ দিকে বসা',
        'left' => 'বাঁ দিকের ভেতর থেকে লেখা ধীরে বেরিয়ে ডান দিকে এসে নিজের জায়গায় বসা',
    ];

    /** গ্র্যাডিয়েন্টের দিক: কী => [নাম, CSS-এর মান]। */
    public const DIRECTIONS = [
        'down' => ['ওপর থেকে নিচে', 'to bottom'],
        'right' => ['বাঁ থেকে ডানে', 'to right'],
        'diagonal' => ['কোনাকুনি', '135deg'],
    ];

    /** ছবির ওপরের কালো পর্দা (লেখা পড়ার সুবিধার জন্য), শতাংশে। */
    public const OVERLAYS = [0, 20, 30, 40, 50, 60];

    /** ছবির মাপ ও ওজনের নির্দেশ: অ্যাডমিন-পেইজে ছবি বাছার ঘরের নিচে দেখানো হয়। */
    public const IMAGE_ADVICE = 'সুপারিশ: ১৯২০ × ১০০০ পিক্সেল (চওড়া ছবি; ছোট পর্দায় ছবির মাঝের অংশ দেখায়, কিনারা কাটা পড়তে পারে)। '
        . 'ফরমেট JPG, PNG বা WebP। ওজন সর্বোচ্চ ২ MB; সাইট দ্রুত খুলতে ৫০০ KB-এর নিচে রাখা ভালো (JPG/WebP ছোট হয়)। '
        . 'লেখা আর ডানের তালিকা ছবির ওপরে বসে, তাই বেশি ব্যস্ত ছবি না নিয়ে শান্ত ছবি নিন।';

    /** @return 'scatter'|'right'|'left' */
    public static function anim(): string
    {
        $v = Content::get('hero.anim', 'scatter');

        return isset(self::ANIMS[$v]) ? $v : 'scatter';
    }

    /** @return 'default'|'color'|'image' */
    public static function mode(): string
    {
        $mode = Content::get('hero.bg', 'default');
        if ($mode === 'color' && self::color1() !== '') {
            return 'color';
        }

        return $mode === 'image' && self::image() !== '' ? 'image' : 'default';
    }

    public static function color1(): string
    {
        return self::hex(Content::get('hero.color1', ''));
    }

    /** দ্বিতীয় রং; '' = একটাই রং (গ্র্যাডিয়েন্ট নেই)। */
    public static function color2(): string
    {
        return self::hex(Content::get('hero.color2', ''));
    }

    public static function direction(): string
    {
        $v = Content::get('hero.dir', 'down');

        return isset(self::DIRECTIONS[$v]) ? $v : 'down';
    }

    /** ছবির ওয়েব-ঠিকানা (/page-image/<hex>) অথবা ''। */
    public static function image(): string
    {
        $v = Content::get('hero.image', '');

        return preg_match('#^/page-image/[a-f0-9]{32}$#', $v) === 1 ? $v : '';
    }

    public static function overlay(): int
    {
        $v = (int) Content::get('hero.overlay', '30');

        return in_array($v, self::OVERLAYS, true) ? $v : 30;
    }

    /** ডানের "আজকের কাজের তালিকা" কি দেখানো হবে (শুরুতে হ্যাঁ)? */
    public static function showToday(): bool
    {
        return Content::get('hero.today_show', '1') !== '0';
    }

    /** কিছু কি বদলানো (শুরুর সাজ নয়)? */
    public static function anyChanged(): bool
    {
        return self::mode() !== 'default';
    }

    /** #rrggbb (ছোট হাতের) অথবা ''। */
    public static function hex(string $value): string
    {
        $value = strtolower(trim($value));

        return preg_match('/^#[0-9a-f]{6}$/', $value) === 1 ? $value : '';
    }

    /**
     * /site-theme-এ যাওয়া হিরোর পটভূমির CSS ('' = শুরুর সাজ)। রং বা ছবি বদলালে লেখা পড়ার জন্য হালকা পটভূমিতে গাঢ় লেখা।
     */
    public static function css(): string
    {
        $mode = self::mode();
        if ($mode === 'default') {
            return '';
        }

        if ($mode === 'color') {
            $c1 = self::color1();
            $c2 = self::color2();
            $background = $c2 === '' ? $c1 : 'linear-gradient(' . self::DIRECTIONS[self::direction()][1] . ', ' . $c1 . ', ' . $c2 . ')';
            $dark = SiteTheme::contrast($c1, '#ffffff') < 3.5 || ($c2 !== '' && SiteTheme::contrast($c2, '#ffffff') < 3.5);
        } else {
            $alpha = number_format(self::overlay() / 100, 2, '.', '');
            $background = 'linear-gradient(rgba(0, 0, 0, ' . $alpha . '), rgba(0, 0, 0, ' . $alpha . ')), url(' . self::image() . ') center / cover no-repeat';
            $dark = false; // ছবিতে কালো পর্দা থাকে, সাদা লেখাই পড়া যায়
        }

        $css = "\n.hero { background: " . $background . "; }\n";
        if ($dark) {
            $css .= ".hero { color: var(--ink); }\n"
                . ".hero .hero__title-1, .hero .brandname { color: var(--teal); }\n"
                . ".hero .btn--light { background: var(--teal); color: var(--white); }\n";
        }

        return $css;
    }
}
