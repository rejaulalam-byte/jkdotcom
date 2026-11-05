<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\AreaRepository;

/**
 * "এলাকার নাম" সাজেশনের তালিকা (JSON): জেলার আইডি => [[নাম, উপজেলার আইডি], ...]।
 * ফর্মের ঘরে ফোকাস করলে app.js একবার নেয়; অতিরিক্ত অনুমোদিত নামও এতে আসে, তাই ক্যাশ ছোট (৫ মিনিট)।
 */
final class AreaController
{
    public function suggestions(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        header('X-Content-Type-Options: nosniff');
        echo json_encode(AreaRepository::suggestions(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
