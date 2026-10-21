<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use App\Core\Logger;
use App\Core\Security;
use Throwable;

/** দর্শকের মতামত — ডাটাবেসে রাখা হয়, যাতে অ্যাডমিন পড়তে, রাখতে বা মুছতে পারেন। */
final class FeedbackRepository
{
    /** @param array{name:string,email:string,phone:string,subject:string,message:string} $data */
    public static function save(array $data): bool
    {
        $pdo = Database::connection();
        if ($pdo === null) {
            Logger::error('Feedback (demo mode, not saved to DB): ' . json_encode($data, JSON_UNESCAPED_UNICODE));

            return true;
        }

        try {
            $stmt = $pdo->prepare(
                'INSERT INTO feedback (name, email, phone, subject, message, ip_hash, user_agent, created_at)
                 VALUES (:name, :email, :phone, :subject, :message, :ip_hash, :ua, NOW())'
            );
            $stmt->execute([
                ':name' => $data['name'],
                ':email' => $data['email'],
                ':phone' => $data['phone'],
                ':subject' => $data['subject'],
                ':message' => $data['message'],
                ':ip_hash' => Security::ipHash(),
                ':ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            return true;
        } catch (Throwable $e) {
            Logger::error('FeedbackRepository::save failed: ' . $e->getMessage());

            return false;
        }
    }
}
