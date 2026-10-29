<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\RichText;

/**
 * হোম পেইজের সেকশন: ক্রম, লুকানো/দেখানো, আর অ্যাডমিনের যোগ করা নতুন সেকশন (শুধু সুপার অ্যাডমিন, লাইভ পেইজ এডিট থেকে)।
 * ক্রম ও লুকানো একটা কী-তে JSON ("home.sections": [{id, hidden}]); হিরো সবসময় একদম ওপরে।
 * নতুন সেকশন একটাই নকশায়: ছোট লোগোসহ ছোট শিরোনাম, বড় শিরোনাম, খোলা লেখা (পূর্ণ এডিটর), ঐচ্ছিক বাটন;
 * শিরোনাম ও বাটন বাঁয়ে / মাঝে / ডানে। তথ্য "home.section.<id>" (JSON), লেখা "home.section.<id>.html" (RichText-এ ছাঁকা)।
 */
final class HomeSections
{
    private const KEY = 'home.sections';

    /** সাইটের নিজের সেকশন, হোমে যে ক্রমে আছে => অ্যাডমিনে দেখানোর নাম। */
    public const BUILTIN = [
        'hero' => 'হিরো (একদম ওপরের বড় অংশ)',
        'ads' => 'বিজ্ঞাপন ও চলমান প্রতিনিধি-পট্টি',
        'services' => 'সেবা',
        'about' => 'পরিচয়',
        'steps' => 'কীভাবে কাজ করে (ধাপ)',
        'faq' => 'প্রশ্ন-উত্তর ও মতামত',
        'map' => 'মানচিত্র',
    ];

    /** শিরোনাম ও বাটনের জায়গা => নাম। */
    public const ALIGNS = ['left' => 'বাঁয়ে', 'center' => 'মাঝে', 'right' => 'ডানে'];

    /** সর্বোচ্চ কতগুলো নতুন সেকশন। */
    public const MAX_NEW = 20;

    /** নতুন সেকশনের সাধারণ লোগো (অন্য সেকশনের ছোট শিরোনামের মতো)। */
    public const DEFAULT_ICON = 'img/icons/badge-section.svg';

    /**
     * সব সেকশন, দেখানোর ক্রমে।
     *
     * @return list<array{id:string,hidden:bool,builtin:bool}>
     */
    public static function all(): array
    {
        $stored = json_decode(Content::get(self::KEY), true);
        $out = [];
        $seen = [];
        foreach (is_array($stored) ? $stored : [] as $row) {
            $id = is_array($row) ? (string) ($row['id'] ?? '') : '';
            if ($id === '' || isset($seen[$id]) || (!isset(self::BUILTIN[$id]) && !self::isNewId($id))) {
                continue;
            }
            if (!isset(self::BUILTIN[$id]) && self::data($id) === null) {
                continue;
            }
            $seen[$id] = true;
            $out[] = ['id' => $id, 'hidden' => (bool) ($row['hidden'] ?? false), 'builtin' => isset(self::BUILTIN[$id])];
        }
        // তালিকায় না-থাকা নিজের সেকশন (প্রথমবার বা নতুন যোগ হলে) নিজের জায়গায়
        foreach (array_keys(self::BUILTIN) as $id) {
            if (!isset($seen[$id])) {
                $out[] = ['id' => $id, 'hidden' => false, 'builtin' => true];
            }
        }
        // হিরো সবসময় প্রথম
        usort($out, static fn (array $a, array $b): int => ($b['id'] === 'hero') <=> ($a['id'] === 'hero'));

        return $out;
    }

    public static function exists(string $id): bool
    {
        foreach (self::all() as $s) {
            if ($s['id'] === $id) {
                return true;
            }
        }

        return false;
    }

    public static function isNewId(string $id): bool
    {
        return preg_match('/^s[0-9]{1,4}$/', $id) === 1;
    }

    /** সেকশনের নাম (অ্যাডমিনের পট্টিতে ও কার্যলগে)। */
    public static function label(string $id): string
    {
        if (isset(self::BUILTIN[$id])) {
            return self::BUILTIN[$id];
        }
        $d = self::data($id);

        return 'নতুন সেকশন: ' . ($d !== null && $d['heading'] !== '' ? $d['heading'] : $id);
    }

    /**
     * নতুন সেকশনের তথ্য, না থাকলে null।
     *
     * @return array{badge:string,heading:string,align:string,icon:string,button_text:string,button_url:string,button_align:string}|null
     */
    public static function data(string $id): ?array
    {
        if (!self::isNewId($id)) {
            return null;
        }
        $d = json_decode(Content::get('home.section.' . $id), true);
        if (!is_array($d)) {
            return null;
        }
        $align = static fn (mixed $v): string => is_string($v) && isset(self::ALIGNS[$v]) ? $v : 'center';

        return [
            'badge' => (string) ($d['badge'] ?? ''),
            'heading' => (string) ($d['heading'] ?? ''),
            'align' => $align($d['align'] ?? null),
            'icon' => (string) ($d['icon'] ?? ''),
            'button_text' => (string) ($d['button_text'] ?? ''),
            'button_url' => (string) ($d['button_url'] ?? ''),
            'button_align' => $align($d['button_align'] ?? null),
        ];
    }

    /** সংরক্ষিত লেখা (এডিটরে খোলার জন্য)। */
    public static function rawHtml(string $id): string
    {
        return Content::get('home.section.' . $id . '.html');
    }

    /** সাইটে দেখানোর নিরাপদ লেখা। */
    public static function html(string $id): string
    {
        return RichText::show(self::rawHtml($id));
    }

    /** লোগোর ঠিকানা: অ্যাডমিনের তোলা ছবি, নইলে সাধারণ লোগো। */
    public static function iconUrl(array $data): string
    {
        return $data['icon'] !== '' ? $data['icon'] : asset(self::DEFAULT_ICON);
    }

    /** @param 'up'|'down' $dir */
    public static function move(string $id, string $dir): ?string
    {
        if ($id === 'hero') {
            return 'হিরো সবসময় একদম ওপরে থাকে।';
        }
        $all = self::all();
        $i = array_search($id, array_column($all, 'id'), true);
        if ($i === false) {
            return 'সেকশনটি পাওয়া যায়নি।';
        }
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j < 1 || $j >= count($all)) {
            return $dir === 'up' ? 'এটা আর ওপরে যাবে না (হিরোর ঠিক নিচে)।' : 'এটা এখনই সবার নিচে।';
        }
        [$all[$i], $all[$j]] = [$all[$j], $all[$i]];

        return self::store($all);
    }

    public static function setHidden(string $id, bool $hidden): ?string
    {
        $all = self::all();
        $found = false;
        foreach ($all as &$s) {
            if ($s['id'] === $id) {
                $s['hidden'] = $hidden;
                $found = true;
            }
        }
        unset($s);

        return $found ? self::store($all) : 'সেকশনটি পাওয়া যায়নি।';
    }

    /**
     * নতুন সেকশন, $nextTo সেকশনের ওপরে ($above) বা নিচে; শুরুর লেখাসহ। ঠিক থাকলে null ও $made-এ নতুন id।
     */
    public static function add(string $nextTo, bool $above, ?string &$made = null): ?string
    {
        $all = self::all();
        $i = array_search($nextTo, array_column($all, 'id'), true);
        if ($i === false) {
            return 'কোন সেকশনের পাশে বসাবেন, তা পাওয়া যায়নি।';
        }
        $newCount = count(array_filter($all, static fn (array $s): bool => !$s['builtin']));
        if ($newCount >= self::MAX_NEW) {
            return 'সর্বোচ্চ ' . bn(self::MAX_NEW) . 'টি নতুন সেকশন যোগ করা যায়; আগে কোনোটা মুছুন।';
        }
        $n = 1;
        while (Content::get('home.section.s' . $n) !== '') {
            $n++;
        }
        $id = 's' . $n;
        $data = ['badge' => 'নতুন সেকশন', 'heading' => 'নতুন সেকশনের বড় শিরোনাম', 'align' => 'center', 'icon' => '',
            'button_text' => '', 'button_url' => '', 'button_align' => 'center'];
        $at = $above ? $i : $i + 1;
        $at = max(1, $at); // হিরোর ওপরে নয়
        array_splice($all, $at, 0, [['id' => $id, 'hidden' => false, 'builtin' => false]]);
        if (!Content::set('home.section.' . $id, (string) json_encode($data, JSON_UNESCAPED_UNICODE))
            || !Content::set('home.section.' . $id . '.html', '<p>এখানে সেকশনের লেখা, ছবি বা টেবিল বসান। "সম্পাদনা" চাপলে পূর্ণ এডিটর খুলবে।</p>')) {
            return 'সেকশনটি সংরক্ষণ করা যায়নি।';
        }
        $error = self::store($all);
        if ($error === null) {
            $made = $id;
        }

        return $error;
    }

    /**
     * নতুন সেকশনের সব তথ্য ও লেখা একসঙ্গে। ঘরগুলো আগেই ছোট করে ছাঁকা (clean_input); লেখা এখানে RichText-এ ছাঁকে।
     *
     * @param array{badge:string,heading:string,align:string,icon:string,button_text:string,button_url:string,button_align:string} $data
     */
    public static function update(string $id, array $data, string $html): ?string
    {
        if (self::data($id) === null) {
            return 'সেকশনটি পাওয়া যায়নি।';
        }
        if ($data['heading'] === '') {
            return 'বড় শিরোনাম খালি রাখা যাবে না।';
        }
        foreach (['align', 'button_align'] as $f) {
            if (!isset(self::ALIGNS[$data[$f]])) {
                $data[$f] = 'center';
            }
        }
        if ($data['button_text'] !== '' && !self::validUrl($data['button_url'])) {
            return 'বাটনের লিংক সাইটের ঠিকানা (/ দিয়ে শুরু, যেমন /services) অথবা http(s)://… হতে হবে।';
        }
        if ($data['icon'] !== '' && preg_match('#^/page-image/[0-9a-f]{32}$#', $data['icon']) !== 1) {
            $data['icon'] = '';
        }
        $clean = RichText::sanitize($html);
        if (!Content::set('home.section.' . $id, (string) json_encode($data, JSON_UNESCAPED_UNICODE))
            || !Content::set('home.section.' . $id . '.html', $clean)) {
            return 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
        }

        return null;
    }

    /** নতুন সেকশন চিরতরে মোছে (সাইটের নিজের সেকশন মোছা যায় না, শুধু লুকানো যায়)। */
    public static function delete(string $id): ?string
    {
        if (self::data($id) === null) {
            return 'এই সেকশন মোছা যায় না (সাইটের নিজের সেকশন শুধু লুকানো যায়)।';
        }
        $all = array_values(array_filter(self::all(), static fn (array $s): bool => $s['id'] !== $id));
        $error = self::store($all);
        if ($error === null) {
            Content::forget('home.section.' . $id);
            Content::forget('home.section.' . $id . '.html');
        }

        return $error;
    }

    public static function validUrl(string $url): bool
    {
        return (str_starts_with($url, '/') && !str_starts_with($url, '//') && preg_match('#^/[A-Za-z0-9/_\-.?=&%\#]*$#', $url) === 1)
            || preg_match('#^https?://[^\s"<>]+$#i', $url) === 1;
    }

    /** @param list<array{id:string,hidden:bool,builtin:bool}> $all */
    private static function store(array $all): ?string
    {
        $rows = array_map(static fn (array $s): array => ['id' => $s['id'], 'hidden' => $s['hidden']], $all);

        return Content::set(self::KEY, (string) json_encode($rows, JSON_UNESCAPED_UNICODE)) ? null : 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
    }
}
