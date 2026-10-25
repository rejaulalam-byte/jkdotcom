<?php

declare(strict_types=1);

namespace App\Core;

/**
 * খোলা পেইজের লেখার ভেতরের ছবি (অ্যাডমিন-এডিটরে টেনে আনা বা "ছবি" বাটন দিয়ে দেওয়া): JPG / PNG / WebP, সর্বোচ্চ ২ MB।
 * PNG-এর স্বচ্ছ কিনারা থাকে (লেখা ছবির গা ঘেঁষে মোড়ানোর জন্য), তাই ছবি যেমন আছে তেমন রাখা হয়।
 * ফাইল ওয়েব-রুটের বাইরে storage/uploads/pages-এ; দেখানো হয় /page-image/<৩২-অক্ষর> দিয়ে।
 */
final class PageImage
{
    public const MAX_BYTES = 2097152;

    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param array<string,mixed>|null $file $_FILES-এর একটা এন্ট্রি
     * @return array{0:?string,1:?string} [ওয়েব ঠিকানা (/page-image/...), ত্রুটির বার্তা]
     */
    public static function store(?array $file): array
    {
        if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [null, 'কোনো ছবি পাওয়া যায়নি।'];
        }
        if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE || (int) ($file['size'] ?? 0) > self::MAX_BYTES) {
            return [null, 'ছবির ফাইল সাইজ সর্বোচ্চ ২ MB হতে পারে।'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string) $file['tmp_name'])) {
            return [null, 'ছবিটি আপলোড করা যায়নি। আবার চেষ্টা করুন।'];
        }

        $tmp = (string) $file['tmp_name'];
        $mime = (string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        $info = @getimagesize($tmp);
        if (!isset(self::TYPES[$mime]) || $info === false || ($info['mime'] ?? '') !== $mime) {
            return [null, 'ছবির ফরমেট অবশ্যই JPG, PNG বা WebP হতে হবে।'];
        }
        if ($info[0] < 10 || $info[1] < 10 || $info[0] > 6000 || $info[1] > 6000) {
            return [null, 'ছবির মাপ ঠিক নয় (১০ থেকে ৬০০০ পিক্সেলের মধ্যে হতে হবে)।'];
        }

        $dir = self::directory();
        if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
            Logger::error('Page image folder could not be created: ' . $dir);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি।'];
        }
        $hex = bin2hex(random_bytes(16));
        if (!@move_uploaded_file($tmp, $dir . '/' . $hex . '.' . self::TYPES[$mime])) {
            Logger::error('Page image could not be written: ' . $dir . '/' . $hex);

            return [null, 'ছবিটি সংরক্ষণ করা যায়নি।'];
        }

        return ['/page-image/' . $hex, null];
    }

    public static function directory(): string
    {
        return BASE_PATH . '/storage/uploads/pages';
    }
}
