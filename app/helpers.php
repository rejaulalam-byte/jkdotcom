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
