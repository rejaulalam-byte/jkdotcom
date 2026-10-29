<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\SiteTheme;

/**
 * GET /site-theme — অ্যাডমিনের বদলানো থিমের রং (App\Models\SiteTheme), app.css-এর পরে লোড হয়। ঠিকানায় ?v=<সংস্করণ>
 * থাকে, তাই ফাইলটা দীর্ঘ সময় ব্রাউজারে রাখা যায়; রং বদলালে সংস্করণ বদলায় আর ব্রাউজার নতুনটা আনে।
 */
final class ThemeController
{
    public function css(): void
    {
        header('Content-Type: text/css; charset=utf-8');
        $current = ($_GET['v'] ?? '') === SiteTheme::version();
        header('Cache-Control: ' . ($current ? 'public, max-age=31536000, immutable' : 'no-cache'));
        echo "/* jorurikaj.com — অ্যাডমিনের বাছা থিমের রং */\n" . SiteTheme::css();
    }
}
