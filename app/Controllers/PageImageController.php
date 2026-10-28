<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\PageImage;
use App\Core\View;

/** খোলা পেইজের লেখার ভেতরের ছবি দেখায় (ফাইল ওয়েব-রুটের বাইরে থাকে, তাই এই রুট দিয়ে)। */
final class PageImageController
{
    private const MIME = ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];

    /** @param array<string,mixed> $meta slug = ৩২-অক্ষরের নাম (এক্সটেনশন ছাড়া) */
    public function show(array $meta = []): void
    {
        $slug = (string) ($meta['slug'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $slug) === 1) {
            foreach (self::MIME as $ext => $mime) {
                $file = PageImage::directory() . '/' . $slug . '.' . $ext;
                if (is_file($file)) {
                    header('Content-Type: ' . $mime);
                    header('Content-Length: ' . (string) filesize($file));
                    header('Cache-Control: public, max-age=86400');
                    header('X-Content-Type-Options: nosniff');
                    readfile($file);

                    return;
                }
            }
        }
        http_response_code(404);
        View::render('pages/error', ['title' => msg('msg.not_found'), 'code' => 404]);
    }
}
