<?php

declare(strict_types=1);

namespace App\Core;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * অ্যাডমিন-এডিটরের (TinyMCE) লেখা HTML-এর সুরক্ষা-ছাঁকনি (allow-list)।
 * শুধু তালিকায় থাকা ট্যাগ, অ্যাট্রিবিউট, ক্লাস ও CSS-এর ঘর (মান যাচাই করে) থাকে; স্ক্রিপ্ট, ইভেন্ট (onclick...),
 * javascript: লিংক, বাইরের ছবি ইত্যাদি সব বাদ। আউটপুট নতুন করে লেখা হয় (ইনপুট ঘষে-মেজে নয়), তাই যা তালিকায় নেই তা
 * কোনোভাবেই বের হতে পারে না। সংরক্ষণের সময় এবং দেখানোর সময়, দুবারই এটা চলে।
 *
 * CSP: সাইটে `style="..."` সাধারণভাবে বন্ধ। show() যে style-মানগুলো বের করে সেগুলোর হ্যাশ জমা রাখে; View সেই
 * হ্যাশগুলোকেই CSP-তে অনুমতি দেয় ('unsafe-hashes'), ফলে শুধু এই ছাঁকনি-পেরোনো স্টাইলই চলে।
 */
final class RichText
{
    /** ট্যাগ => অনুমোদিত অ্যাট্রিবিউট (style ও class আলাদা যাচাই হয়)। */
    private const TAGS = [
        'p' => ['style', 'class'], 'h2' => ['style', 'class'], 'h3' => ['style', 'class'], 'h4' => ['style', 'class'],
        'br' => [], 'hr' => [], 'strong' => [], 'em' => [], 'u' => [], 's' => [], 'sub' => [], 'sup' => [],
        'span' => ['style'], 'blockquote' => ['style'],
        'ul' => ['style'], 'ol' => ['style'], 'li' => ['style'],
        'a' => ['href', 'target'],
        'img' => ['src', 'alt', 'class', 'style', 'width', 'height'],
        'table' => ['style'], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => ['style'],
        'th' => ['style', 'colspan', 'rowspan'], 'td' => ['style', 'colspan', 'rowspan'],
    ];

    /** এই ট্যাগগুলো একই অর্থের অনুমোদিত ট্যাগে বদলায়। */
    private const RENAME = ['b' => 'strong', 'i' => 'em', 'strike' => 's', 'del' => 's', 'h1' => 'h2', 'h5' => 'h4', 'h6' => 'h4'];

    /** এগুলো ভেতরের লেখাসহ পুরো বাদ। (বাকি অচেনা ট্যাগের শুধু মোড়ক বাদ যায়, ভেতরের লেখা থাকে।) */
    private const DROP = ['script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'textarea', 'select', 'option', 'svg', 'math', 'noscript', 'template', 'link', 'meta', 'head', 'title', 'base', 'video', 'audio', 'canvas'];

    /** অনুমোদিত ক্লাস (পুরনো লেখার ক্লাস + এডিটরের ছবি-বসানোর ক্লাস)। */
    private const CLASSES = ['ta-center', 'ta-right', 'ta-justify', 'legal-meta', 'indent',
        'img-left', 'img-right', 'img-center', 'img-left-sm', 'img-right-sm', 'img-shape', 'img-down-10'];

    /** ট্যাগ => অনুমোদিত CSS-ঘর। */
    private const STYLES = [
        'p' => ['text-align', 'margin-left', 'line-height', 'margin-top', 'margin-bottom'],
        'h2' => ['text-align', 'margin-left', 'line-height', 'margin-top', 'margin-bottom'],
        'h3' => ['text-align', 'margin-left', 'line-height', 'margin-top', 'margin-bottom'],
        'h4' => ['text-align', 'margin-left', 'line-height', 'margin-top', 'margin-bottom'],
        'li' => ['text-align', 'line-height'],
        'blockquote' => ['margin-left'],
        'ul' => ['list-style-type', 'margin-left'], 'ol' => ['list-style-type', 'margin-left'],
        'span' => ['color', 'background-color', 'font-family', 'font-size', 'font-weight', 'line-height'],
        'img' => ['width', 'height'],
        'table' => ['width', 'margin-left', 'margin-right', 'border', 'border-width', 'border-style', 'border-color'],
        'tr' => ['background-color', 'height'],
        'th' => ['width', 'height', 'vertical-align', 'text-align', 'background-color', 'border', 'border-width', 'border-style', 'border-color',
            'border-top', 'border-right', 'border-bottom', 'border-left'],
        'td' => ['width', 'height', 'vertical-align', 'text-align', 'background-color', 'border', 'border-width', 'border-style', 'border-color',
            'border-top', 'border-right', 'border-bottom', 'border-left'],
    ];

    /** এডিটরের ফন্ট-তালিকা (সাইটে শুধু এই দুটো ফন্ট আছে) => বের হওয়ার রূপ। */
    public const FONTS = ['hind siliguri' => 'Hind Siliguri, sans-serif', 'poppins' => 'Poppins, sans-serif'];

    /** ছবির ঠিকানা: শুধু নিজের সাইটের ছবি (এডিটরে আপলোড-করা বা সাইটের সঙ্গে আসা)। */
    private const IMG_SRC = '#^(/page-image/[a-f0-9]{32}|/assets/img/[a-z0-9/_.-]{1,120}\.(png|jpe?g|webp|svg))$#';

    private const COLOR = '/^(#[0-9a-f]{3,8}|rgba?\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*(,\s*(0|1|0?\.\d{1,3}))?\s*\)|transparent)$/i';

    /** @var array<string,true> show() যে style-মানগুলো বের করেছে (CSP-র জন্য) */
    private static array $styles = [];

    /** HTML ছেঁকে নিরাপদ HTML ফেরত দেয়। */
    public static function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $doc->getElementsByTagName('body')->item(0);

        return $body === null ? '' : trim(self::children($body));
    }

    /** পাবলিক পেইজে দেখানোর জন্য: ছেঁকে নেয় এবং বের-হওয়া style-মান CSP-র জন্য মনে রাখে। */
    public static function show(string $html): string
    {
        $clean = self::sanitize($html);
        if (preg_match_all('/ style="([^"]*)"/', $clean, $m) > 0) {
            foreach ($m[1] as $value) {
                self::$styles[$value] = true;
            }
        }

        return $clean;
    }

    /** @return list<string> show() যে style-মানগুলো বের করেছে, প্রতিটির CSP হ্যাশ ('sha256-...') */
    public static function styleHashes(): array
    {
        return array_map(static fn (string $v): string => "'sha256-" . base64_encode(hash('sha256', $v, true)) . "'", array_keys(self::$styles));
    }

    private static function children(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= self::node($child);
        }

        return $out;
    }

    private static function node(DOMNode $node): string
    {
        if ($node->nodeType === XML_TEXT_NODE || $node->nodeType === XML_CDATA_SECTION_NODE) {
            return htmlspecialchars((string) $node->nodeValue, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
        if (!$node instanceof DOMElement) {
            return ''; // মন্তব্য ইত্যাদি
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, self::DROP, true)) {
            return '';
        }
        $tag = self::RENAME[$tag] ?? $tag;
        if (!isset(self::TAGS[$tag])) {
            return self::children($node); // অচেনা মোড়ক বাদ, ভেতরের লেখা থাকে
        }

        $attrs = self::attributes($node, $tag);
        if ($attrs === null) {
            return ''; // যেমন অনুমোদনহীন ঠিকানার ছবি
        }
        if ($tag === 'br' || $tag === 'hr' || $tag === 'img') {
            return '<' . $tag . $attrs . '>';
        }

        return '<' . $tag . $attrs . '>' . self::children($node) . '</' . $tag . '>';
    }

    /** অনুমোদিত অ্যাট্রিবিউটগুলো লেখা আকারে; ছবির ঠিকানা অনুমোদনহীন হলে null (পুরো ছবি বাদ)। */
    private static function attributes(DOMElement $el, string $tag): ?string
    {
        $out = [];
        $classes = [];
        foreach (self::TAGS[$tag] as $name) {
            if (!$el->hasAttribute($name)) {
                continue;
            }
            $value = trim($el->getAttribute($name));
            switch ($name) {
                case 'class':
                    $classes = array_values(array_intersect(preg_split('/\s+/', strtolower($value)) ?: [], self::CLASSES));
                    break;
                case 'href':
                    $href = self::href($value);
                    if ($href !== null) {
                        $out['href'] = $href;
                    }
                    break;
                case 'target':
                    if ($value === '_blank') {
                        $out['target'] = '_blank';
                        $out['rel'] = 'noopener noreferrer';
                    }
                    break;
                case 'src':
                    if (preg_match(self::IMG_SRC, $value) !== 1) {
                        return null;
                    }
                    $out['src'] = $value;
                    break;
                case 'alt':
                    $out['alt'] = mb_substr($value, 0, 200);
                    break;
                case 'width':
                case 'height':
                case 'colspan':
                case 'rowspan':
                    if (preg_match('/^\d{1,4}$/', $value) === 1 && (int) $value > 0) {
                        $out[$name] = $value;
                    }
                    break;
            }
        }
        if ($tag === 'img' && !isset($out['src'])) {
            return null;
        }
        if ($tag === 'img' && !isset($out['alt'])) {
            $out['alt'] = '';
        }
        if ($classes !== []) {
            $out['class'] = implode(' ', $classes);
        }

        $style = in_array('style', self::TAGS[$tag], true) ? self::style($tag, $el->getAttribute('style')) : [];
        // PNG-এর কিনারা ঘেঁষে লেখা: ভাসমান ছবিতে shape-outside = ছবিরই ঠিকানা (ঠিকানা ওপরে যাচাই-করা)
        if ($tag === 'img' && in_array('img-shape', $classes, true) && (in_array('img-left', $classes, true) || in_array('img-right', $classes, true))) {
            $style[] = 'shape-outside: url(' . $out['src'] . ')';
            $style[] = 'shape-margin: 12px';
        }
        if ($style !== []) {
            $out['style'] = implode('; ', $style);
        }

        $text = '';
        foreach ($out as $name => $value) {
            $text .= ' ' . $name . '="' . htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"';
        }

        return $text;
    }

    /** লিংকের ঠিকানা: http(s), mailto, tel অথবা সাইটের ভেতরের পথ (/...); বাকি সব (javascript: ইত্যাদি) বাদ। */
    private static function href(string $value): ?string
    {
        if ($value === '' || strlen($value) > 500 || preg_match('/[\x00-\x20<>"\'\\\\`]/', $value) === 1) {
            return null;
        }
        if (preg_match('#^/(?!/)#', $value) === 1 || preg_match('/^(mailto|tel):[^\s]+$/i', $value) === 1 || preg_match('/^#[a-z0-9_-]{1,60}$/i', $value) === 1) {
            return $value;
        }

        return Security::safeUrl($value);
    }

    /**
     * style-এর ঘরগুলো যাচাই করে অনুমোদিতগুলো "ঘর: মান" রূপে ফেরত দেয়।
     *
     * @return list<string>
     */
    private static function style(string $tag, string $style): array
    {
        $allowed = self::STYLES[$tag] ?? [];
        $out = [];
        foreach (explode(';', $style) as $decl) {
            if (!str_contains($decl, ':')) {
                continue;
            }
            [$prop, $value] = array_map('trim', explode(':', $decl, 2));
            $prop = strtolower($prop);
            if ($prop === 'font-family') {
                $value = str_replace(["'", '"'], '', $value); // ফন্টের নামের উদ্ধৃতি-চিহ্ন
            }
            $value = preg_replace('/\s+/', ' ', $value) ?? '';
            if (!in_array($prop, $allowed, true) || $value === '' || preg_match('/[\\\\<>"\'`{}]|\/\*|expression|url\s*\(/i', $value) === 1) {
                continue;
            }
            $clean = self::value($prop, $value);
            if ($clean !== null) {
                $out[$prop] = $prop . ': ' . $clean;
            }
        }

        return array_values($out);
    }

    /** একটা CSS-ঘরের মান যাচাই; ঠিক থাকলে স্বাভাবিক রূপ, নইলে null। */
    private static function value(string $prop, string $value): ?string
    {
        $v = strtolower($value);
        $px = static fn (string $s, int $min, int $max): bool => preg_match('/^(\d{1,4})(\.\d{1,2})?px$/', $s, $m) === 1 && (int) $m[1] >= $min && (int) $m[1] <= $max;
        $pct = static fn (string $s): bool => preg_match('/^(\d{1,3})(\.\d{1,4})?%$/', $s, $m) === 1 && (float) $s > 0 && (float) $s <= 100;

        switch ($prop) {
            case 'text-align':
                return in_array($v, ['left', 'center', 'right', 'justify'], true) ? $v : null;
            case 'vertical-align':
                return in_array($v, ['top', 'middle', 'bottom'], true) ? $v : null;
            case 'list-style-type':
                return in_array($v, ['disc', 'circle', 'square', 'decimal', 'lower-alpha', 'upper-alpha', 'lower-roman', 'upper-roman', 'lower-greek', 'none'], true) ? $v : null;
            case 'color':
            case 'background-color':
            case 'border-color':
                return preg_match(self::COLOR, $v) === 1 ? $v : null;
            case 'font-family':
                $first = strtolower(trim(explode(',', $v)[0], " \"'"));

                return self::FONTS[$first] ?? null;
            case 'font-size':
                return $px($v, 10, 70) ? $v : null;
            case 'font-weight':
                return in_array($v, ['300', '400', '500', '600', '700', '800', 'normal', 'bold'], true) ? $v : null;
            case 'line-height':
                return preg_match('/^[0-3](\.\d{1,2})?$/', $v) === 1 && (float) $v >= 1 && (float) $v <= 3 ? $v : null;
            case 'margin-left':
            case 'margin-right':
                return $v === 'auto' || $v === '0' || $px($v, 0, 600) ? $v : null;
            case 'margin-top':
            case 'margin-bottom':
                return $v === '0' || $px($v, 0, 200) ? $v : null;
            case 'width':
                return $v === 'auto' || $px($v, 10, 2000) || $pct($v) ? $v : null;
            case 'height':
                return $v === 'auto' || $px($v, 10, 2000) ? $v : null;
            case 'border-width':
                return $v === '0' || $px($v, 0, 10) ? $v : null;
            case 'border-style':
                return in_array($v, ['none', 'solid', 'dashed', 'dotted', 'double'], true) ? $v : null;
            case 'border':
            case 'border-top':
            case 'border-right':
            case 'border-bottom':
            case 'border-left':
                return self::border($v);
        }

        return null;
    }

    /** "1px solid #ccc" ধাঁচের border; প্রতিটা অংশ আলাদা যাচাই। */
    private static function border(string $v): ?string
    {
        if ($v === 'none' || $v === '0') {
            return $v;
        }
        $parts = [];
        // rgb(...)-এর ভেতরের ফাঁকা ভাঙতে না দিয়ে অংশ ভাগ
        if (preg_match_all('/rgba?\([^)]*\)|\S+/', $v, $m) < 1 || count($m[0]) > 3) {
            return null;
        }
        foreach ($m[0] as $part) {
            if (preg_match('/^(\d{1,2})(\.\d{1,2})?px$/', $part) === 1 && (float) $part <= 10) {
                $parts[] = $part;
            } elseif (in_array($part, ['none', 'solid', 'dashed', 'dotted', 'double'], true)) {
                $parts[] = $part;
            } elseif (preg_match(self::COLOR, $part) === 1) {
                $parts[] = $part;
            } else {
                return null;
            }
        }

        return implode(' ', $parts);
    }
}
