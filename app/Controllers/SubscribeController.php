<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Logger;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use Throwable;

/** ফুটারের "আপনার ইমেইল - আমাদের সাথে সেতুবন্ধন" বাক্স: দর্শকের ইমেইল জমা রাখে। */
final class SubscribeController
{
    /** @param array<string,mixed> $meta */
    public function store(array $meta = []): void
    {
        $back = '/#feedback';
        $ok = Security::verifyCsrf($_POST['_token'] ?? null)
            && trim((string) ($_POST['website'] ?? '')) === ''      // honeypot
            && !RateLimiter::tooMany('subscribe', 5, 3600);

        $email = mb_substr(trim((string) ($_POST['email'] ?? '')), 0, 150);
        if (!$ok || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Session::flash('feedback_errors', [msg('subscribe.msg.bad')]);
            redirect($back);
        }

        $pdo = Database::connection();
        if ($pdo !== null) {
            try {
                $stmt = $pdo->prepare('INSERT IGNORE INTO subscribers (email, ip_hash, created_at) VALUES (:email, :ip, NOW())');
                $stmt->execute([':email' => $email, ':ip' => Security::ipHash()]);
            } catch (Throwable $e) {
                Logger::error('Subscribe failed: ' . $e->getMessage());
            }
        }

        Session::flash('feedback_ok', msg('subscribe.msg.done'));
        redirect($back);
    }
}
