<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\AdImage;
use App\Core\View;

/** অ্যাডমিনের আপলোড-করা বিজ্ঞাপনের ছবি দেখায় (ফাইল ওয়েব-রুটের বাইরে থাকে, তাই এই রুট দিয়ে)। */
final class AdImageController
{
    /** @param array<string,mixed> $meta slug = ৩২-অক্ষরের নাম (এক্সটেনশন ছাড়া) */
    public function show(array $meta = []): void
    {
        $slug = (string) ($meta['slug'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $slug) === 1) {
            foreach (['jpg', 'png', 'webp'] as $ext) {
                $file = AdImage::directory() . '/' . $slug . '.' . $ext;
                if (is_file($file)) {
                    header('Content-Type: ' . AdImage::mimeOf($slug . '.' . $ext));
                    header('Content-Length: ' . (string) filesize($file));
                    header('Cache-Control: public, max-age=86400');
                    header('X-Content-Type-Options: nosniff');
                    readfile($file);

                    return;
                }
            }
        }
        http_response_code(404);
        View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
    }
}
