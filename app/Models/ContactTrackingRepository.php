<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/**
 * কে লুকানো ফোন নাম্বার / ইমেইল দেখতে চাইছে তার হিসাব:
 *  - লুকানো নাম্বার বা ইমেইলে প্রতিটি ক্লিক একটা "কল" হিসেবে গোনা হয় (contact_clicks);
 *  - যিনি দেখতে চান তিনি আগে আগ্রহের কথা জানিয়ে নাম + মোবাইল দেন (contact_leads)।
 * কাজের বিজ্ঞপ্তির জন্য ব্যবহার হয় ($type = 'job'), পরে কর্মীর তালিকার জন্যও ($type = 'worker')।
 * অ্যাডমিন প্যানেলে দিন/বিজ্ঞপ্তি-ভিত্তিক গণনা ও আগ্রহীদের তালিকা দেখানো হবে।
 */
final class ContactTrackingRepository
{
    private const DEMO_FILE = '/storage/demo-contact-log.json';

    /** লুকানো ফোন/ইমেইলে একটা ক্লিক। $kind = 'phone' | 'email'। */
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

    /** একজন দর্শক "আমি আগ্রহী" বলে নিজের নাম ও মোবাইল নাম্বার দিয়েছেন। */
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
