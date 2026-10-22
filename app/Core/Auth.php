<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\UserRepository;

/** লগইন-করা সদস্য (কর্মী বা প্রতিনিধি): সেশনে শুধু সদস্যের id থাকে, বাকি তথ্য প্রতিবার ডাটাবেস থেকে আসে। */
final class Auth
{
    /** @var array<string,mixed>|null|false false = এখনও খোঁজা হয়নি */
    private static array|null|false $user = false;

    /** সফল লগইনের পর: সেশন-id বদলে (session fixation আটকাতে) সদস্যকে লগইন-করা হিসেবে রাখে। */
    public static function login(int $id): void
    {
        session_regenerate_id(true);
        $_SESSION['uid'] = $id;
        self::$user = false;
    }

    public static function logout(): void
    {
        unset($_SESSION['uid']);
        session_regenerate_id(true);
        self::$user = false;
    }

    /**
     * এখন লগইন-করা সদস্য; লগইন না থাকলে বা অ্যাকাউন্ট আর 'approved' না থাকলে null।
     *
     * @return array<string,mixed>|null
     */
    public static function user(): ?array
    {
        if (self::$user !== false) {
            return self::$user;
        }
        $id = (int) ($_SESSION['uid'] ?? 0);
        $row = $id > 0 ? UserRepository::findById($id) : null;
        if ($row !== null && $row['status'] !== 'approved') {
            $row = null;
            unset($_SESSION['uid']);
        }

        return self::$user = $row;
    }
}
