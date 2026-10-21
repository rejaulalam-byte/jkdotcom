<?php

declare(strict_types=1);

use App\Core\Content;
use App\Core\Router;
use App\Core\Security;

/** Escape text for safe output inside HTML. Use it for EVERY dynamic value. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Latin digits -> Bangla digits (1234 -> ১২৩৪). */
function bn(int|string $number): string
{
    return strtr((string) $number, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ]);
}

/** URL of a file inside public/assets, with a cache-busting version. */
function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return '/assets/' . $path . '?v=' . $version;
}

/** Editable site text: database value if the admin changed it, otherwise the default. */
function content(string $key, string $fallback = ''): string
{
    return Content::get($key, $fallback);
}

/** Escaped text where {braced words} become <strong>bold</strong>. */
function rich(string $text): string
{
    return (string) preg_replace('/\{(.+?)\}/u', '<strong>$1</strong>', e($text));
}

/**
 * Colours the admin may use inside a text with [[color:NAME|words]].
 * NAME => CSS class (defined in app.css). Anything not listed is shown as plain text.
 */
const TEXT_COLORS = [
    'gold'  => 'tc-gold',
    'amber' => 'tc-amber',
    'white' => 'tc-white',
    'red'   => 'tc-red',
];

/**
 * Escaped text with simple, safe markup:
 *   [[color:gold|words]]  -> words in an allowed colour (see TEXT_COLORS)
 *   {words}               -> bold
 * The text is escaped FIRST, so no HTML from the admin can ever get through.
 */
function colored(string $text): string
{
    $html = (string) preg_replace_callback(
        '/\[\[color:([a-z]+)\|(.+?)\]\]/u',
        static function (array $m): string {
            $class = TEXT_COLORS[$m[1]] ?? null;
            return $class === null ? $m[2] : '<span class="' . $class . '">' . $m[2] . '</span>';
        },
        e($text)
    );

    return (string) preg_replace('/\{(.+?)\}/u', '<strong>$1</strong>', $html);
}

/**
 * Social media icons with their addresses from the editable keys "site.social.*".
 * Setting "settings.social_hide_empty" = 1 (switched on only when the owner says so):
 * an icon with no address (empty, "#" or not http/https) is left out of the site.
 * While it is 0 (default) every icon is shown; one without an address links to "#".
 *
 * @return list<array{key:string,label:string,href:string}>
 */
function social_links(): array
{
    $labels = [
        'facebook'  => 'ফেসবুক',
        'whatsapp'  => 'হোয়াটসঅ্যাপ',
        'instagram' => 'ইনস্টাগ্রাম',
        'telegram'  => 'টেলিগ্রাম',
        'twitter'   => 'টুইটার',
        'linkedin'  => 'লিংকডইন',
    ];
    $hideEmpty = content('settings.social_hide_empty', '0') === '1';
    $links = [];
    foreach ($labels as $key => $label) {
        $href = Security::safeUrl(content('site.social.' . $key));
        if ($href === null && $hideEmpty) {
            continue;
        }
        $links[] = ['key' => $key, 'label' => $label, 'href' => $href ?? '#'];
    }

    return $links;
}

/** Hidden CSRF field for every POST form. */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Security::csrfToken()) . '">';
}

/** True when $path is the page being shown (used for the active menu item). */
function is_current(string $path): bool
{
    return Router::currentPath() === $path;
}

/** "tel:" link from a display phone number such as "(+88) 01761441911". */
function tel(string $display): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $display);
}

/** Redirect to an internal path only (never to a URL taken from user input). */
function redirect(string $path, int $status = 303): never
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, $status);
    exit;
}

/** "2026-10-19" -> "১৯ অক্টোবর ২০২৬". */
function bn_date(string $ymd): string
{
    $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $time = strtotime($ymd);
    if ($time === false) {
        return '';
    }

    return bn(date('j', $time)) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . bn(date('Y', $time));
}

/** "2026-10-19 10:55:17" -> "১০:৫৫:১৭ AM বি.এস.টি." (server time, Asia/Dhaka). */
function bn_time(string $datetime): string
{
    $time = strtotime($datetime);
    if ($time === false) {
        return '';
    }

    return bn(date('h:i:s', $time)) . ' ' . date('A', $time) . ' বি.এস.টি.';
}

/** Any typed form of a Bangladeshi mobile number -> the 11 digits "01XXXXXXXXX" (unchanged when it does not fit). */
function normalize_phone(string $phone): string
{
    $digits = strtr($phone, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);
    $digits = (string) preg_replace('/\D/', '', $digits);
    if (strlen($digits) === 13 && str_starts_with($digits, '880')) {
        $digits = '0' . substr($digits, 3);
    } elseif (strlen($digits) === 12 && str_starts_with($digits, '88')) {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 10 && $digits[0] === '1') {
        $digits = '0' . $digits;
    }

    return $digits;
}

/** Hides the middle of a mobile number: "01774123443" -> "+88 01774 *** *43". */
function mask_phone(string $phone): string
{
    $n = normalize_phone($phone);
    if (strlen($n) !== 11) {
        return str_repeat('*', max(0, strlen($n) - 2)) . substr($n, -2);
    }

    return '+88 ' . substr($n, 0, 5) . ' *** *' . substr($n, 9, 2);
}

/** Full number as shown after the visitor asked for it: "01774123443" -> "+88 01774 123 443". */
function format_phone(string $phone): string
{
    $n = normalize_phone($phone);

    return strlen($n) === 11 ? '+88 ' . substr($n, 0, 5) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 3) : $phone;
}

/** "mr.azizsarkar@gmail.com" -> "mr*********@gmail.com". */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at < 1) {
        return '';
    }
    $name = substr($email, 0, $at);

    return substr($name, 0, 2) . str_repeat('*', max(3, strlen($name) - 2)) . substr($email, $at);
}

