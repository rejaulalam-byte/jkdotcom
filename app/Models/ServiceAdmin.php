<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\PageImage;
use Throwable;

/**
 * অ্যাডমিনের সেবা-তালিকা (/admin/services): সব সেবা (বন্ধগুলোসহ), নতুন সেবা, নাম বদল, আইকন, চালু/বন্ধ, ক্রম।
 * slug (সাইটের ঠিকানা /services/<slug>) একবার বসলে আর বদলায় না: কর্মীদের পেশা ও পুরনো লিংক এর ওপর দাঁড়িয়ে।
 * সাইটের দিকের পড়া ServiceRepository-তে (শুধু চালু সেবা)। ডাটাবেস না থাকলে (লোকাল প্রিভিউ) storage/demo-services.json
 * (প্রথমবার database/seeds/services.php থেকে বানানো; DemoData::services() সেটাই পড়ে)।
 */
final class ServiceAdmin
{
    public const DEMO_FILE = '/storage/demo-services.json';

    /** সাইটের সঙ্গে আসা আইকনের জায়গা। */
    private const DEFAULT_ICON_DIR = '/assets/img/service-icons/';

    /**
     * সব সেবা, ক্রম অনুযায়ী।
     *
     * @return list<array{id:int,name:string,slug:string,icon_path:?string,icon:?string,sort_order:int,is_active:bool}>
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
        } else {
            try {
                $rows = $pdo->query('SELECT id, name, slug, icon_path, sort_order, is_active FROM services ORDER BY sort_order ASC, id ASC')->fetchAll();
            } catch (Throwable $e) {
                Logger::error('ServiceAdmin::all failed: ' . $e->getMessage());

                return [];
            }
        }
        usort($rows, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

        return array_map(static fn (array $r): array => [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'slug' => (string) ($r['slug'] ?? '') !== '' ? (string) $r['slug'] : (string) $r['id'],
            'icon_path' => $r['icon_path'] !== null ? (string) $r['icon_path'] : null,
            'icon' => ServiceRepository::iconUrl($r['icon_path'] !== null ? (string) $r['icon_path'] : null),
            'sort_order' => (int) $r['sort_order'],
            'is_active' => (bool) $r['is_active'],
        ], $rows);
    }

    /** @return array{id:int,name:string,slug:string,icon_path:?string,icon:?string,sort_order:int,is_active:bool}|null */
    public static function find(int $id): ?array
    {
        foreach (self::all() as $s) {
            if ($s['id'] === $id) {
                return $s;
            }
        }

        return null;
    }

    /**
     * নতুন সেবা (তালিকার শেষে, চালু)। $slug খালি হলে নিজে বানায় (service-<নম্বর>)। ভুল থাকলে বার্তা, নইলে null।
     * সফল হলে $newSlug-এ নতুন সেবার slug।
     */
    public static function add(string $name, string $slug, ?string &$newSlug = null, bool $confirmed = false): ?string
    {
        $name = trim($name);
        $slug = strtolower(trim($slug));
        if ($error = self::checkName($name, null, $confirmed)) {
            return $error;
        }
        $list = self::all();
        if ($slug === '') {
            $n = count($list) + 1;
            do {
                $slug = 'service-' . $n++;
            } while (self::slugTaken($slug, $list));
        } elseif (preg_match('/^[a-z0-9][a-z0-9-]{1,78}[a-z0-9]$/', $slug) !== 1) {
            return 'ঠিকানার নাম শুধু ইংরেজি ছোট অক্ষর, সংখ্যা ও - দিয়ে লিখুন (৩–৮০ অক্ষর), যেমন tiles-mistri।';
        } elseif (self::slugTaken($slug, $list)) {
            return 'এই ঠিকানার নাম আগেই অন্য সেবায় আছে। অন্য নাম দিন।';
        }
        $order = $list === [] ? 1 : max(array_column($list, 'sort_order')) + 1;

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $rows[] = ['id' => (int) max(array_column($rows, 'id') ?: [0]) + 1, 'name' => $name, 'slug' => $slug, 'icon_path' => null, 'sort_order' => $order, 'is_active' => 1];
            if (!self::demoWrite($rows)) {
                return 'সংরক্ষণ করা যায়নি।';
            }
        } else {
            try {
                $pdo->prepare('INSERT INTO services (name, slug, icon_path, sort_order, is_featured, is_active) VALUES (:n, :s, NULL, :o, 0, 1)')
                    ->execute([':n' => $name, ':s' => $slug, ':o' => $order]);
            } catch (Throwable $e) {
                Logger::error('ServiceAdmin::add failed: ' . $e->getMessage());

                return 'সংরক্ষণ করা যায়নি।';
            }
        }
        $newSlug = $slug;

        return null;
    }

    public static function rename(int $id, string $name): ?string
    {
        $name = trim($name);
        if ($error = self::checkName($name, $id, true)) { // নাম বদলে শুধু হুবহু-একই আটকায়, কাছাকাছি নাম নয়
            return $error;
        }

        return self::update($id, ['name' => $name]) ? null : 'সংরক্ষণ করা যায়নি।';
    }

    /** নতুন আইকন (PageImage-এর ঠিকানা) অথবা null = সাইটের সঙ্গে আসা আইকনে ফেরা। আগের দেওয়া ছবি মোছে। */
    public static function setIcon(int $id, ?string $url): bool
    {
        $service = self::find($id);
        if ($service === null) {
            return false;
        }
        $default = self::DEFAULT_ICON_DIR . $service['slug'] . '.svg';
        $path = $url ?? (is_file(BASE_PATH . '/public' . $default) ? $default : null);
        if (!self::update($id, ['icon_path' => $path])) {
            return false;
        }
        if ($service['icon_path'] !== null && str_starts_with($service['icon_path'], '/page-image/')) {
            PageImage::remove($service['icon_path']);
        }

        return true;
    }

    public static function setActive(int $id, bool $active): bool
    {
        return self::update($id, ['is_active' => $active ? 1 : 0]);
    }

    /** ক্রমে এক ধাপ ওপরে ($step = -1) বা নিচে (+1): পাশের সেবার সঙ্গে জায়গা বদল। */
    public static function move(int $id, int $step): bool
    {
        $ids = array_column(self::all(), 'id');
        $pos = array_search($id, $ids, true);
        $to = $pos === false ? -1 : $pos + $step;
        if ($to < 0 || $to >= count($ids)) {
            return true;
        }
        [$ids[$pos], $ids[$to]] = [$ids[$to], $ids[$pos]];
        foreach ($ids as $i => $sid) {
            if (!self::update($sid, ['sort_order' => $i + 1])) {
                return false;
            }
        }

        return true;
    }

    /** একই নাম (ছোট-বড় অক্ষর না দেখে) দুবার চলে না। */
    /**
     * নামের যাচাই: দৈর্ঘ্য; হুবহু একই বা "করা / করার লোক / মিস্ত্রি"-জাতীয় শেষাংশ ও ফাঁক বাদ দিলে একই নাম আটকায় (App\Models\ServiceSimilarity);
     * কাছাকাছি নাম থাকলে সতর্ক করে আটকায়, অ্যাডমিন "তবুও আলাদা সেবা" টিক দিলে ($confirmed) চলে।
     */
    private static function checkName(string $name, ?int $exceptId, bool $confirmed = false): ?string
    {
        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            return 'সেবার নাম লিখুন (২ থেকে ১২০ অক্ষর)।';
        }
        $found = ServiceSimilarity::find($name, self::all(), $exceptId);
        if ($found['same'] !== []) {
            return 'এই সেবা আগেই আছে: "' . implode('", "', $found['same']) . '" (ফাঁক বা "করা / করার লোক / মিস্ত্রি" জাতীয় তফাত ধরা হয় না)। নতুন করে যোগ না করে ওটাই ব্যবহার করুন।';
        }
        if ($found['near'] !== [] && !$confirmed) {
            return 'কাছাকাছি নামের সেবা আগেই আছে: "' . implode('", "', $found['near']) . '"। একই সেবা হলে নতুন করে যোগ করবেন না। সত্যিই আলাদা সেবা হলে "তবুও আলাদা সেবা হিসেবে যোগ করুন" টিক দিয়ে আবার চেপে যোগ করুন।';
        }

        return null;
    }

    /** @param list<array<string,mixed>> $list */
    private static function slugTaken(string $slug, array $list): bool
    {
        return in_array($slug, array_column($list, 'slug'), true);
    }

    /**
     * এক সেবার কয়েকটা ঘর বদলায়। কলামের নাম শুধু এই ফাইলের ভেতর থেকে আসে (ব্যবহারকারীর লেখা নয়)।
     *
     * @param array<string,mixed> $columns
     */
    private static function update(int $id, array $columns): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as &$r) {
                if ((int) $r['id'] === $id) {
                    $r = array_merge($r, $columns);
                }
            }
            unset($r);

            return self::demoWrite($rows);
        }
        try {
            $sets = implode(', ', array_map(static fn (string $c): string => $c . ' = :' . $c, array_keys($columns)));
            $params = [':id' => $id];
            foreach ($columns as $c => $v) {
                $params[':' . $c] = $v;
            }

            return $pdo->prepare('UPDATE services SET ' . $sets . ' WHERE id = :id')->execute($params);
        } catch (Throwable $e) {
            Logger::error('ServiceAdmin::update failed: ' . $e->getMessage());

            return false;
        }
    }

    /**
     * লোকাল প্রিভিউয়ের সেবা-তালিকা: ফাইল না থাকলে সাইটের সঙ্গে আসা ৯৪টি সেবা থেকে বানায়।
     *
     * @return list<array<string,mixed>>
     */
    public static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (is_file($file)) {
            $rows = json_decode((string) file_get_contents($file), true);

            return is_array($rows) ? array_values($rows) : [];
        }
        /** @var list<array{0:int,1:string,2:string}> $seed */
        $seed = require BASE_PATH . '/database/seeds/services.php';

        return array_map(static fn (array $r): array => [
            'id' => $r[0], 'name' => $r[1], 'slug' => $r[2], 'icon_path' => self::DEFAULT_ICON_DIR . $r[2] . '.svg', 'sort_order' => $r[0], 'is_active' => 1,
        ], $seed);
    }

    /** @param list<array<string,mixed>> $rows */
    private static function demoWrite(array $rows): bool
    {
        return @file_put_contents(BASE_PATH . self::DEMO_FILE, json_encode(array_values($rows), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
