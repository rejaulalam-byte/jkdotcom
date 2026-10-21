<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/**
 * Who looks at hidden phone numbers / e-mails (owner decision 2026-10-21):
 *  - every click on a hidden number or e-mail is COUNTED as a "call" (contact_clicks);
 *  - a visitor who wants to see it first says they are interested and leaves name + mobile (contact_leads).
 * Used for job notices ($type = 'job') and, later, for the workers list ($type = 'worker').
 * The admin panel will show the counts per day/notice and the list of leads.
 */
final class ContactTrackingRepository
{
    private const DEMO_FILE = '/storage/demo-contact-log.json';

    /** One click on a hidden phone/e-mail. $kind = 'phone' | 'email'. */
    public static function click(string $type, int $targetId, string $kind): void
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            self::demoAppend('clicks', ['type' => $type, 'target_id' => $targetId, 'kind' => $kind, 'at' => date('Y-m-d H:i:s')]);

            return;
        }

        try {
            $pdo->prepare('INSERT INTO contact_clicks (target_type, target_id, kind, clicked_at, ip_hash) VALUES (:t, :i, :k, NOW(), :ip)')
                ->execute([':t' => $type, ':i' => $targetId, ':k' => $kind, ':ip' => Security::ipHash()]);
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::click failed: ' . $e->getMessage());
        }
    }

    /** A visitor said "I am interested" and left their name and mobile number. */
    public static function lead(string $type, int $targetId, string $kind, string $name, string $phone): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            return self::demoAppend('leads', [
                'type' => $type, 'target_id' => $targetId, 'kind' => $kind,
                'name' => $name, 'phone' => $phone, 'at' => date('Y-m-d H:i:s'),
            ]);
        }

        try {
            $pdo->prepare(
                'INSERT INTO contact_leads (target_type, target_id, kind, visitor_name, visitor_phone, created_at, ip_hash)
                 VALUES (:t, :i, :k, :n, :p, NOW(), :ip)'
            )->execute([':t' => $type, ':i' => $targetId, ':k' => $kind, ':n' => $name, ':p' => $phone, ':ip' => Security::ipHash()]);

            return true;
        } catch (Throwable $e) {
            Logger::error('ContactTrackingRepository::lead failed: ' . $e->getMessage());

            return false;
        }
    }

    /** @param array<string,mixed> $row */
    private static function demoAppend(string $list, array $row): bool
    {
        $file = BASE_PATH . self::DEMO_FILE;
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        $data = is_array($data) ? $data : [];
        $data[$list][] = $row;
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0700, true);
        }

        return @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX) !== false;
    }
}
