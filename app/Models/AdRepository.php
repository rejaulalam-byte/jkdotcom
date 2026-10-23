<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** হোম hero-র নিচের চলমান স্ট্রিপে দেখানো বিজ্ঞাপন-ব্যানার (সক্রিয় বিজ্ঞাপন না থাকলে বা অ্যাডমিন বন্ধ করলে স্ট্রিপের সাদা বক্স থাকে না)। */
final class AdRepository
{
    /** @return list<array{title:string,image:string,width:int,height:int,link:?string}> */
    public static function active(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return DemoData::ads();
        }

        try {
            $rows = $pdo->query(
                'SELECT title, image_path, width, height, link_url FROM advertisements
                 WHERE is_active = 1
                   AND (starts_at IS NULL OR starts_at <= NOW())
                   AND (ends_at IS NULL OR ends_at >= NOW())
                 ORDER BY sort_order ASC, id ASC'
            )->fetchAll();
        } catch (Throwable $e) {
            Logger::error('AdRepository::active failed: ' . $e->getMessage());

            return [];
        }

        $ads = [];
        foreach ($rows as $r) {
            $ads[] = [
                'title' => (string) $r['title'],
                // '/assets/...' = ছবি সাইটের সঙ্গে আসা (নমুনা বিজ্ঞাপন); নইলে অ্যাডমিনের আপলোড-করা ফাইলের নাম
                'image' => str_starts_with((string) $r['image_path'], '/assets/img/ads/')
                    ? '/assets/img/ads/' . basename((string) $r['image_path'])
                    : '/uploads/ads/' . basename((string) $r['image_path']),
                'width' => max(1, (int) $r['width']),
                'height' => max(1, (int) $r['height']),
                'link' => Security::safeUrl($r['link_url'] !== null ? (string) $r['link_url'] : null),
            ];
        }

        return $ads;
    }
}
