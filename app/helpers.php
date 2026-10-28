<?php

declare(strict_types=1);

use App\Core\Content;
use App\Core\Env;
use App\Core\Router;
use App\Core\Security;

/** HTML-এ নিরাপদে দেখানোর জন্য লেখা escape করে। প্রতিটি পরিবর্তনশীল মানের জন্য এটা ব্যবহার করুন। */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** ইংরেজি অঙ্ক → বাংলা অঙ্ক (1234 → ১২৩৪)। */
function bn(int|string $number): string
{
    return strtr((string) $number, [
        '0' => '০', '1' => '১', '2' => '২', '3' => '৩', '4' => '৪',
        '5' => '৫', '6' => '৬', '7' => '৭', '8' => '৮', '9' => '৯',
    ]);
}

/**
 * অ্যাডমিন প্যানেলের গোপন প্রবেশপথ (যেমন "/self-pakundi")। সার্ভারের .env-এর ADMIN_PATH থেকে আসে, যাতে সাধারণ
 * "/admin" দিয়ে কেউ খুঁজে না পায়। .env-এ না থাকলে বা ভুল ধাঁচের হলে "/admin" (শুধু লোকাল পরীক্ষার জন্য)।
 */
function admin_base(): string
{
    $path = trim((string) Env::get('ADMIN_PATH', ''), " \t/");

    return preg_match('/^[a-z0-9][a-z0-9_-]{2,60}$/i', $path) === 1 ? '/' . $path : '/admin';
}

/** public/assets-এর ভেতরের ফাইলের URL; ফাইল বদলালে ব্রাউজার যেন নতুনটা নেয় সেজন্য ভার্সন-সহ। */
/** অ্যাডমিনের "লাইভ পেইজ এডিট"-এর ফ্রেমে খোলা পেইজ কি না (পপ-আপ বিজ্ঞাপন, দেখার হিসাব ইত্যাদি তখন বাদ)। */
function live_preview(): bool
{
    return \App\Core\Security::isLivePreview();
}

function asset(string $path): string
{
    $path = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $path;
    $version = is_file($file) ? (string) filemtime($file) : '1';

    return '/assets/' . $path . '?v=' . $version;
}

/** সম্পাদনাযোগ্য সাইটের লেখা: অ্যাডমিন বদলালে ডাটাবেসের মান, নইলে ডিফল্ট। লাইভ পেইজ এডিটের ফ্রেমে অদৃশ্য চিহ্নসহ (LiveEdit)। */
function content(string $key, string $fallback = ''): string
{
    $value = Content::get($key, $fallback);

    return live_preview() ? \App\Core\LiveEdit::mark($key, $value) : $value;
}

/** escape-করা লেখা, যেখানে {বন্ধনীর শব্দ} <strong>মোটা</strong> হয়। */
function rich(string $text): string
{
    return (string) preg_replace('/\{(.+?)\}/u', '<strong>$1</strong>', e($text));
}

/**
 * লেখার ভেতরে [[color:NAME|শব্দ]] দিয়ে অ্যাডমিন যে রংগুলো ব্যবহার করতে পারবেন।
 * NAME => CSS ক্লাস (app.css-এ আছে)। তালিকায় নেই এমন কিছু সাধারণ লেখা হিসেবে দেখায়।
 * gold/amber/white/red গাঢ় টিল পটভূমির জন্য; বাকিগুলো সাদা/হালকা পটভূমির জন্য।
 * CSP-তে style="" চলে না, তাই রং শুধু এই তালিকার ক্লাস দিয়ে।
 */
const TEXT_COLORS = [
    'gold'   => 'tc-gold',
    'amber'  => 'tc-amber',
    'white'  => 'tc-white',
    'red'    => 'tc-red',
    'teal'   => 'tc-teal',
    'orange' => 'tc-orange',
    'maroon' => 'tc-maroon',
    'darkred' => 'tc-darkred',
    'brown'  => 'tc-brown',
    'black'  => 'tc-black',
];

/**
 * সহজ ও নিরাপদ মার্কআপসহ escape-করা লেখা:
 *   [[color:gold|শব্দ]]  -> অনুমোদিত রঙে শব্দ (TEXT_COLORS দেখুন)
 *   [[i|শব্দ]]           -> বাঁকা (ইটালিক)
 *   [[u|শব্দ]]           -> নিচে দাগ (আন্ডারলাইন)
 *   {শব্দ}               -> মোটা
 * একটার ভেতরে আরেকটা চলে (ভেতরেরটা আগে বদলায়)। লেখা আগে escape হয়, তাই অ্যাডমিনের দেওয়া কোনো HTML কখনও ঢুকতে পারে না।
 */
function colored(string $text): string
{
    $html = e($text);
    // সবচেয়ে ভেতরের [[...]] আগে: যার ভেতরে আর [[ নেই
    $pattern = '/\[\[(color:[a-z]+|i|u)\|((?:(?!\[\[).)+?)\]\]/u';
    for ($guard = 0; $guard < 20 && preg_match($pattern, $html) === 1; $guard++) {
        $html = (string) preg_replace_callback($pattern, static function (array $m): string {
            if ($m[1] === 'i') {
                return '<em>' . $m[2] . '</em>';
            }
            if ($m[1] === 'u') {
                return '<u>' . $m[2] . '</u>';
            }
            $class = TEXT_COLORS[substr($m[1], 6)] ?? null;

            return $class === null ? $m[2] : '<span class="' . $class . '">' . $m[2] . '</span>';
        }, $html);
    }

    return (string) preg_replace('/\{(.+?)\}/u', '<strong>$1</strong>', $html);
}

/**
 * পেইজে দেখানোর জন্য একটা সাইটের লেখা, বোল্ড/ইটালিক/আন্ডারলাইন/রঙের চিহ্নসহ (colored())।
 * "{name}"-ধাঁচের জায়গা-ধারক থাকা লেখা সাধারণ escape-করা লেখা থাকে (সেখানে {…} মানে নাম/এলাকা, মোটা নয়)।
 * ঘরের বৈশিষ্ট্যে (placeholder="", alt="" …) এটা নয়, e(content()) ব্যবহার করুন।
 */
function t(string $key): string
{
    if (\App\Models\SiteTexts::placeholders($key) !== []) {
        return e(content($key));
    }
    if (live_preview()) {
        \App\Core\LiveEdit::formattable($key);
    }

    return colored(content($key));
}

/**
 * সোশ্যাল মিডিয়ার আইকন ও তাদের ঠিকানা (সম্পাদনাযোগ্য "site.social.*" কী থেকে)।
 * "settings.social_hide_empty" = 1 হলে (মালিক বললেই চালু হবে): ঠিকানাহীন (ফাঁকা, "#"
 * বা http/https নয়) আইকন সাইটে দেখানো হয় না।
 * ০ থাকলে (ডিফল্ট) সব আইকন দেখায়; ঠিকানাহীনটা "#"-এ যায়।
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

/** প্রতিটি POST ফর্মের জন্য লুকানো CSRF ঘর। */
function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(Security::csrfToken()) . '">';
}

/** $path যে পেইজ এখন দেখানো হচ্ছে সেটা হলে true (মেনুর সক্রিয় আইটেমের জন্য)। */
function is_current(string $path): bool
{
    return Router::currentPath() === $path;
}

/** দেখানোর ফোন নাম্বার (যেমন "(+88) 01761441911") থেকে "tel:" লিংক। */
function tel(string $display): string
{
    return 'tel:' . preg_replace('/[^0-9+]/', '', $display);
}

/** শুধু সাইটের ভেতরের পাথে পাঠায় (ব্যবহারকারীর দেওয়া URL-এ কখনও নয়)। */
function redirect(string $path, int $status = 303): never
{
    if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . $path, true, $status);
    exit;
}

/** "2026-10-19" → "১৯ অক্টোবর ২০২৬"। */
function bn_date(string $ymd): string
{
    $months = ['জানুয়ারি', 'ফেব্রুয়ারি', 'মার্চ', 'এপ্রিল', 'মে', 'জুন', 'জুলাই', 'আগস্ট', 'সেপ্টেম্বর', 'অক্টোবর', 'নভেম্বর', 'ডিসেম্বর'];
    $time = strtotime($ymd);
    if ($time === false) {
        return '';
    }

    return bn(date('j', $time)) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . bn(date('Y', $time));
}

/** "2026-10-19 10:55:17" → "১০:৫৫:১৭ AM বি.এস.টি." (সার্ভারের সময়, Asia/Dhaka)। */
function bn_time(string $datetime): string
{
    $time = strtotime($datetime);
    if ($time === false) {
        return '';
    }

    return bn(date('h:i:s', $time)) . ' ' . date('A', $time) . ' বি.এস.টি.';
}

/** বাংলাদেশি মোবাইল নাম্বারের যেকোনো লেখা-রূপ → ১১ সংখ্যা "01XXXXXXXXX" (না মিললে যেমন ছিল তেমনই)। */
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

/** মোবাইল নাম্বারের মাঝখান লুকায়: "01774123443" → "+88 01774 *** *43"। */
function mask_phone(string $phone): string
{
    $n = normalize_phone($phone);
    if (strlen($n) !== 11) {
        return str_repeat('*', max(0, strlen($n) - 2)) . substr($n, -2);
    }

    return '+88 ' . substr($n, 0, 5) . ' *** *' . substr($n, 9, 2);
}

/** দর্শক চাইলে যে পূর্ণ রূপে দেখানো হয়: "01774123443" → "+88 01774 123 443"। */
function format_phone(string $phone): string
{
    $n = normalize_phone($phone);

    return strlen($n) === 11 ? '+88 ' . substr($n, 0, 5) . ' ' . substr($n, 5, 3) . ' ' . substr($n, 8, 3) : $phone;
}

/** "mr.azizsarkar@gmail.com" → "mr*********@gmail.com"। */
function mask_email(string $email): string
{
    $at = strrpos($email, '@');
    if ($at === false || $at < 1) {
        return '';
    }
    $name = substr($email, 0, $at);

    return substr($name, 0, 2) . str_repeat('*', max(3, strlen($name) - 2)) . substr($email, $at);
}

/** বাংলা অঙ্কসহ লেখা থেকে শুধু ইংরেজি অঙ্ক (০-৯ → 0-9), অন্য সব চিহ্ন বাদ (যেমন জাতীয় পরিচয়পত্র নম্বর)। */
function normalize_digits(string $value): string
{
    $value = strtr($value, ['০' => '0', '১' => '1', '২' => '2', '৩' => '3', '৪' => '4', '৫' => '5', '৬' => '6', '৭' => '7', '৮' => '8', '৯' => '9']);

    return (string) preg_replace('/\D/', '', $value);
}


/**
 * ফর্মের <form> ট্যাগে বসানোর data-* অ্যাট্রিবিউট: app.js এই বার্তাগুলো ঘরের নিচে দেখায়
 * (মোবাইল ১১ সংখ্যা না হলে, ইমেইল ভুল হলে ইত্যাদি)। লেখাগুলো defaults.php-র form.msg.*।
 */
function form_msg_attrs(): string
{
    $out = 'data-validate';
    foreach (['required', 'select', 'phone', 'phone_long', 'email', 'nid', 'password', 'match', 'consent'] as $key) {
        $out .= ' data-msg-' . str_replace('_', '-', $key) . '="' . e(content('form.msg.' . $key)) . '"';
    }

    return $out;
}

/**
 * নামের আগে সম্বোধন: পুরুষ → "মি: নাম", মহিলা → "মিস নাম", অন্যান্য বা অজানা → শুধু "নাম"।
 * (নাম দেখে লিঙ্গ আন্দাজ করা হয় না; ব্যবহারকারী নিজে ফর্মে জেন্ডার বাছেন।)
 */
function honorific_name(?string $gender, string $name): string
{
    return match ($gender) {
        'male' => 'মি: ' . $name,
        'female' => 'মিস ' . $name,
        default => $name,
    };
}

/** ফর্মে আসা জেন্ডার মান ঠিক আছে কি না: 'male' | 'female' | 'other', নইলে খালি লেখা। */
function clean_gender(mixed $value): string
{
    return in_array($value, ['male', 'female', 'other'], true) ? (string) $value : '';
}

/** ফর্মের লেখা পরিষ্কার করে: কন্ট্রোল-অক্ষর বাদ, দুই পাশের ফাঁকা বাদ, সর্বোচ্চ $max অক্ষর ($multiline হলে নতুন লাইন থাকে)। */
function clean_input(mixed $value, int $max, bool $multiline = false): string
{
    $value = is_string($value) ? $value : '';
    $value = (string) preg_replace($multiline ? '/[^\P{C}\n]+/u' : '/\p{C}+/u', '', str_replace("\r\n", "\n", $value));

    return mb_substr(trim($value), 0, $max);
}
