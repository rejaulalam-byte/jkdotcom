<?php

declare(strict_types=1);

namespace App\Core;

/**
 * সদস্যের ছবি: JPG/PNG/WebP। ফাইলের আসল ধরন finfo ও getimagesize দিয়ে যাচাই হয় (নামের এক্সটেনশন বিশ্বাস করা
 * হয় না)। ছবি ওয়েব-রুটের বাইরে storage/uploads/photos-এ থাকে এবং শুধু কন্ট্রোলারের মাধ্যমে (/photo/<নাম>) দেখানো হয়।
 *
 * মোবাইলের ছবি কয়েক MB হয়, তাই ব্রাউজারেই (app.js) ছবি ছোট করে পাঠানো হয়। কিন্তু JavaScript ছাড়াও
 * বড় ছবি এলে সার্ভারে (GD থাকলে) একই কাজ হয়: মান যতটা সম্ভব ধরে রেখে MAX_BYTES-এর ভিতরে আনা হয়।
 */
final class PhotoUpload
{
    /** সংরক্ষিত ছবির সর্বোচ্চ সাইজ (১৫০ KB) */
    public const MAX_BYTES = 153600;

    /** সার্ভারে নেওয়া মূল ছবির সর্বোচ্চ সাইজ (এর বেশি হলে ছোট করার আগেই বাতিল) */
    private const RAW_MAX_BYTES = 12582912;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /** বড় ছবি ছোট করার ধাপ: লম্বা দিকের মাপ (px) ও গুণমান; আগে মাপ ধরে রেখে গুণমান কমানো হয়, তারপর মাপ */
    private const SIDES = [1600, 1280, 1024, 800, 640, 480];
    private const QUALITIES = [88, 80, 72, 64, 56];

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
            return [null, 'ছবির ফাইলটি অনেক বড়। কম সাইজের ছবি দিন।'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return [null, 'ছবিটি আপলোড করা যায়নি। আবার চেষ্টা করুন।'];
        }
        if ((int) $file['size'] > self::RAW_MAX_BYTES) {
            return [null, 'ছবির ফাইলটি অনেক বড়। কম সাইজের ছবি দিন।'];
        }

        $tmp = (string) $file['tmp_name'];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if (!isset(self::TYPES[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
            return [null, 'ছবির ফরমেট অবশ্যই JPG or PNG or WebP হতে হবে।'];
        }
        if ($info[0] < 100 || $info[1] < 100 || $info[0] > 12000 || $info[1] > 12000) {
            return [null, 'ছবির মাপ ঠিক নয়। কমপক্ষে ১০০×১০০ পিক্সেলের একটি স্পষ্ট ছবি দিন।'];
        }

        $raw = file_get_contents($tmp);
        if ($raw === false) {
            return [null, 'ছবিটি আপলোড করা যায়নি। আবার চেষ্টা করুন।'];
        }
        [$bytes, $ext] = self::prepare($raw, $mime);
        if ($bytes === null) {
            return [null, 'ছবির ফাইল সাইজ অবশ্যই 150 KB এর ভিতর থাকতে হবে।'];
        }

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Logger::error('Photo folder could not be created: ' . $dir);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }
        $name = bin2hex(random_bytes(16)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bytes) === false) {
            Logger::error('Photo could not be written: ' . $dir . '/' . $name);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।'];
        }

        return [$name, null];
    }

    public static function directory(): string
    {
        return BASE_PATH . '/storage/uploads/photos';
    }

    /** সংরক্ষিত ছবির ওয়েব ঠিকানা (/photo/<৩২-অক্ষরের নাম>, এক্সটেনশন ছাড়া: রুট নিজে ফাইলের ধরন খোঁজে)। */
    public static function url(?string $name): ?string
    {
        return $name !== null && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) === 1 ? '/photo/' . pathinfo($name, PATHINFO_FILENAME) : null;
    }

    /** ফাইলের আসল ধরনে MIME (সার্ভ করার সময়)। */
    public static function mimeOf(string $name): string
    {
        return ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][pathinfo($name, PATHINFO_EXTENSION)] ?? 'application/octet-stream';
    }

    /**
     * সংরক্ষণের বাইটস ও এক্সটেনশন ঠিক করে।
     *  - GD নেই: ছবি MAX_BYTES-এর ভিতরে থাকলে যেমন আছে তেমন, নইলে বাতিল (null)।
     *  - GD আছে: ছবি আবার এঁকে সংরক্ষণ করা হয় (ভেতরের লুকানো তথ্য, যেমন EXIF/GPS, বাদ যায়; মোবাইলের
     *    ঘোরানো ছবি সোজা হয়); MAX_BYTES-এর বেশি হলে মাপ ও গুণমান ধাপে ধাপে কমিয়ে ভিতরে আনা হয়।
     *
     * @return array{0:?string,1:string} [বাইটস বা null, এক্সটেনশন]
     */
    private static function prepare(string $raw, string $mime): array
    {
        if (!function_exists('imagecreatefromstring')) {
            return [strlen($raw) <= self::MAX_BYTES ? $raw : null, self::TYPES[$mime]];
        }
        $image = @imagecreatefromstring($raw);
        if ($image === false) {
            return [null, self::TYPES[$mime]];
        }
        $image = self::upright($image, $raw, $mime);

        // ছোট ছবি আগের ধরনেই; বড় হলে WebP (থাকলে) বা JPEG, কারণ ছবির জন্য এরা সবচেয়ে ছোট হয়
        if (strlen($raw) <= self::MAX_BYTES) {
            $out = self::encode($image, $mime, 88);
            if ($out !== '' && strlen($out) <= self::MAX_BYTES) {
                return [$out, self::TYPES[$mime]];
            }
        }
        $target = function_exists('imagewebp') ? 'image/webp' : 'image/jpeg';
        $w = imagesx($image);
        $h = imagesy($image);
        foreach (self::SIDES as $side) {
            $scale = min(1.0, $side / max($w, $h));
            $scaled = $scale < 1.0 ? imagescale($image, max(1, (int) round($w * $scale)), max(1, (int) round($h * $scale))) : $image;
            if ($scaled === false) {
                continue;
            }
            foreach (self::QUALITIES as $q) {
                $out = self::encode($scaled, $target, $q);
                if ($out !== '' && strlen($out) <= self::MAX_BYTES) {
                    return [$out, self::TYPES[$target]];
                }
            }
        }

        return [null, self::TYPES[$mime]];
    }

    /** GD ছবিকে $mime ধরনে কোড করে; JPEG-এ স্বচ্ছ অংশ সাদা হয়। */
    private static function encode(\GdImage $image, string $mime, int $quality): string
    {
        ob_start();
        if ($mime === 'image/png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagepng($image, null, 7);
        } elseif ($mime === 'image/webp') {
            imagewebp($image, null, $quality);
        } else {
            $flat = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagefill($flat, 0, 0, (int) imagecolorallocate($flat, 255, 255, 255));
            imagecopy($flat, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            imagejpeg($flat, null, $quality);
        }

        return (string) ob_get_clean();
    }

    /** মোবাইলে আড়াআড়ি/উল্টো করে তোলা JPEG-এর EXIF ঘোরানোর তথ্য মেনে ছবি সোজা করে (exif থাকলে)। */
    private static function upright(\GdImage $image, string $raw, string $mime): \GdImage
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }
        $exif = @exif_read_data('data://image/jpeg;base64,' . base64_encode($raw));
        $angle = [3 => 180, 6 => -90, 8 => 90][(int) ($exif['Orientation'] ?? 1)] ?? 0;
        if ($angle === 0) {
            return $image;
        }
        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }
}
