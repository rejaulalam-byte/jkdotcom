<?php

declare(strict_types=1);

namespace App\Core;

/**
 * হোমের বিজ্ঞাপন-স্ট্রিপের ছবি: JPG / PNG / WebP। উচ্চতা স্থির (২৭৮px; ধারালো দেখাতে দ্বিগুণ ৫৫৬px-ও চলে), চওড়া স্থির নয়
 * (ছবি যত চওড়া, ব্যানারও তত চওড়া)। ফাইলের আসল ধরন finfo ও getimagesize দিয়ে যাচাই হয়, নামের এক্সটেনশন বিশ্বাস করা হয় না।
 * ছবি ওয়েব-রুটের বাইরে storage/uploads/ads-এ থাকে এবং কন্ট্রোলারের মাধ্যমে (/ad-image/<নাম>) দেখানো হয়।
 * অ্যাডমিন-ফর্মের পাশের নিয়মের লেখা এই ধ্রুবকগুলো থেকেই আসে, তাই লেখা ও যাচাই কখনও আলাদা হয় না।
 */
final class AdImage
{
    /** সাইটে সব বিজ্ঞাপন এই উচ্চতায় (px) দেখায় (CSS --ad-h)। */
    public const HEIGHT = 278;

    /** ধারালো (রেটিনা) পর্দার জন্য দ্বিগুণ উচ্চতার ছবিও চলে। */
    public const HEIGHT_2X = 556;

    /** উচ্চতা ২৭৮px ধরে চওড়ার সীমা (px); ৫৫৬px ছবির বেলায় দ্বিগুণ। */
    public const MIN_WIDTH = 150;
    public const MAX_WIDTH = 1500;

    /** ফাইলের সর্বোচ্চ সাইজ। */
    public const MAX_BYTES = 512000;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * আপলোড-করা বিজ্ঞাপনের ছবি যাচাই করে সংরক্ষণ করে।
     *
     * @param array<string,mixed>|null $file $_FILES-এর একটা এন্ট্রি
     * @return array{0:?string,1:int,2:int,3:?string} [ফাইলের নাম, চওড়া, উচ্চতা (দুটোই ২৭৮px-ভিত্তিক), ত্রুটির বার্তা];
     *         ছবি না দিলে সব null/০
     */
    public static function store(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, 0, 0, null];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return [null, 0, 0, 'ছবির ফাইল সাইজ সর্বোচ্চ ' . bn((int) (self::MAX_BYTES / 1024)) . ' KB হতে পারে।'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return [null, 0, 0, 'ছবিটি আপলোড করা যায়নি। আবার চেষ্টা করুন।'];
        }

        $tmp = (string) $file['tmp_name'];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if (!isset(self::TYPES[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
            return [null, 0, 0, 'ছবির ফরমেট অবশ্যই JPG, PNG বা WebP হতে হবে।'];
        }

        [$width, $height] = [(int) $info[0], (int) $info[1]];
        if ($height !== self::HEIGHT && $height !== self::HEIGHT_2X) {
            return [null, 0, 0, 'ছবির উচ্চতা অবশ্যই ঠিক ' . bn(self::HEIGHT) . ' পিক্সেল (বা ধারালো করতে ' . bn(self::HEIGHT_2X) . ' পিক্সেল) হতে হবে; আপনার ছবির উচ্চতা ' . bn($height) . ' পিক্সেল।'];
        }
        $scale = $height === self::HEIGHT_2X ? 2 : 1;
        if ($width < self::MIN_WIDTH * $scale || $width > self::MAX_WIDTH * $scale) {
            return [null, 0, 0, 'ছবির চওড়া ' . bn(self::MIN_WIDTH * $scale) . ' থেকে ' . bn(self::MAX_WIDTH * $scale) . ' পিক্সেলের মধ্যে হতে হবে; আপনার ছবির চওড়া ' . bn($width) . ' পিক্সেল।'];
        }

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Logger::error('Ad image folder could not be created: ' . $dir);

            return [null, 0, 0, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        if (!@move_uploaded_file($tmp, $dir . '/' . $name)) {
            Logger::error('Ad image could not be written: ' . $dir . '/' . $name);

            return [null, 0, 0, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }

        // স্ট্রিপে প্রকৃত উচ্চতা সবসময় ২৭৮px, তাই মাপ ২৭৮px-ভিত্তিকে এনে রাখা
        return [$name, (int) round($width / $scale), self::HEIGHT, null];
    }

    public static function directory(): string
    {
        return BASE_PATH . '/storage/uploads/ads';
    }

    /** ফাইলের আসল ধরনে MIME। */
    public static function mimeOf(string $name): string
    {
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    /**
     * ছবির ওয়েব ঠিকানা: সাইটের সঙ্গে আসা নমুনা (/assets/img/ads/...) যেমন আছে; আপলোড-করা ফাইল /ad-image/<৩২-অক্ষর> (এক্সটেনশন ছাড়া)।
     */
    public static function url(string $imagePath): string
    {
        if (str_starts_with($imagePath, '/assets/img/ads/')) {
            return '/assets/img/ads/' . basename($imagePath);
        }

        return preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $imagePath) === 1 ? '/ad-image/' . pathinfo($imagePath, PATHINFO_FILENAME) : '';
    }

    /** আপলোড-করা ফাইল মোছে (সাইটের সঙ্গে আসা নমুনায় হাত দেয় না)। */
    public static function remove(string $imagePath): void
    {
        if (preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $imagePath) === 1) {
            @unlink(self::directory() . '/' . $imagePath);
        }
    }
}
