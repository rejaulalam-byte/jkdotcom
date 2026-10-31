<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Models\CustomPages;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * GET /robots.txt ও GET /sitemap.xml — সার্চ ইঞ্জিনকে বলে কী দেখা যাবে আর সাইটে কোন কোন পেইজ আছে।
 * সূচি সবসময় বর্তমান ডেটা থেকে তৈরি হয় (নতুন সেবা, কর্মী বা পেইজ যোগ হলে নিজে থেকেই আসে)।
 */
final class SeoController
{
    /** সাইটম্যাপের স্থির পেইজ। */
    private const PAGES = [
        '/', '/about', '/services', '/today', '/today/post', '/contact', '/faq', '/privacy', '/terms',
        '/registration', '/registration/professional', '/registration/agent',
        '/registration/professional/form', '/registration/agent/form', '/login', '/login/agent',
    ];

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: public, max-age=3600');
        // সদস্যের ব্যক্তিগত পেইজ ও ফর্মের উত্তর-পথ সূচিতে আসার দরকার নেই; অ্যাডমিনের গোপন দরজার নাম এখানে লেখা হয় না।
        echo "User-agent: *\n"
            . "Disallow: /profile\n"
            . "Disallow: /account/\n"
            . "Disallow: /welcome\n"
            . "Disallow: /password/\n"
            . "Disallow: /search\n"
            . "Disallow: /site-theme\n"
            . "Allow: /\n\n"
            . 'Sitemap: ' . self::base() . "/sitemap.xml\n";
    }

    public function sitemap(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        header('Cache-Control: public, max-age=3600');

        $paths = self::PAGES;
        foreach (ServiceRepository::all() as $service) {
            $paths[] = '/services/' . $service['slug'];
        }
        foreach (CustomPages::all() as $page) {
            if (!$page['hidden']) {
                $paths[] = CustomPages::url($page['slug']);
            }
        }
        foreach (UserRepository::listApprovedWorkers() as $worker) {
            $paths[] = '/workers/' . $worker['id'];
        }

        $base = self::base();
        echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach (array_slice(array_unique($paths), 0, 50000) as $path) {
            echo '<url><loc>' . htmlspecialchars($base . ($path === '/' ? '/' : $path), ENT_XML1) . "</loc></url>\n";
        }
        echo '</urlset>' . "\n";
    }

    private static function base(): string
    {
        return rtrim(Env::get('APP_URL', 'http://localhost:8000') ?? 'http://localhost:8000', '/');
    }
}
