<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * অ্যাডমিনের বানানো নতুন পেইজ (/page/<slug>): দেখতে "আমাদের সম্পর্কে"-র মতো — টিল ব্যানার, পথ-নির্দেশ, নিচে একটাই খোলা
 * লেখার জায়গা (পূর্ণ এডিটর)। তালিকা একটা কী-তে JSON হিসেবে ("pages.custom"), প্রতিটি পেইজের লেখা খোলা পেইজের নিয়মে
 * "page.custom-<slug>.html"-এ (OpenPages দেখায় ও ছাঁকে; লাইভ পেইজ এডিটে ক্লিক করলে পূর্ণ এডিটর খোলে)।
 * ঠিকানার নাম (slug) একবার বসলে বদলায় না, কারণ মেনু বা অন্য পেইজে লিংক থাকতে পারে।
 */
final class CustomPages
{
    private const KEY = 'pages.custom';

    /** OpenPages-এ এই পেইজগুলোর নামের শুরু (সাইটের নিজের খোলা পেইজের সঙ্গে নাম মিলে না যায়)। */
    public const OPEN_PREFIX = 'custom-';

    /** সর্বোচ্চ কতগুলো নতুন পেইজ। */
    public const MAX = 50;

    /** নতুন পেইজের শুরুর লেখা (অ্যাডমিন লাইভ পেইজ এডিটে বদলাবেন)। */
    public const STARTER = '<p>এই পেইজের লেখা এখানে লিখুন। লাইভ পেইজ এডিটে এই লেখায় ক্লিক করলে পূর্ণ এডিটর খুলবে।</p>';

    /**
     * সব নতুন পেইজ, বানানোর ক্রমে।
     *
     * @return list<array{slug:string,name:string,heading:string,description:string,hidden:bool,created:string}>
     */
    public static function all(): array
    {
        $data = json_decode(Content::get(self::KEY), true);
        $out = [];
        foreach (is_array($data) ? $data : [] as $row) {
            if (!is_array($row) || !is_string($row['slug'] ?? null) || preg_match('/^[a-z0-9-]{2,60}$/', $row['slug']) !== 1) {
                continue;
            }
            $out[] = [
                'slug' => $row['slug'],
                'name' => (string) ($row['name'] ?? ''),
                'heading' => (string) ($row['heading'] ?? ''),
                'description' => (string) ($row['description'] ?? ''),
                'hidden' => (bool) ($row['hidden'] ?? true),
                'created' => (string) ($row['created'] ?? ''),
            ];
        }

        return $out;
    }

    /** @return array{slug:string,name:string,heading:string,description:string,hidden:bool,created:string}|null */
    public static function find(string $slug): ?array
    {
        foreach (self::all() as $page) {
            if ($page['slug'] === $slug) {
                return $page;
            }
        }

        return null;
    }

    public static function url(string $slug): string
    {
        return '/page/' . $slug;
    }

    /** OpenPages-এর নাম (যেমন "custom-offer"); নতুন পেইজ নয় এমন নাম হলে null। */
    public static function slugFromOpen(string $open): ?string
    {
        if (!str_starts_with($open, self::OPEN_PREFIX)) {
            return null;
        }
        $slug = substr($open, strlen(self::OPEN_PREFIX));

        return self::find($slug) !== null ? $slug : null;
    }

    /**
     * নতুন পেইজ (লুকানো অবস্থায়, যাতে আগে লেখা হয়ে যায়)। ঠিক থাকলে null এবং $slug-এ ঠিকানার নাম, নইলে বাংলা বার্তা।
     */
    public static function add(string $name, string $heading, string $slug, string $description, ?string &$made = null): ?string
    {
        $all = self::all();
        if (count($all) >= self::MAX) {
            return 'সর্বোচ্চ ' . bn(self::MAX) . 'টি নতুন পেইজ বানানো যায়; আগে কোনোটা মুছুন।';
        }
        $error = self::checkTexts($name, $heading, $description);
        if ($error !== null) {
            return $error;
        }
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            $n = count($all) + 1;
            do {
                $slug = 'page-' . $n++;
            } while (self::find($slug) !== null);
        } elseif (preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug) !== 1 || strlen($slug) < 2 || strlen($slug) > 60) {
            return 'ঠিকানার নাম শুধু ছোট হাতের ইংরেজি অক্ষর, সংখ্যা আর মাঝে "-" দিয়ে, ২ থেকে ৬০ অক্ষরের হতে হবে (যেমন eid-offer)।';
        } elseif (self::find($slug) !== null) {
            return '"' . $slug . '" নামে আগেই একটি পেইজ আছে; অন্য নাম দিন।';
        }

        $all[] = ['slug' => $slug, 'name' => $name, 'heading' => $heading, 'description' => $description, 'hidden' => true, 'created' => date('Y-m-d H:i')];
        if (!self::store($all) || !Content::set('page.' . self::OPEN_PREFIX . $slug . '.html', self::STARTER)) {
            return 'পেইজটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
        }
        $made = $slug;

        return null;
    }

    /** নাম, ব্যানারের শিরোনাম ও বর্ণনা বদল। ঠিক থাকলে null, নইলে বাংলা বার্তা। */
    public static function update(string $slug, string $name, string $heading, string $description): ?string
    {
        $error = self::checkTexts($name, $heading, $description);
        if ($error !== null) {
            return $error;
        }

        return self::change($slug, static fn (array $p): array => ['name' => $name, 'heading' => $heading, 'description' => $description] + $p);
    }

    public static function setHidden(string $slug, bool $hidden): ?string
    {
        return self::change($slug, static fn (array $p): array => ['hidden' => $hidden] + $p);
    }

    /** পেইজ ও তার লেখা চিরতরে মোছে (লেখার ছবিগুলো /page-image-এ থেকে যায়; অন্য পেইজে ব্যবহার হয়ে থাকতে পারে)। */
    public static function delete(string $slug): ?string
    {
        $all = self::all();
        $left = array_values(array_filter($all, static fn (array $p): bool => $p['slug'] !== $slug));
        if (count($left) === count($all)) {
            return 'পেইজটি পাওয়া যায়নি।';
        }
        if (!self::store($left)) {
            return 'মোছা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
        }
        Content::forget('page.' . self::OPEN_PREFIX . $slug . '.html');

        return null;
    }

    private static function checkTexts(string $name, string $heading, string $description): ?string
    {
        if ($name === '' || $heading === '') {
            return 'পেইজের নাম ও ব্যানারের শিরোনাম দুটোই লিখুন।';
        }
        if (mb_strlen($name) > 80 || mb_strlen($heading) > 150 || mb_strlen($description) > 300) {
            return 'লেখা বেশি লম্বা: নাম সর্বোচ্চ ৮০, শিরোনাম ১৫০, বর্ণনা ৩০০ অক্ষর।';
        }

        return null;
    }

    /** @param callable(array<string,mixed>):array<string,mixed> $edit */
    private static function change(string $slug, callable $edit): ?string
    {
        $all = self::all();
        $found = false;
        foreach ($all as &$page) {
            if ($page['slug'] === $slug) {
                $page = $edit($page);
                $found = true;
            }
        }
        unset($page);
        if (!$found) {
            return 'পেইজটি পাওয়া যায়নি।';
        }

        return self::store($all) ? null : 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।';
    }

    /** @param list<array<string,mixed>> $pages */
    private static function store(array $pages): bool
    {
        return Content::set(self::KEY, (string) json_encode(array_values($pages), JSON_UNESCAPED_UNICODE));
    }
}
