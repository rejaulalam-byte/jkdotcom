<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;

/** Pages that are designed but not built yet show a friendly "coming soon" page. */
final class PageController
{
    /** @param array<string,mixed> $meta */
    public function soon(array $meta = []): void
    {
        $title = (string) ($meta['title'] ?? 'শীঘ্রই আসছে');
        View::render('pages/coming-soon', ['title' => $title, 'heading' => $title]);
    }
}
