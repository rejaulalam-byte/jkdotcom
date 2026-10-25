<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের ছোট লেখা ও বার্তা (app/Content/defaults.php-এর কী-গুলো) অ্যাডমিনে বদলানোর জন্য সাজানো তালিকা।
 * ভাগগুলো defaults.php-এর "// ---- নাম ----" মন্তব্য থেকেই আসে, তাই নতুন লেখা সেখানে যোগ করলে অ্যাডমিনেও নিজে আসে।
 * প্রতিটি কী হয় "পেইজের লেখা" ('page') নয়তো "বার্তা" ('msg') ট্যাবে (MESSAGE নিয়ম)। সুইচ/সেটিং ও খোলা পেইজের
 * পুরো লেখা (page.*.html) এখানে নেই।
 */
final class SiteTexts
{
    /** যে কী-গুলো লেখা নয় (সুইচ বা সেটিং) — এখানে দেখানো হয় না। */
    private const SKIP = '/^(settings\.|page\.|home\.ads\.show$)/';

    /** "বার্তা" ট্যাবে যায়: সফলতা/ভুলের বার্তা, পপ-আপ, আগ্রহীর পপ-আপ, ফাঁকা-তালিকার লেখা। */
    private const MESSAGE = '/(\.done(\.|_|$)|^form\.msg\.|\.lead\.|^consent\.error$|\.empty$|\.none$|^worker\.rate\.(thanks|invalid|limit|error)$'
        . '|^(login\.unlocked|password\.changed|setup\.done|setup\.missing|profile\.done|forgot\.(ok|done_title|done_1|done_2))$)/';

    /** একটা লেখার সর্বোচ্চ দৈর্ঘ্য। */
    public const MAX_LENGTH = 3000;

    /** @var list<array{title:string,keys:list<string>}>|null */
    private static ?array $sections = null;

    /**
     * defaults.php পড়ে ভাগ ও প্রতিটি ভাগের কী (ফাইলের ক্রমে)।
     *
     * @return list<array{title:string,keys:list<string>}>
     */
    public static function sections(): array
    {
        if (self::$sections !== null) {
            return self::$sections;
        }
        $lines = file(BASE_PATH . '/app/Content/defaults.php', FILE_IGNORE_NEW_LINES) ?: [];
        $out = [];
        $current = ['title' => 'সাধারণ', 'keys' => []];
        foreach ($lines as $line) {
            if (preg_match('#^\s*//\s*-{3,}\s*(.+?)\s*-{3,}\s*$#u', $line, $m) === 1) {
                if ($current['keys'] !== []) {
                    $out[] = $current;
                }
                // কারিগরি অংশ (ঠিকানা, Figma নম্বর) অ্যাডমিনের তালিকায় দরকার নেই
                $title = trim((string) preg_replace('/\s*\([^)]*(\/|Figma|\d{3,})[^)]*\)|;.*$/u', '', $m[1]));
                $current = ['title' => $title !== '' ? $title : $m[1], 'keys' => []];
            } elseif (preg_match("/^\s*'([a-z0-9_.-]+)'\s*=>/", $line, $m) === 1 && preg_match(self::SKIP, $m[1]) !== 1) {
                $current['keys'][] = $m[1];
            }
        }
        if ($current['keys'] !== []) {
            $out[] = $current;
        }

        return self::$sections = $out;
    }

    /** কী-টা কোন ট্যাবের: 'msg' (বার্তা) বা 'page' (পেইজের লেখা)। */
    public static function tabOf(string $key): string
    {
        return preg_match(self::MESSAGE, $key) === 1 ? 'msg' : 'page';
    }

    /**
     * এক ট্যাবের ভাগগুলো (খালি ভাগ বাদ), প্রতিটিতে শুধু সেই ট্যাবের কী।
     *
     * @return list<array{title:string,keys:list<string>}>
     */
    public static function sectionsFor(string $tab): array
    {
        $out = [];
        foreach (self::sections() as $section) {
            $keys = array_values(array_filter($section['keys'], static fn (string $k): bool => self::tabOf($k) === $tab));
            if ($keys !== []) {
                $out[] = ['title' => $section['title'], 'keys' => $keys];
            }
        }

        return $out;
    }

    /** কী-টা কি এখানে বদলানো যায়? */
    public static function editable(string $key): bool
    {
        foreach (self::sections() as $section) {
            if (in_array($key, $section['keys'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * লেখা বা কী-র নামে খোঁজা (এক ট্যাবের ভেতরে)।
     *
     * @return list<string>
     */
    public static function search(string $tab, string $q): array
    {
        $q = mb_strtolower(trim($q));
        $out = [];
        foreach (self::sectionsFor($tab) as $section) {
            foreach ($section['keys'] as $key) {
                if (str_contains($key, $q) || str_contains(mb_strtolower(Content::get($key)), $q) || str_contains(mb_strtolower(self::original($key)), $q)) {
                    $out[] = $key;
                }
            }
        }

        return $out;
    }

    /** সাইটের সঙ্গে আসা শুরুর লেখা। */
    public static function original(string $key): string
    {
        static $defaults = null;
        $defaults ??= require BASE_PATH . '/app/Content/defaults.php';

        return (string) ($defaults[$key] ?? '');
    }

    /**
     * শুরুর লেখার "{name}"-ধাঁচের জায়গা-ধারক (এগুলো নতুন লেখাতেও থাকতে হবে, নইলে নাম/এলাকা বসবে না)।
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
}
