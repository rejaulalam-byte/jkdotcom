<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Session;
use App\Core\View;
use App\Models\AdRepository;
use App\Models\JobPostRepository;
use App\Models\ServiceRepository;

final class HomeController
{
    /** @param array<string,mixed> $meta */
    public function index(array $meta = []): void
    {
        $days = max(1, min(30, (int) Content::get('settings.jobs_visible_days', '3')));

        View::render('pages/home', [
            'title' => content('site.title'),
            'description' => content('site.description'),
            'jobs' => JobPostRepository::recent($days),
            'ads' => AdRepository::active(),
            'services' => ServiceRepository::featured(8),
            'feedbackOk' => Session::pull('feedback_ok'),
            'feedbackErrors' => Session::pull('feedback_errors', []),
            'old' => Session::pull('feedback_old', []),
        ]);
    }
}
