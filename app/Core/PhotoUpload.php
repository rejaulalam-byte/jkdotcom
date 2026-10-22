<?php

declare(strict_types=1);

namespace App\Core;

/**
 * সদস্যের ছবি: JPG/PNG/WebP, সর্বোচ্চ ১৫০ KB। ফাইলের আসল ধরন finfo ও getimagesize দিয়ে যাচাই হয়
 * (নামের এক্সটেনশন বিশ্বাস করা হয় না)। ছবি ওয়েব-রুটের বাইরে storage/uploads/photos-এ থাকে এবং
 * শুধু কন্ট্রোলারের মাধ্যমে (/photo/<নাম>) দেখানো হয়।
 */
final class PhotoUpload
{
    public const MAX_BYTES = 153600;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * আপলোড-করা ছবি যাচাই করে সংরক্ষণ করে।
     *
     * @param array<string,mixed>|null $file $_FILES-এর একটা এন্ট্রি
     * @return array{0:?string,1:?string} [সংরক্ষিত ফাইলের নাম, ত্রুটির বার্তা]; ছবি না দিলে দুটোই null
     */
    public static function store(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, null];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
            return [null, 'ছবির ফাইল সাইজ অবশ্যই 150 KB এর ভিতর থাকতে হবে।'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return [null, 'ছবিটি আপলোড করা যায়নি। আবার চেষ্টা করুন।'];
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return [null, 'ছবির ফাইল সাইজ অবশ্যই 150 KB এর ভিতর থাকতে হবে।'];
        }

        $tmp = (string) $file['tmp_name'];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if (!isset(self::TYPES[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
            return [null, 'ছবির ফরমেট অবশ্যই JPG or PNG or WebP হতে হবে।'];
        }
        if ($info[0] < 100 || $info[1] < 100 || $info[0] > 6000 || $info[1] > 6000) {
            return [null, 'ছবির মাপ ঠিক নয়। কমপক্ষে ১০০×১০০ পিক্সেলের একটি স্পষ্ট ছবি দিন।'];
        }

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Logger::error('Photo folder could not be created: ' . $dir);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }

        $name = bin2hex(random_bytes(16)) . '.' . self::TYPES[$mime];
        $bytes = self::clean($tmp, $mime);
        if ($bytes === null || @file_put_contents($dir . '/' . $name, $bytes) === false) {
            Logger::error('Photo could not be written: ' . $dir . '/' . $name);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }

        return [$name, null];
    }

    public static function directory(): string
    {
        return BASE_PATH . '/storage/uploads/photos';
    }

    /** সংরক্ষিত ছবির ওয়েব ঠিকানা (/photo/<নাম>)। */
    public static function url(?string $name): ?string
    {
        return $name !== null && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) === 1 ? '/photo/' . $name : null;
    }

    /** ফাইলের আসল ধরনে MIME (সার্ভ করার সময়)। */
    public static function mimeOf(string $name): string
    {
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    /**
     * GD থাকলে ছবি আবার এঁকে সংরক্ষণ করে: এতে ভেতরের লুকানো তথ্য (EXIF, GPS, জোড়া কোড) বাদ যায়।
     * GD না থাকলে যাচাই-করা মূল ফাইলই রাখা হয়।
     */
    private static function clean(string $tmp, string $mime): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            $raw = file_get_contents($tmp);

            return $raw === false ? null : $raw;
        }
        $image = @imagecreatefromstring((string) file_get_contents($tmp));
        if ($image === false) {
            return null;
        }
        ob_start();
        match ($mime) {
            'image/png' => (static function () use ($image): void {
                imagealphablending($image, false);
                imagesavealpha($image, true);
                imagepng($image, null, 7);
            })(),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 88),
        };
        imagedestroy($image);
        $out = (string) ob_get_clean();

        return $out === '' ? null : $out;
    }
}
