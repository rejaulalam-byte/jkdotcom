<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use Throwable;

/** Service categories (electrician, plumber ...). */
final class ServiceRepository
{
    /** @return list<array{name:string,icon:?string}> */
    public static function featured(int $limit = 8): array
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return array_slice(DemoData::services(), 0, $limit);
        }

        try {
            $stmt = $pdo->prepare(
                'SELECT name, icon_path FROM services
                 WHERE is_active = 1 AND is_featured = 1
                 ORDER BY sort_order ASC, id ASC
                 LIMIT :max'
            );
            $stmt->bindValue(':max', $limit, \PDO::PARAM_INT);
            $stmt->execute();

            return array_map(
                static fn (array $r): array => [
                    'name' => (string) $r['name'],
                    'icon' => $r['icon_path'] !== null ? '/uploads/services/' . basename((string) $r['icon_path']) : null,
                ],
                $stmt->fetchAll()
            );
        } catch (Throwable $e) {
            Logger::error('ServiceRepository::featured failed: ' . $e->getMessage());

            return [];
        }
    }
}
