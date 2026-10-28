<?php

declare(strict_types=1);

namespace App\Models;

/**
 * "লাইভ পেইজ এডিট"-এর ফ্রেমে পেইজের নিচে "এই পেইজের বার্তা" অংশ: যে বার্তাগুলো সাধারণত শুধু কোনো ঘটনার পরে
 * দেখা যায় (ভুল, সফলতা, পপ-আপ, ঘরের নিচের লাল লেখা), সেগুলো এখানে একসঙ্গে তাদের আসল বাক্সের চেহারায় দেখায়,
 * যাতে অ্যাডমিন ক্লিক করে সেখানেই বদলাতে পারেন। কোন পেইজের কোন বার্তা, তা আসে পেইজ-মানচিত্র (PageMap) থেকে।
 */
final class LiveMessages
{
    /** ঠিকানার শুরু => পেইজ-মানচিত্রের id (যে পেইজগুলোর ঠিকানায় নাম/নম্বর বদলায়)। */
    private const PREFIXES = ['/services/' => 'workers', '/workers/' => 'worker', '/login/' => 'login'];

    /** সব ফর্মে একই বার্তা (প্রতিটি পেইজের শেষে ভাঁজ-করা অংশে দেখায়, কারণ ফুটারের ইমেইল-ঘর সব পেইজে আছে)। */
    private const COMMON = 'common';

    /** সবুজ "সফল" বাক্সে দেখানো বার্তা। */
    private const OK = '/^(feedback\.msg\.done|subscribe\.msg\.done|password\.changed|setup\.done|profile\.done|login\.unlocked|worker\.rate\.thanks)$/';

    /** বাক্সের ধরনের নাম (অ্যাডমিন বোঝার জন্য প্রতিটি বার্তার ওপরে ছোট লেবেল)। */
    public const KINDS = [
        'error' => 'ভুল হলে ফর্মের ওপরে (লাল বাক্স)',
        'ok' => 'সফল হলে (সবুজ বাক্স)',
        'card' => 'জমার পরের ধন্যবাদ-কার্ড',
        'field' => 'ঘরের নিচের লাল লেখা',
        'dialog' => 'পপ-আপের লেখা',
        'empty' => 'কিছু না থাকলে দেখানো লেখা',
    ];

    /** ঠিকানা থেকে পেইজ-মানচিত্রের id, না মিললে null। */
    public static function pageFor(string $path): ?string
    {
        $path = '/' . trim($path, '/');
        $match = null;
        $walk = static function (array $nodes) use (&$walk, &$match, $path): void {
            foreach ($nodes as $node) {
                if (($node['url'] ?? null) === $path && $match === null) {
                    $match = (string) $node['id'];
                }
                $walk($node['children'] ?? []);
            }
        };
        $walk(PageMap::tree());
        if ($match !== null) {
            return $match;
        }
        foreach (self::PREFIXES as $prefix => $id) {
            if (str_starts_with($path, $prefix)) {
                return $id;
            }
        }

        return null;
    }

    /**
     * দেখানোর দল: [শিরোনাম, ভাঁজ-করা কি না, [[কী, বাক্সের ধরন], …]]। পেইজের নিজের বার্তা আগে, সব ফর্মে একই পরে।
     *
     * @return list<array{title:string,folded:bool,items:list<array{0:string,1:string}>}>
     */
    public static function groups(?string $pageId): array
    {
        $out = [];
        if ($pageId !== null && $pageId !== self::COMMON) {
            $own = self::messagesOf($pageId);
            if ($own !== []) {
                $out[] = ['title' => 'এই পেইজের বার্তা', 'folded' => false, 'items' => $own];
            }
        }
        $common = self::messagesOf(self::COMMON);
        if ($common !== []) {
            $out[] = ['title' => 'সব ফর্মে একই বার্তা (প্রতিটি পেইজে একই)', 'folded' => true, 'items' => $common];
        }

        return $out;
    }

    /** বার্তাটি কোন বাক্সে দেখায়। */
    public static function kind(string $key): string
    {
        return match (true) {
            str_contains($key, '.done.text') => 'card',
            str_starts_with($key, 'form.msg.') => 'field',
            preg_match(self::OK, $key) === 1 => 'ok',
            str_contains($key, '.lead.') || (str_starts_with($key, 'forgot.') && !str_starts_with($key, 'forgot.msg.')) => 'dialog',
            str_ends_with($key, '.empty') || str_ends_with($key, '.none') => 'empty',
            default => 'error',
        };
    }

    /** @return list<array{0:string,1:string}> */
    private static function messagesOf(string $pageId): array
    {
        $node = PageMap::find($pageId);
        if ($node === null) {
            return [];
        }
        $items = [];
        foreach (PageMap::parts($node) as $part) {
            if ($part['messages']) {
                foreach ($part['keys'] as $key) {
                    $items[] = [$key, self::kind($key)];
                }
            }
        }

        return $items;
    }
}
