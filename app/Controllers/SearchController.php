<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\View;
use App\Models\SiteSearch;
use App\Models\WorkerDirectory;

/** সাইট-জুড়ে খোঁজা: GET /search?q=… (পেইজ, প্রশ্ন-উত্তর, সেবা, এলাকা, পেশাজীবীর নাম/মোবাইল/ঠিকানা)। */
final class SearchController
{
    /** একটা পৃষ্ঠায় কতজন পেশাজীবী দেখানো হবে। */
    private const WORKERS_PER_PAGE = 20;

    public function index(): void
    {
        $query = mb_substr(trim((string) preg_replace('/\s+/u', ' ', (string) ($_GET['q'] ?? ''))), 0, 100);
        // মোবাইল খোঁজার জন্য অন্তত ৩ অঙ্ক; অন্য লেখায় অন্তত ২ অক্ষর
        $digits = normalize_digits($query);
        $isNumber = $query !== '' && preg_match('/^[\d০-৯\s+\-]+$/u', $query) === 1;
        $tooShort = $query !== '' && ($isNumber ? strlen($digits) < 3 : mb_strlen($query) < SiteSearch::MIN_LENGTH);

        $result = null;
        $paged = ['items' => [], 'page' => 1, 'pages' => 1, 'offset' => 0];
        if ($query !== '' && !$tooShort) {
            $result = SiteSearch::run($query);
            $paged = WorkerDirectory::paginate($result['workers'], self::WORKERS_PER_PAGE, (int) ($_GET['p'] ?? 1));
        }

        View::render('pages/search', [
            'bodyClass' => 'page-search',
            'title' => Content::get('search.title') . ' — Jorurikaj.com',
            'heading' => Content::get('search.title'),
            'query' => $query,
            'tooShort' => $tooShort,
            'result' => $result,
            'workers' => $paged['items'],
            'page' => $paged['page'],
            'pages' => $paged['pages'],
        ]);
    }
}
