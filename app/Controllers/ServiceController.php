<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ServiceDirectory;
use App\Models\ServiceRepository;

/** One service's page: /services/<slug> (list of its providers — to be built; "coming soon" for now). */
final class ServiceController
{
    /** All services A-Z in three balanced columns, with search: /services */
    public function index(): void
    {
        $query = trim((string) ($_GET['q'] ?? ''));
        $query = mb_substr($query, 0, 100);

        $services = ServiceDirectory::filter(ServiceRepository::all(), $query);

        View::render('pages/services', [
            'title' => 'সেবা সমূহ — Jorurikaj.com',
            'description' => content('services.intro'),
            'heading' => content('services.title'),
            'query' => $query,
            'found' => count($services),
            'columns' => ServiceDirectory::columns(ServiceDirectory::group($services)),
        ]);
    }

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
