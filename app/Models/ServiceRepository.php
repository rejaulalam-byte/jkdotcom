<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** Service categories (electrician, plumber ...). */
final class ServiceRepository
{
    /** Default icons shipped with the site live here; admin uploads live in /uploads/services/. */
    private const DEFAULT_ICON_DIR = '/assets/img/service-icons/';

    /**
     * Every active service, in the admin's order (Home page scrolling rows).
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
     * Web address of an icon stored in services.icon_path:
     * "/assets/img/service-icons/x.svg" (default set) or an uploaded file name (admin).
     * Returns null when the file does not exist, so the card shows an empty circle.
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
            // rows without a slug (added before 002_services.sql) use their id
            'slug' => (string) ($r['slug'] ?? '') !== '' ? (string) $r['slug'] : (string) $r['id'],
            'icon' => self::iconUrl($r['icon_path'] !== null ? (string) $r['icon_path'] : null),
        ];
    }
}
