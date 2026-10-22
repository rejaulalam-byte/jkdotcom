<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** সেবার বিভাগ (ইলেকট্রিশিয়ান, প্লাম্বার ইত্যাদি)। */
final class ServiceRepository
{
    /** সাইটের সঙ্গে আসা ডিফল্ট আইকন এখানে; অ্যাডমিনের আপলোড করা আইকন /uploads/services/-এ। */
    private const DEFAULT_ICON_DIR = '/assets/img/service-icons/';

    /**
     * প্রতিটি সক্রিয় সেবা, অ্যাডমিনের ক্রমে (হোম পেইজের চলমান সারি)।
     *
     * @return list<array{name:string,slug:string,icon:?string}>
     */
    public static function all(): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return DemoData::services();
        }

        try {
            $rows = $pdo->query(
                'SELECT id, name, slug, icon_path FROM services
                 WHERE is_active = 1
                 ORDER BY sort_order ASC, id ASC'
            )->fetchAll();

            return array_map(static fn (array $r): array => self::row($r), $rows);
        } catch (Throwable $e) {
            Logger::error('ServiceRepository::all failed: ' . $e->getMessage());

            return [];
        }
    }

    /** লেখা পেশার নাম সেবা-তালিকার কোনো নামের সঙ্গে হুবহু মিললে তার slug, নইলে null। */
    public static function slugByName(string $name): ?string
    {
        $needle = mb_strtolower(trim($name));
        foreach (self::all() as $service) {
            if (mb_strtolower($service['name']) === $needle) {
                return $service['slug'];
            }
        }

        return null;
    }

    /** @return array{name:string,slug:string,icon:?string}|null */
    public static function findBySlug(string $slug): ?array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            foreach (DemoData::services() as $service) {
                if ($service['slug'] === $slug) {
                    return $service;
                }
            }

            return null;
        }

        try {
            $stmt = $pdo->prepare('SELECT id, name, slug, icon_path FROM services WHERE slug = :slug AND is_active = 1 LIMIT 1');
            $stmt->execute([':slug' => $slug]);
            $row = $stmt->fetch();

            return $row === false ? null : self::row($row);
        } catch (Throwable $e) {
            Logger::error('ServiceRepository::findBySlug failed: ' . $e->getMessage());

            return null;
        }
    }

    /**
     * services.icon_path-এ রাখা আইকনের ওয়েব ঠিকানা:
     * "/assets/img/service-icons/x.svg" (ডিফল্ট সেট) বা আপলোড-করা ফাইলের নাম (অ্যাডমিন)।
     * ফাইল না থাকলে null দেয়, তখন কার্ডে ফাঁকা বৃত্ত দেখায়।
     */
    public static function iconUrl(?string $iconPath): ?string
    {
        if ($iconPath === null || $iconPath === '') {
            return null;
        }
        if (str_starts_with($iconPath, self::DEFAULT_ICON_DIR)) {
            $file = basename($iconPath);
            if (!preg_match('/^[a-z0-9-]+\.svg$/', $file) || !is_file(BASE_PATH . '/public' . self::DEFAULT_ICON_DIR . $file)) {
                return null;
            }

            return asset('img/service-icons/' . $file);
        }

        return '/uploads/services/' . basename($iconPath);
    }

    /**
     * @param array<string,mixed> $r
     * @return array{name:string,slug:string,icon:?string}
     */
    private static function row(array $r): array
    {
        return [
            'name' => (string) $r['name'],
            // slug-হীন সারি (002_services.sql-এর আগে যোগ হওয়া) নিজের id ব্যবহার করে
            'slug' => (string) ($r['slug'] ?? '') !== '' ? (string) $r['slug'] : (string) $r['id'],
            'icon' => self::iconUrl($r['icon_path'] !== null ? (string) $r['icon_path'] : null),
        ];
    }
}
