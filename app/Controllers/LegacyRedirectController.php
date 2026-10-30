<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * পুরনো সাইটের নাম্বার-ঠিকানা (/2001, /3000, /3001?service_id=N …) নতুন ঠিকানায় স্থায়ী (301) পাঠায়, যাতে গুগলে জমা ও
 * সংরক্ষিত পুরনো লিংক কাজ করে। তালিকা: config/routes.php-এর `legacy_routes()`; প্রতিটি /index/<নাম্বার> রূপেও চলে।
 */
final class LegacyRedirectController
{
    /** পুরনো ঠিকানা => নতুন ঠিকানা। '/3001' আলাদা (service_id দেখে ঠিক হয়)। */
    public const MAP = [
        '/2001' => '/about',
        '/3000' => '/services',
        '/5001' => '/contact',
        '/6002' => '/login',
        '/6003' => '/profile',
        '/6004' => '/registration/professional/form',
        '/6005' => '/registration/agent/form',
        '/7000' => '/today',
    ];

    /** @param array<string,mixed> $meta to = নতুন ঠিকানা */
    public function go(array $meta = []): void
    {
        $this->redirect((string) ($meta['to'] ?? '/'));
    }

    /** পুরনো /3001?service_id=N: N পুরনো সেবা-নাম্বার (সেবার তালিকার `legacy_sl`); চেনা না গেলে সব সেবার পেইজ। */
    public function service(): void
    {
        $id = (int) ($_GET['service_id'] ?? 0);
        $slug = '';
        foreach ((array) require BASE_PATH . '/database/seeds/services.php' as [$sl, , $candidate]) {
            if ((int) $sl === $id) {
                $slug = (string) $candidate;
                break;
            }
        }
        $this->redirect($slug !== '' ? '/services/' . $slug : '/services');
    }

    private function redirect(string $to): void
    {
        header('Location: ' . $to, true, 301);
        header('Cache-Control: public, max-age=86400');
    }
}
