<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * পুরনো সাইটের নাম্বার-ঠিকানা (/2001, /3000, /3001?service_id=N …) নতুন ঠিকানায় স্থায়ী (301) পাঠায়, যাতে গুগলে জমা ও
 * সংরক্ষিত পুরনো লিংক কাজ করে। তালিকা: MAP ও config/routes.php; প্রতিটি /index/<নাম্বার> রূপেও চলে।
 */
final class LegacyRedirectController
{
    /** পুরনো ঠিকানা => নতুন ঠিকানা (পুরনো সাইটে শেষে / থাকত; রাউটার তা সরিয়ে মেলায়)। '/3001' আলাদা (service_id দেখে ঠিক হয়)। */
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

    /** পুরনো সাইটের service_id = এই সংখ্যা + সেবার নাম্বার (sl): ২০২৩০৩০৩১১০৭৩০ = ১ নম্বর "অনুবাদক", …৭৩৭ = ৮ নম্বর "ইলেকট্রিক মিস্ত্রি"। */
    private const OLD_ID_BASE = 20230303110729;

    /**
     * পুরনো /3001/?service_id=<দীর্ঘ নাম্বার> (পুরনো সাইটের লিংক এভাবেই ছিল), আর ছোট সেবা-নাম্বার হলে সেটাও;
     * সেবা চেনা না গেলে সব সেবার পেইজ।
     */
    public function service(): void
    {
        $raw = (string) ($_GET['service_id'] ?? '');
        $id = ctype_digit($raw) ? (int) $raw : 0;
        if ($id > self::OLD_ID_BASE) {
            $id -= self::OLD_ID_BASE;
        }
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
