<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইট-জুড়ে খোঁজা (/search): পেইজ, প্রশ্ন-উত্তর, সেবা, এলাকা (বিভাগ/জেলা/উপজেলা-থানা) ও পেশাজীবী (নাম, মোবাইল, ঠিকানা)।
 * সব মিল "লেখার অংশ মিললেই" নিয়মে। মোবাইল শুধু অঙ্ক (বাংলা/ইংরেজি) হলে ফোন নাম্বারে খোঁজে — কর্মী-তালিকার খোঁজার মতোই;
 * ফলে ফোন নাম্বার কখনও পুরো দেখানো হয় না (মুখোশ পরানো)।
 */
final class SiteSearch
{
    /** খোঁজার লেখার ন্যূনতম দৈর্ঘ্য (অক্ষর); অঙ্ক হলে WorkerDirectory::filter-এর নিয়ম (৩ অঙ্ক)। */
    public const MIN_LENGTH = 2;

    /** FAQ-র প্রশ্ন-উত্তরের সংখ্যা (faq.1..N.q/.a)। */
    private const FAQ_COUNT = 20;

    /**
     * @return array{
     *   pages:list<array{title:string,url:string,snippet:string}>,
     *   faq:list<array{title:string,url:string,snippet:string}>,
     *   services:list<array{name:string,slug:string}>,
     *   areas:list<array{kind:string,name:string,workers:int,q:string}>,
     *   workers:list<array<string,mixed>>,
     *   total:int
     * }
     */
    public static function run(string $query): array
    {
        $needle = mb_strtolower($query);
        $workers = WorkerDirectory::filter(UserRepository::listApprovedWorkers(), $query);

        $result = [
            'pages' => self::pages($needle),
            'faq' => self::faq($needle),
            'services' => self::services($needle),
            'areas' => self::areas($needle),
            'workers' => $workers,
        ];
        $result['total'] = count($result['pages']) + count($result['faq']) + count($result['services'])
            + count($result['areas']) + count($result['workers']);

        return $result;
    }

    /** @return list<array{title:string,url:string,snippet:string}> */
    private static function pages(string $needle): array
    {
        $out = [];
        $seen = [];
        $add = static function (string $title, string $url, string $body = '') use (&$out, &$seen, $needle): void {
            if ($url === '' || $url === '#' || isset($seen[$url])) {
                return;
            }
            $inTitle = str_contains(mb_strtolower($title), $needle);
            $snippet = $body !== '' ? self::snippet($body, $needle) : '';
            if ($inTitle || $snippet !== '') {
                $seen[$url] = true;
                $out[] = ['title' => $title, 'url' => $url, 'snippet' => $inTitle ? '' : $snippet];
            }
        };

        // মেনুর পেইজ (সাব-মেনুসহ); "সেবা সমূহ" ইত্যাদি
        foreach (SiteMenus::header() as [$href, $label, $children]) {
            $add((string) $label, (string) $href);
            foreach ($children as [$subHref, $subLabel]) {
                $add((string) $subLabel, (string) $subHref);
            }
        }
        $add(Content::get('search.page.faq'), '/faq');
        $add(Content::get('search.page.contact'), '/contact');
        $add(Content::get('search.page.today'), '/today');
        $add(Content::get('search.page.post'), '/today/post');

        // খোলা পেইজ: নাম আর পুরো লেখাতেও খোঁজা
        foreach (OpenPages::ALL as $slug => $info) {
            $add($info['title'], $info['url'], self::plain(OpenPages::raw($slug)));
        }
        foreach (CustomPages::all() as $page) {
            if (!$page['hidden']) {
                $add((string) $page['name'], CustomPages::url($page['slug']), self::plain(OpenPages::raw(CustomPages::OPEN_PREFIX . $page['slug'])));
            }
        }

        return $out;
    }

    /** @return list<array{title:string,url:string,snippet:string}> */
    private static function faq(string $needle): array
    {
        $out = [];
        for ($n = 1; $n <= self::FAQ_COUNT; $n++) {
            $q = Content::get("faq.$n.q");
            $a = Content::get("faq.$n.a");
            if ($q === '') {
                continue;
            }
            $snippet = self::snippet(self::plain($a), $needle);
            if (str_contains(mb_strtolower($q), $needle) || $snippet !== '') {
                $out[] = ['title' => $q, 'url' => '/faq', 'snippet' => $snippet];
            }
        }

        return $out;
    }

    /** @return list<array{name:string,slug:string}> */
    private static function services(string $needle): array
    {
        $out = [];
        foreach (ServiceRepository::all() as $service) {
            if (str_contains(mb_strtolower($service['name']), $needle)) {
                $out[] = ['name' => $service['name'], 'slug' => $service['slug']];
            }
        }

        return $out;
    }

    /**
     * মিলে যাওয়া বিভাগ, জেলা ও উপজেলা/থানা; সঙ্গে ওই এলাকার পেশাজীবীর সংখ্যা।
     *
     * @return list<array{kind:string,name:string,workers:int,q:string}> kind = division|district|upazila, q = খোঁজার লেখা (লিংকে)
     */
    private static function areas(string $needle): array
    {
        $geo = GeoRepository::all();
        $districtName = [];
        foreach ($geo['districts'] as $d) {
            $districtName[$d['id']] = $d['name'];
        }
        // কর্মীদের এলাকা-ভিত্তিক গণনা একবারেই: নাম => সংখ্যা
        $count = ['division' => [], 'district' => [], 'upazila' => []];
        foreach (UserRepository::listApprovedWorkers() as $w) {
            if ($w['division'] !== '') {
                $count['division'][$w['division']] = ($count['division'][$w['division']] ?? 0) + 1;
            }
            if ($w['district'] !== '') {
                $count['district'][$w['district']] = ($count['district'][$w['district']] ?? 0) + 1;
            }
            if ($w['upazila'] !== '') {
                $key = $w['upazila'] . '|' . $w['district'];
                $count['upazila'][$key] = ($count['upazila'][$key] ?? 0) + 1;
            }
        }

        $out = [];
        foreach ($geo['divisions'] as $d) {
            $short = trim((string) preg_replace('/\s*বিভাগ$/u', '', $d['name']));
            if (str_contains(mb_strtolower($d['name']), $needle)) {
                $out[] = ['kind' => 'division', 'name' => $short, 'workers' => $count['division'][$short] ?? 0, 'q' => $short];
            }
        }
        foreach ($geo['districts'] as $d) {
            if (str_contains(mb_strtolower($d['name']), $needle)) {
                $out[] = ['kind' => 'district', 'name' => $d['name'], 'workers' => $count['district'][$d['name']] ?? 0, 'q' => $d['name']];
            }
        }
        foreach ($geo['upazilas'] as $u) {
            $short = (string) ($u['short'] ?? $u['name']);
            if (str_contains(mb_strtolower($short), $needle)) {
                $dist = $districtName[$u['parent']] ?? '';
                $out[] = ['kind' => 'upazila', 'name' => $u['name'], 'workers' => $count['upazila'][$short . '|' . $dist] ?? 0, 'q' => $short];
            }
        }

        return $out;
    }

    /** HTML থেকে সাদামাটা লেখা (ট্যাগ বাদ, ফাঁক একটা করে)। */
    private static function plain(string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['</p>', '</li>', '<br>', '<br/>', '</h2>', '</h3>', '</h4>', '</td>'], ' ', $html)), ENT_QUOTES, 'UTF-8');

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /** লেখায় মিলের চারপাশের একটুখানি অংশ; না মিললে ''। */
    private static function snippet(string $text, string $needle): string
    {
        $pos = mb_stripos($text, $needle);
        if ($pos === false) {
            return '';
        }
        $start = max(0, $pos - 50);
        $part = mb_substr($text, $start, 140);

        return ($start > 0 ? '… ' : '') . $part . (mb_strlen($text) > $start + 140 ? ' …' : '');
    }
}
