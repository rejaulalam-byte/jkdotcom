<?php

declare(strict_types=1);

namespace App\Models;

/**
 * "লাইভ পেইজ এডিট"-এর ওপরের ড্রপডাউনগুলো: "সকল পেইজ", সাইটের মেনুর প্রতিটি আইটেম (app/Content/menu.php)
 * এবং "অন্যান্য"। পেইজের নাম ও ঠিকানা আসে পেইজ-মানচিত্র (PageMap) থেকে; মেনুর কোন আইটেমের নিচে কোন পেইজ,
 * তা ঠিকানা মিলিয়ে নিজে নিজে ঠিক হয়। মেনুতে না-থাকা পেইজ (নতুন পেইজসহ) "অন্যান্য"-তে যায়।
 */
final class LivePages
{
    /**
     * @return array{all: list<array{id:string,title:string,url:?string,note:?string}>,
     *               menus: list<array{label:string,pages:list<array{id:string,title:string,url:?string,note:?string}>}>,
     *               other: list<array{id:string,title:string,url:?string,note:?string}>}
     */
    public static function menus(): array
    {
        $menu = require BASE_PATH . '/app/Content/menu.php';
        $groups = [];
        foreach ($menu as [$href, $label, $children]) {
            $groups[] = ['label' => $label, 'hrefs' => array_merge([$href], array_column($children, 0)), 'pages' => []];
        }
        $other = [];
        $all = [];

        foreach (PageMap::tree() as $node) {
            $pages = self::flatten($node);
            $all = array_merge($all, $pages);
            $urls = array_filter(array_column($pages, 'url'));
            $placed = false;
            foreach ($groups as &$group) {
                if (array_intersect($urls, $group['hrefs']) !== []) {
                    $group['pages'] = array_merge($group['pages'], $pages);
                    $placed = true;
                    break;
                }
            }
            unset($group);
            if (!$placed) {
                $other = array_merge($other, $pages);
            }
        }

        return [
            'all' => $all,
            'menus' => array_map(static fn (array $g): array => ['label' => $g['label'], 'pages' => $g['pages']], $groups),
            'other' => $other,
        ];
    }

    /**
     * একটা পেইজ ও তার ভেতরের পেইজগুলো এক তালিকায়। ভেতরে-পেইজ-থাকা নামের নিজের ঠিকানা না থাকলে (যেমন "আজকের প্রকাশিত")
     * সেটা আলাদা পেইজ নয়, তাই বাদ।
     *
     * @param array<string,mixed> $node
     * @return list<array{id:string,title:string,url:?string,note:?string}>
     */
    private static function flatten(array $node): array
    {
        $out = [];
        $children = $node['children'] ?? [];
        if (!empty($node['url']) || $children === []) {
            $out[] = self::page($node);
        }
        foreach ($children as $child) {
            $out = array_merge($out, self::flatten($child));
        }

        return $out;
    }

    /**
     * @param array<string,mixed> $node
     * @return array{id:string,title:string,url:?string,note:?string}
     */
    private static function page(array $node): array
    {
        $id = (string) $node['id'];
        $url = !empty($node['url']) ? (string) $node['url'] : null;
        $note = null;
        if ($id === 'worker') {
            // কর্মীর পেইজের নমুনা: ইলেকট্রিক মিস্ত্রির তালিকার প্রথম অনুমোদিত কর্মী
            $first = UserRepository::listWorkersByService('electrician')[0] ?? null;
            $url = $first !== null ? '/workers/' . (int) $first['id'] : null;
            $note = $first === null ? 'দেখানোর মতো কোনো অনুমোদিত কর্মী নেই।' : null;
        } elseif ($url === null && in_array($id, ['common', 'other'], true)) {
            $note = 'এই লেখাগুলো আলাদা কোনো পেইজ নয় (সব পেইজের ওপরের পট্টি, ফুটার ইত্যাদি)।';
        } elseif ($url === null) {
            $note = 'এই পেইজ শুধু লগইন-করা সদস্য দেখেন, তাই এখানে দেখানো যায় না।';
        }

        return ['id' => $id, 'title' => (string) $node['title'], 'url' => $url, 'note' => $note];
    }
}
