<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
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

    /** About page (/about). */
    public function about(): void
    {
        View::render('pages/about', [
            'title' => 'আমাদের সম্পর্কে — Jorurikaj.com',
            'description' => 'মানুষের প্রয়োজনে, মানুষের পাশে — Jorurikaj.com-এর পরিচয় ও লক্ষ্য।',
            'heading' => 'আমাদের পরিচয়, আমাদের লক্ষ্য',
            'bodyHtml' => Content::get('page.about.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/about.html')),
        ]);
    }
}
