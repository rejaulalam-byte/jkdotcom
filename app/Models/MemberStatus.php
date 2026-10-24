<?php

declare(strict_types=1);

namespace App\Models;

/**
 * কর্মী/প্রতিনিধির অবস্থার বাংলা নাম (ডাটাবেসের status মান => লেখা)।
 * 'suspended' = হোল্ড: সাইটে দেখা যায় না, লগইন বন্ধ, কিন্তু মোছা নয়; অনুমতি দিলে আবার চালু।
 */
final class MemberStatus
{
    /** @var array<string,string> */
    public const LABELS = [
        'pending' => 'নতুন আবেদন',
        'approved' => 'অনুমোদিত',
        'suspended' => 'হোল্ড',
        'rejected' => 'বাতিল',
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
