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

    /** Registration rules overview (/registration): who can register + buttons to the two guidelines. */
    public function registrationRules(): void
    {
        View::render('pages/registration-rules', [
            'bodyClass' => 'page-regrules',
            'title' => 'রেজিস্ট্রেশন নিয়মাবলী — Jorurikaj.com',
            'description' => 'পেশাজীবী, সেবাগ্রহীতা ও প্রতিনিধি—কারা Jorurikaj.com-এ রেজিস্ট্রেশন করতে পারবেন।',
            'heading' => Content::get('reg.rules.title'),
            'bodyHtml' => Content::get('page.registration-rules.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/registration-rules.html')),
        ]);
    }

    /** About page (/about). */
    public function about(): void
    {
        View::render('pages/about', [
            'bodyClass' => 'page-about',
            'title' => 'আমাদের সম্পর্কে — Jorurikaj.com',
            'description' => 'মানুষের প্রয়োজনে, মানুষের পাশে — Jorurikaj.com-এর পরিচয় ও লক্ষ্য।',
            'heading' => 'আমাদের পরিচয়, আমাদের লক্ষ্য',
            'bodyHtml' => Content::get('page.about.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/about.html')),
        ]);
    }
}
