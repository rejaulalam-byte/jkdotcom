<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\RatingRepository;
use App\Models\ServiceDirectory;
use App\Models\ServiceRepository;
use App\Models\UserRepository;
use App\Models\WorkerDirectory;
use App\Models\WorkerRotation;

/** সেবার পেইজ: সব সেবার তালিকা (/services) ও একটা সেবার কর্মী-তালিকা (/services/<slug>)। */
final class ServiceController
{
    /** সব সেবা বর্ণানুক্রমে তিনটি সমান কলামে, খোঁজার সুবিধাসহ: /services */
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

        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $perPage = WorkerDirectory::perPage($_GET['n'] ?? null);

        $all = UserRepository::listWorkersByService($service['slug']);
        // ডিফল্ট: ঘূর্ণি ক্রম (কল-পাওয়ারা নিচে); দর্শক চাইলে রেটিং অনুযায়ী (সমান রেটিংয়ে ঘূর্ণি ক্রমই)
        $all = WorkerRotation::ordered($all, $service['slug']);
        $byRating = ($_GET['o'] ?? '') === 'rating';
        if ($byRating) {
            $avg = RatingRepository::averages(array_column($all, 'id'));
            $pos = array_flip(array_column($all, 'id'));
            usort($all, static fn (array $a, array $b): int => [-($avg[$a['id']] ?? 0.0), $pos[$a['id']]] <=> [-($avg[$b['id']] ?? 0.0), $pos[$b['id']]]);
        }
        $found = WorkerDirectory::filter($all, $query);
        $paged = WorkerDirectory::paginate($found, $perPage, (int) ($_GET['p'] ?? 1));

        View::render('pages/workers', [
            'bodyClass' => 'page-workers',
            'title' => $service['name'] . ' — Jorurikaj.com',
            'description' => content('services.intro'),
            'heading' => content('services.title'),
            'service' => $service,
            'query' => $query,
            'perPage' => $perPage,
            'byRating' => $byRating,
            'total' => count($all),
            'found' => count($found),
            'divisions' => WorkerDirectory::divisions($found),
            'workers' => $paged['items'],
            'offset' => $paged['offset'],
            'page' => $paged['page'],
            'pages' => $paged['pages'],
            'ratings' => RatingRepository::averages(array_column($paged['items'], 'id')),
        ]);
    }
}
