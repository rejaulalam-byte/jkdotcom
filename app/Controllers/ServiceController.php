<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ServiceRepository;

/** One service's page: /services/<slug> (list of its providers — to be built; "coming soon" for now). */
final class ServiceController
{
    /** @param array<string,mixed> $meta */
    public function show(array $meta = []): void
    {
        $service = ServiceRepository::findBySlug((string) ($meta['slug'] ?? ''));
        if ($service === null) {
            http_response_code(404);
            View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
            return;
        }

        View::render('pages/coming-soon', [
            'title' => $service['name'],
            'heading' => $service['name'],
            'crumbs' => [['/', 'হোম'], ['/services', 'সেবা সমূহ'], [null, $service['name']]],
        ]);
    }
}
