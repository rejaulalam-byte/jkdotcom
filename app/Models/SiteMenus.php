<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;
use App\Core\PageImage;
use App\Core\Security;

/**
 * সাইটের মেনু: ওপরের প্রধান মেনু (সাব-মেনুসহ), ফুটারের লিংকের কলাম ও সামাজিক মাধ্যমের ঠিকানা।
 * "লাইভ পেইজ এডিট"-এর "মেনু সম্পাদনা" বাক্স থেকে বদলান: ওপরের মেনু শুধু সুপার অ্যাডমিন; ফুটার ও সামাজিক মাধ্যম
 * মেইন অ্যাডমিন ও যে সাব-অ্যাডমিনকে "footer" অনুমতি দেওয়া হয়। বদল থাকে লেখার টেবিলে (Content): "menu.header" ও
 * "menu.footer" (JSON), সামাজিক মাধ্যমের তালিকা "social.list" (JSON; না থাকলে পুরনো "site.social.*" ঠিকানা) ও "settings.social_hide_empty"।
 * বদল না থাকলে সাইটের সঙ্গে আসা মেনু: app/Content/menu.php ও নিচের FOOTER।
 */
final class SiteMenus
{
    public const MAX_TOP = 10;
    public const MAX_SUB = 12;
    public const MAX_COLUMNS = 4;
    public const MAX_LINKS = 12;
    private const MAX_LABEL = 80;

    /** সাইটের সঙ্গে আসা সামাজিক মাধ্যম: কী => নাম (আইকন public/assets/img/icons/<কী>.svg) */
    public const SOCIAL = [
        'facebook' => 'ফেসবুক', 'whatsapp' => 'হোয়াটসঅ্যাপ', 'instagram' => 'ইনস্টাগ্রাম',
        'telegram' => 'টেলিগ্রাম', 'twitter' => 'টুইটার', 'linkedin' => 'লিংকডইন',
    ];

    public const MAX_SOCIAL = 12;

    /**
     * সামাজিক মাধ্যমের তালিকা, সাইটে দেখানোর ক্রমে। icon = '' হলে সাইটের সঙ্গে আসা আইকন, নইলে /page-image/<hex>।
     *
     * @return list<array{key:string,name:string,url:string,icon:string}>
     */
    public static function social(): array
    {
        if (Content::has('social.list')) {
            $saved = json_decode(Content::get('social.list'), true);
            if (is_array($saved)) {
                return array_values(array_filter($saved, 'is_array'));
            }
        }
        $list = [];
        foreach (self::SOCIAL as $key => $name) {
            $list[] = ['key' => $key, 'name' => $name, 'url' => Content::get('site.social.' . $key), 'icon' => ''];
        }

        return $list;
    }

    /** আইকনের ঠিকানা: নিজের তোলা ছবি, নইলে সাইটের সঙ্গে আসা SVG। @param array{key:string,icon:string} $item */
    public static function socialIcon(array $item): string
    {
        return $item['icon'] !== '' ? $item['icon'] : asset('img/icons/' . $item['key'] . '.svg');
    }

    /** সাইটের সঙ্গে আসা ফুটারের দুই কলাম: প্রতিটির প্রথম লিংক কলামের শিরোনাম। */
    private const FOOTER = [
        [['/', 'হোম'], ['/services', 'আমাদের সেবা সমূহ'], ['/registration', 'রেজিস্ট্রেশন'], ['/faq', 'আপনার জিজ্ঞাসা আমাদের উত্তর']],
        [['/about', 'আমাদের সম্পর্কে'], ['/today', 'আজকের প্রকাশিত কাজ'], ['/contact', 'যোগাযোগ'], ['/stats', 'এক নজরে আমাদের পরিসংখ্যান']],
    ];

    /** @return list<array{0:string,1:string,2:list<array{0:string,1:string}>}> [লিংক, নাম, সাব-মেনু] */
    public static function header(): array
    {
        $saved = json_decode(Content::get('menu.header'), true);

        return is_array($saved) && $saved !== [] ? $saved : require BASE_PATH . '/app/Content/menu.php';
    }

    /** @return list<list<array{0:string,1:string}>> কলাম => [লিংক, নাম] (প্রথমটা শিরোনাম) */
    public static function footer(): array
    {
        $saved = json_decode(Content::get('menu.footer'), true);

        return is_array($saved) && $saved !== [] ? $saved : self::FOOTER;
    }

    public static function isChanged(): bool
    {
        return Content::has('menu.header') || Content::has('menu.footer');
    }

    /**
     * ফর্ম থেকে আসা মেনু যাচাই করে সংরক্ষণ করে। খালি নাম-লিংকের সারি বাদ যায়। ফেরত: ভুলের তালিকা (খালি = সব ঠিক)।
     * $withHeader = false (সুপার ছাড়া অন্যরা): ওপরের মেনু যেমন আছে থাকে, ফর্মে পাঠালেও নেওয়া হয় না।
     *
     * @param array<mixed> $post  header[i][label|href|sub][j][label|href], footer[c][j][label|href], social[i][key|name|url|icon], social_hide
     * @param array<mixed> $files $_FILES['social_icon'] (প্রতিটি সারির নতুন আইকন)
     * @return list<string>
     */
    public static function save(array $post, bool $withHeader = true, array $files = []): array
    {
        $errors = [];

        $header = [];
        foreach ($withHeader ? array_slice(self::rows($post['header'] ?? null), 0, self::MAX_TOP) : [] as $i => $row) {
            [$href, $label] = self::link($row, 'ওপরের মেনুর ' . bn($i + 1) . ' নম্বর আইটেম', $errors);
            if ($label === null) {
                continue;
            }
            $sub = [];
            foreach (array_slice(self::rows($row['sub'] ?? null), 0, self::MAX_SUB) as $j => $s) {
                [$sHref, $sLabel] = self::link($s, '"' . $label . '"-এর সাব-মেনুর ' . bn($j + 1) . ' নম্বর', $errors);
                if ($sLabel !== null) {
                    $sub[] = [$sHref, $sLabel];
                }
            }
            $header[] = [$href, $label, $sub];
        }
        if ($withHeader && $header === []) {
            $errors[] = 'ওপরের মেনুতে অন্তত একটি আইটেম রাখুন।';
        }

        $footer = [];
        foreach (array_slice(self::rows($post['footer'] ?? null), 0, self::MAX_COLUMNS) as $c => $column) {
            $links = [];
            foreach (array_slice(self::rows($column), 0, self::MAX_LINKS) as $j => $row) {
                [$href, $label] = self::link($row, 'ফুটারের ' . bn($c + 1) . ' নম্বর কলামের ' . bn($j + 1) . ' নম্বর লিংক', $errors);
                if ($label !== null) {
                    $links[] = [$href, $label];
                }
            }
            if ($links !== []) {
                $footer[] = $links;
            }
        }

        $social = self::parseSocial($post['social'] ?? null, $files, $errors);

        if ($errors !== []) {
            return $errors;
        }
        $old = self::social();
        $ok = (!$withHeader || Content::set('menu.header', (string) json_encode($header, JSON_UNESCAPED_UNICODE)))
            && Content::set('menu.footer', (string) json_encode($footer, JSON_UNESCAPED_UNICODE))
            && Content::set('social.list', (string) json_encode($social, JSON_UNESCAPED_UNICODE))
            && Content::set('settings.social_hide_empty', !empty($post['social_hide']) ? '1' : '0');
        if ($ok) {
            // আর ব্যবহার হচ্ছে না এমন নিজের-তোলা আইকনের ফাইল মোছা
            $kept = array_column($social, 'icon');
            foreach ($old as $item) {
                if ($item['icon'] !== '' && !in_array($item['icon'], $kept, true)) {
                    PageImage::remove($item['icon']);
                }
            }
        }

        return $ok ? [] : ['সংরক্ষণ করা যায়নি।'];
    }

    /**
     * সামাজিক মাধ্যমের সারিগুলো যাচাই করে: নাম লাগবে; ঠিকানা খালি = "#" (লিংক নেই); নতুন সারিতে আইকন লাগবে।
     *
     * @param array<mixed> $files $_FILES['social_icon']
     * @param list<string> $errors
     * @return list<array{key:string,name:string,url:string,icon:string}>
     */
    private static function parseSocial(mixed $rows, array $files, array &$errors): array
    {
        $out = [];
        foreach (array_slice(self::rows($rows), 0, self::MAX_SOCIAL) as $i => $row) {
            $name = clean_input($row['name'] ?? '', 40);
            $url = trim((string) ($row['url'] ?? ''));
            $key = preg_match('/^[a-z0-9_-]{1,30}$/', (string) ($row['key'] ?? '')) === 1 ? (string) $row['key'] : '';
            $icon = preg_match('#^/page-image/[a-f0-9]{32}$#', (string) ($row['icon'] ?? '')) === 1 ? (string) $row['icon'] : '';
            $file = null;
            if (isset($files['error'][$i]) && $files['error'][$i] !== UPLOAD_ERR_NO_FILE) {
                $file = ['name' => $files['name'][$i] ?? '', 'type' => $files['type'][$i] ?? '', 'tmp_name' => $files['tmp_name'][$i] ?? '', 'error' => $files['error'][$i], 'size' => $files['size'][$i] ?? 0];
            }
            if ($name === '' && ($url === '' || $url === '#') && $file === null && $key === '') {
                continue;
            }
            if ($name === '') {
                $errors[] = 'সামাজিক মাধ্যমের ' . bn($i + 1) . ' নম্বর সারি: নাম লিখুন।';
                continue;
            }
            if ($url === '' || $url === '#') {
                $url = '#';
            } elseif (Security::safeUrl($url) === null) {
                $errors[] = $name . '-এর ঠিকানা সঠিক নয় (https:// দিয়ে শুরু পুরো ঠিকানা দিন, বা খালি রাখুন)।';
                continue;
            }
            if ($file !== null) {
                [$stored, $error] = PageImage::store($file);
                if ($stored === null) {
                    $errors[] = $name . '-এর আইকন: ' . $error;
                    continue;
                }
                $icon = $stored;
            }
            if ($key === '') {
                if ($icon === '') {
                    $errors[] = $name . ': নতুন মাধ্যমের জন্য একটা আইকনের ছবি দিন।';
                    continue;
                }
                $key = 'custom-' . bin2hex(random_bytes(3));
            }
            $out[] = ['key' => $key, 'name' => $name, 'url' => $url, 'icon' => $icon];
        }

        return $out;
    }

    /** সাইটের সঙ্গে আসা মেনু ও ফুটারে ফেরা (সামাজিক মাধ্যম যেমন আছে থাকে)। */
    public static function reset(): bool
    {
        return Content::forget('menu.header') && Content::forget('menu.footer');
    }

    /** শুধু ফুটার সাইটের সঙ্গে আসা রূপে ফেরা (ওপরের মেনু যেমন আছে থাকে)। */
    public static function resetFooter(): bool
    {
        return Content::forget('menu.footer');
    }

    public static function isFooterChanged(): bool
    {
        return Content::has('menu.footer');
    }

    /**
     * একটা সারির নাম ও লিংক। দুটোই খালি = বাদ ([.., null])। একটা খালি বা লিংক ঠিক না হলে $errors-এ বার্তা।
     *
     * @param array<mixed> $row
     * @param list<string> $errors
     * @return array{0:string,1:?string}
     */
    private static function link(array $row, string $where, array &$errors): array
    {
        $label = clean_input($row['label'] ?? '', self::MAX_LABEL);
        $href = trim((string) ($row['href'] ?? ''));
        if ($label === '' && $href === '') {
            return ['', null];
        }
        if ($label === '') {
            $errors[] = $where . ': নাম লিখুন।';

            return ['', null];
        }
        if (!self::validHref($href)) {
            $errors[] = $where . ' ("' . $label . '"): লিংক সঠিক নয়। সাইটের ভেতরের পেইজ / দিয়ে শুরু করুন (যেমন /contact), বাইরের সাইট https:// দিয়ে।';

            return ['', null];
        }

        return [$href, $label];
    }

    /** সাইটের ভেতরের পাথ (/...), "#", অথবা সাধারণ http/https ঠিকানা। */
    public static function validHref(string $href): bool
    {
        if ($href === '#') {
            return true;
        }
        if (str_starts_with($href, '/')) {
            return strlen($href) <= 200 && !str_starts_with($href, '//') && preg_match('#^/[A-Za-z0-9/_\-.?=&%\#]*$#', $href) === 1;
        }

        return Security::safeUrl($href) !== null;
    }

    /** @return list<array<mixed>> ফর্মের তালিকা (সংখ্যা-চাবি ক্রমে), অ্যারে না হলে খালি */
    private static function rows(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }
        ksort($value);

        return array_values(array_filter($value, 'is_array'));
    }
}
