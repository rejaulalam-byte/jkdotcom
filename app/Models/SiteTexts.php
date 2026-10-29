<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের ছোট লেখা ও বার্তা (app/Content/defaults.php-এর কী) অ্যাডমিনে বদলানোর নিয়ম: কোন কী বদলানো যায়, শুরুর লেখা,
 * জায়গা-ধারক ({name}), বার্তা কি না, আর ঘরের সহজ বাংলা নাম। পেইজ ধরে সাজানো PageMap-এ।
 */
final class SiteTexts
{
    /** যে কী-গুলো লেখা নয় (সুইচ বা সেটিং) বা অন্য জায়গায় বদলায় (খোলা পেইজের পুরো লেখা)। */
    private const SKIP = '/^(settings\.|page\.|popup\.|mail\.|message\.|home\.ads\.show$)/';

    /** বার্তা-ধরনের কী: সফলতা/ভুলের বার্তা, পপ-আপ, আগ্রহীর পপ-আপ, ফাঁকা-তালিকার লেখা (পেইজের শেষে আলাদা অংশে দেখায়)। */
    private const MESSAGE = '/(\.done(\.|_|$)|(^|\.)msg\.|\.lead\.|^consent\.error$|\.empty$|\.none$|^worker\.rate\.(thanks|invalid|limit|error)$'
        . '|^(login\.unlocked|password\.changed|setup\.done|setup\.missing|profile\.done|forgot\.(ok|done_title|done_1|done_2))$)/';

    /** কী-র শেষ অংশ => ঘরের সহজ নাম (না মিললে নাম ছাড়া শুধু শুরুর লেখা দেখায়)। */
    private const LABELS = [
        'badge' => 'ছোট শিরোনাম (ব্যাজ)', 'title' => 'শিরোনাম', 'title_1' => 'বড় শিরোনাম — প্রথম লাইন', 'title_2' => 'বড় শিরোনাম — দ্বিতীয় লাইন',
        'heading' => 'শিরোনাম', 'text' => 'লেখা', 'text_1' => 'লেখা (প্রথম অংশ)', 'text_2' => 'লেখা (দ্বিতীয় অংশ)', 'text_3' => 'লেখা (তৃতীয় অংশ)',
        'button' => 'বাটনের লেখা', 'submit' => 'জমা দেওয়ার বাটন', 'banner' => 'ব্যানারের শিরোনাম', 'crumb' => 'পথ-নির্দেশে পেইজের নাম',
        'intro' => 'ভূমিকার লেখা', 'description' => 'সার্চ ইঞ্জিনের জন্য ছোট বর্ণনা', 'placeholder' => 'ঘরের ভেতরের হালকা লেখা',
        'q' => 'প্রশ্ন', 'a' => 'উত্তর', 'alt' => 'ছবির বিকল্প লেখা', 'map_alt' => 'ছবির বিকল্প লেখা', 'label' => 'লেবেল', 'note' => 'নোট',
        'empty' => 'কিছু না থাকলে দেখানো লেখা', 'link' => 'লিংকের লেখা',
    ];

    /** একটা লেখার সর্বোচ্চ দৈর্ঘ্য। */
    public const MAX_LENGTH = 3000;

    /** @var list<string>|null */
    private static ?array $keys = null;

    /**
     * বদলানো যায় এমন সব কী, defaults.php-এর ক্রমে।
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        if (self::$keys === null) {
            $defaults = require BASE_PATH . '/app/Content/defaults.php';
            self::$keys = array_values(array_filter(array_keys($defaults), static fn (string $k): bool => preg_match(self::SKIP, $k) !== 1));
        }

        return self::$keys;
    }

    public static function editable(string $key): bool
    {
        return in_array($key, self::keys(), true);
    }

    public static function isMessage(string $key): bool
    {
        return preg_match(self::MESSAGE, $key) === 1;
    }

    /** ঘরের সহজ নাম, না থাকলে খালি লেখা। */
    public static function label(string $key): string
    {
        $last = substr($key, (int) strrpos($key, '.') + 1);

        return self::LABELS[$last] ?? '';
    }

    /** সাইটের সঙ্গে আসা শুরুর লেখা। */
    public static function original(string $key): string
    {
        static $defaults = null;
        $defaults ??= require BASE_PATH . '/app/Content/defaults.php';

        return (string) ($defaults[$key] ?? '');
    }

    /**
     * শুরুর লেখার "{name}"-ধাঁচের জায়গা-ধারক (নতুন লেখাতেও থাকতে হবে, নইলে নাম/এলাকা বসবে না)।
     *
     * @return list<string>
     */
    public static function placeholders(string $key): array
    {
        preg_match_all('/\{[a-z_]+\}/', self::original($key), $m);

        return array_values(array_unique($m[0]));
    }

    /** এই লেখায় রং/মোটা করার চিহ্ন ([[color:...|...]], {...}) চলে কি না (colored() দিয়ে দেখানো লেখা)। */
    public static function usesColorMarkup(string $key): bool
    {
        return in_array($key, ['home.rep.text', 'worker.note.text', 'contact.holiday'], true);
    }

    /**
     * লেখা বা কী-র নামে খোঁজা (সব কী-র মধ্যে)।
     *
     * @return list<string>
     */
    public static function search(string $q): array
    {
        $q = mb_strtolower(trim($q));

        return array_values(array_filter(self::keys(), static fn (string $k): bool => str_contains($k, $q)
            || str_contains(mb_strtolower(Content::get($k)), $q) || str_contains(mb_strtolower(self::original($k)), $q)));
    }
}
