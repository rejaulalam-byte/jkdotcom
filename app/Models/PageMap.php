<?php

declare(strict_types=1);

namespace App\Models;

/**
 * "সাইটের লেখা"-র পেইজ-মানচিত্র (app/Content/page-map.php): বাঁয়ের পেইজ-তালিকা ও প্রতিটি পেইজের অংশ ও লেখা।
 * মানচিত্রে না-থাকা কী নিজে নিজে "অন্যান্য লেখা" পেইজে যায়।
 */
final class PageMap
{
    /** @var list<array<string,mixed>>|null */
    private static ?array $tree = null;

    /**
     * পুরো তালিকা (প্রতিটির children থাকতে পারে); শেষে দরকার হলে "অন্যান্য লেখা"।
     *
     * @return list<array<string,mixed>>
     */
    public static function tree(): array
    {
        if (self::$tree !== null) {
            return self::$tree;
        }
        $tree = require BASE_PATH . '/app/Content/page-map.php';
        $used = [];
        $walk = static function (array $nodes) use (&$walk, &$used): void {
            foreach ($nodes as $node) {
                foreach ($node['parts'] as [, $patterns]) {
                    foreach (self::expand($patterns) as $key) {
                        $used[$key] = true;
                    }
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk($tree);
        // অ্যাডমিনের বানানো নতুন পেইজ: মেনুতে না থাকলে লাইভ পেইজ এডিটের "অন্যান্য"-তে আসে (LivePages)
        foreach (CustomPages::all() as $custom) {
            $tree[] = ['id' => 'cp-' . $custom['slug'], 'title' => $custom['name'] . ($custom['hidden'] ? ' (লুকানো)' : ''),
                'url' => CustomPages::url($custom['slug']), 'open' => CustomPages::OPEN_PREFIX . $custom['slug'], 'parts' => []];
        }
        $rest = array_values(array_filter(SiteTexts::keys(), static fn (string $k): bool => !isset($used[$k])));
        if ($rest !== []) {
            $tree[] = ['id' => 'other', 'title' => 'অন্যান্য লেখা', 'parts' => [['বাকি লেখা', $rest]]];
        }

        return self::$tree = $tree;
    }

    /**
     * id ধরে একটা পেইজ (ভেতরের পেইজসহ খোঁজে)।
     *
     * @return array<string,mixed>|null
     */
    public static function find(string $id): ?array
    {
        $search = static function (array $nodes) use (&$search, $id): ?array {
            foreach ($nodes as $node) {
                if ($node['id'] === $id) {
                    return $node;
                }
                $found = $search($node['children'] ?? []);
                if ($found !== null) {
                    return $found;
                }
            }

            return null;
        };

        return $search(self::tree());
    }

    /**
     * একটা পেইজের অংশগুলো, প্রতিটিতে আসল কী-র তালিকা; বার্তা-ধরনের কী শেষে "এই পেইজের বার্তা"-য়।
     *
     * @param array<string,mixed> $node
     * @return list<array{title:string,keys:list<string>,messages:bool}>
     */
    public static function parts(array $node): array
    {
        $out = [];
        $messages = [];
        foreach ($node['parts'] as [$title, $patterns]) {
            $keys = [];
            foreach (self::expand($patterns) as $key) {
                if (SiteTexts::isMessage($key)) {
                    $messages[] = $key;
                } else {
                    $keys[] = $key;
                }
            }
            if ($keys !== []) {
                $out[] = ['title' => (string) $title, 'keys' => $keys, 'messages' => false];
            }
        }
        if ($messages !== []) {
            $out[] = ['title' => 'এই পেইজের বার্তা', 'keys' => array_values(array_unique($messages)), 'messages' => true];
        }

        return $out;
    }

    /**
     * খোঁজার ফলের জন্য: কোন কী কোন পেইজে (প্রথম যেটায় পাওয়া যায়) — কী => [id, পেইজের নাম]।
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function owners(): array
    {
        $out = [];
        $walk = static function (array $nodes) use (&$walk, &$out): void {
            foreach ($nodes as $node) {
                foreach ($node['parts'] as [, $patterns]) {
                    foreach (self::expand($patterns) as $key) {
                        $out[$key] ??= [(string) $node['id'], (string) $node['title']];
                    }
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk(self::tree());

        return $out;
    }

    /**
     * "home.hero.*" ধাঁচের তালিকা থেকে আসল কী (defaults.php-এর ক্রমে, বদলানো যায় এমনগুলোই)।
     *
     * @param list<string> $patterns
     * @return list<string>
     */
    private static function expand(array $patterns): array
    {
        $out = [];
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '*')) {
                $prefix = substr($pattern, 0, -1);
                foreach (SiteTexts::keys() as $key) {
                    if (str_starts_with($key, $prefix)) {
                        $out[] = $key;
                    }
                }
            } elseif (SiteTexts::editable($pattern)) {
                $out[] = $pattern;
            }
        }

        return array_values(array_unique($out));
    }
}
