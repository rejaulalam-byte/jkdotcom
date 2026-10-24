<?php

declare(strict_types=1);

namespace App\Models;

/**
 * কর্মী/প্রতিনিধির অবস্থার বাংলা নাম (ডাটাবেসের status মান => লেখা) ও কোন অবস্থা থেকে কোন কাজ চলে।
 * 'suspended' = হোল্ড: সাইটে দেখা যায় না, লগইন বন্ধ, কিন্তু মোছা নয়; অনুমতি দিলে (অনুমোদন) আবার চালু।
 * 'reserved' = সংরক্ষিত ("মুছুন" চাপলে প্রথম ধাপ), 'trashed' = ডাস্টবিন (দ্বিতীয় ধাপ)।
 */
final class MemberStatus
{
    /** @var array<string,string> */
    public const LABELS = [
        'pending' => 'নতুন আবেদন',
        'approved' => 'অনুমোদিত',
        'suspended' => 'হোল্ড',
        'rejected' => 'বাতিল',
        'reserved' => 'সংরক্ষিত',
        'trashed' => 'ডাস্টবিন',
    ];

    /**
     * কাজ => [যে অবস্থাগুলো থেকে চলে, নতুন অবস্থা]।
     * restore-এর নতুন অবস্থা আগের অবস্থা (previous_status), না থাকলে 'pending'।
     *
     * @var array<string,array{0:list<string>,1:string}>
     */
    public const ACTIONS = [
        'approve' => [['pending', 'suspended', 'rejected'], 'approved'],
        'hold' => [['pending', 'approved'], 'suspended'],
        'reject' => [['pending', 'suspended'], 'rejected'],
        'reserve' => [['pending', 'approved', 'suspended', 'rejected'], 'reserved'],
        'trash' => [['reserved'], 'trashed'],
        'restore' => [['reserved', 'trashed'], 'pending'],
        'reopen' => [['approved', 'suspended', 'rejected'], 'pending'],
    ];

    public static function label(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }
}
