<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\AdImage;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/**
 * হোম hero-র নিচের চলমান স্ট্রিপে দেখানো বিজ্ঞাপন-ব্যানার (সক্রিয় বিজ্ঞাপন না থাকলে বা অ্যাডমিন বন্ধ করলে স্ট্রিপের সাদা বক্স থাকে না)।
 * ডাটাবেস না থাকলে (লোকাল প্রিভিউ) storage/demo-ads.json; প্রথমবার সেখানে সাইটের তিনটি নমুনা বিজ্ঞাপন বসে।
 */
final class AdRepository
{
    private const DEMO_FILE = '/storage/demo-ads.json';

    /** @return list<array{title:string,image:string,width:int,height:int,link:?string}> */
    public static function active(): array
    {
        $ads = [];
        foreach (self::all() as $r) {
            if ((int) $r['is_active'] !== 1 || self::state($r) !== 'live') {
                continue;
            }
            $ads[] = [
                'title' => (string) $r['title'],
                'image' => AdImage::url((string) $r['image_path']),
                'width' => max(1, (int) $r['width']),
                'height' => max(1, (int) $r['height']),
                'link' => Security::safeUrl($r['link_url'] !== null ? (string) $r['link_url'] : null),
            ];
        }

        return array_values(array_filter($ads, static fn (array $a): bool => $a['image'] !== ''));
    }

    /**
     * সব বিজ্ঞাপন (বন্ধ ও মেয়াদ-শেষসহ), দেখানোর ক্রমে: sort_order, তারপর id।
     *
     * @return list<array<string,mixed>> id, title, image_path, width, height, link_url, sort_order, starts_at, ends_at, is_active
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            usort($rows, static fn (array $a, array $b): int => [(int) $a['sort_order'], (int) $a['id']] <=> [(int) $b['sort_order'], (int) $b['id']]);

            return $rows;
        }

        try {
            return $pdo->query(
                'SELECT id, title, image_path, width, height, link_url, sort_order, starts_at, ends_at, is_active
                 FROM advertisements ORDER BY sort_order ASC, id ASC'
            )->fetchAll();
        } catch (Throwable $e) {
            Logger::error('AdRepository::all failed: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * বিজ্ঞাপনের এখনকার অবস্থা (সক্রিয়-চিহ্ন বাদ দিয়ে শুধু সময় ধরে): live | upcoming (শুরু হয়নি) | expired (মেয়াদ শেষ)।
     *
     * @param array<string,mixed> $ad
     */
    public static function state(array $ad): string
    {
        $now = time();
        if ((string) ($ad['starts_at'] ?? '') !== '' && strtotime((string) $ad['starts_at']) > $now) {
            return 'upcoming';
        }
        if ((string) ($ad['ends_at'] ?? '') !== '' && strtotime((string) $ad['ends_at']) < $now) {
            return 'expired';
        }

        return 'live';
    }

    /** @return array<string,mixed>|null */
    public static function find(int $id): ?array
    {
        foreach (self::all() as $r) {
            if ((int) $r['id'] === $id) {
                return $r;
            }
        }

        return null;
    }

    /**
     * নতুন বিজ্ঞাপন; ক্রম = সবার শেষে। নতুন id ফেরত দেয়, না পারলে 0।
     *
     * @param array{title:string,image_path:string,width:int,height:int,link_url:?string,starts_at:?string,ends_at:?string,is_active:int} $data
     */
    public static function create(array $data): int
    {
        $order = 1 + (int) max(array_map(static fn (array $r): int => (int) $r['sort_order'], self::all()) ?: [0]);

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            $id = 1 + (int) max(array_column($rows, 'id') ?: [0]);
            $rows[] = $data + ['id' => $id, 'sort_order' => $order];

            return self::demoWrite($rows) ? $id : 0;
        }

        try {
            $pdo->prepare(
                'INSERT INTO advertisements (title, image_path, width, height, link_url, sort_order, starts_at, ends_at, is_active)
                 VALUES (:title, :image, :w, :h, :link, :sort, :starts, :ends, :active)'
            )->execute([
                ':title' => $data['title'], ':image' => $data['image_path'], ':w' => $data['width'], ':h' => $data['height'],
                ':link' => $data['link_url'], ':sort' => $order, ':starts' => $data['starts_at'], ':ends' => $data['ends_at'], ':active' => $data['is_active'],
            ]);

            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            Logger::error('AdRepository::create failed: ' . $e->getMessage());

            return 0;
        }
    }

    /**
     * বিজ্ঞাপনের কয়েকটা ঘর বদলায়। কলামের নাম শুধু এই ফাইলের তালিকা থেকে নেওয়া হয়।
     *
     * @param array<string,mixed> $columns title, image_path, width, height, link_url, sort_order, starts_at, ends_at, is_active
     */
    public static function update(int $id, array $columns): bool
    {
        $columns = array_intersect_key($columns, array_flip(['title', 'image_path', 'width', 'height', 'link_url', 'sort_order', 'starts_at', 'ends_at', 'is_active']));
        if ($columns === []) {
            return false;
        }

        $pdo = Database::connection();
        if ($pdo === null) {
            $rows = self::demoRead();
            foreach ($rows as $i => $r) {
                if ((int) $r['id'] === $id) {
                    $rows[$i] = array_merge($r, $columns);
                }
            }

            return self::demoWrite($rows);
        }

        try {
            $sets = implode(', ', array_map(static fn (string $c): string => "$c = :$c", array_keys($columns)));

            return $pdo->prepare("UPDATE advertisements SET $sets WHERE id = :id")->execute($columns + ['id' => $id]);
        } catch (Throwable $e) {
            Logger::error('AdRepository::update failed: ' . $e->getMessage());

            return false;
        }
    }

    /** বিজ্ঞাপনের সারি মোছে (ছবির ফাইল মোছা কন্ট্রোলারের কাজ)। সফল হলে true। */
    public static function delete(int $id): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoWrite(array_values(array_filter(self::demoRead(), static fn (array $r): bool => (int) $r['id'] !== $id)));
        }

        try {
            return $pdo->prepare('DELETE FROM advertisements WHERE id = :id')->execute([':id' => $id]);
        } catch (Throwable $e) {
            Logger::error('AdRepository::delete failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @return list<array<string,mixed>> */
    private static function demoRead(): array
    {
        $file = BASE_PATH . self::DEMO_FILE;
        if (!is_file($file)) {
            $rows = [];
            foreach (DemoData::ads() as $i => $ad) {
                $rows[] = [
                    'id' => $i + 1, 'title' => $ad['title'], 'image_path' => $ad['image'], 'width' => $ad['width'], 'height' => $ad['height'],
                    'link_url' => $ad['link'], 'sort_order' => $i + 1, 'starts_at' => null, 'ends_at' => null, 'is_active' => 1,
                ];
            }

            return $rows;
        }
        $rows = json_decode((string) file_get_contents($file), true);

        return is_array($rows) ? array_values($rows) : [];
    }

    /** @param list<array<string,mixed>> $rows */
    private static function demoWrite(array $rows): bool
    {
        return @file_put_contents(BASE_PATH . self::DEMO_FILE, json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
