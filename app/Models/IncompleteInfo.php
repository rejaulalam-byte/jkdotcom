<?php

declare(strict_types=1);

namespace App\Models;

/**
 * কর্মীর কোন তথ্য বাকি আছে তার নিয়ম (অ্যাডমিনের "অসম্পূর্ণ তথ্য" পেইজ): কোন ঘর খালি হলে "বাকি", তার নাম এবং
 * SQL-এর শর্ত এক জায়গায়, যাতে তালিকা, সংখ্যা ও ডেমো-মোড একই নিয়মে চলে।
 */
final class IncompleteInfo
{
    /** @var array<string,string> কী => বাংলা নাম (এই ক্রমেই দেখানো হয়) */
    public const LABELS = [
        'phone' => 'মোবাইল',
        'email' => 'ইমেইল',
        'address' => 'ঠিকানা',
        'area' => 'উপজেলা/জেলা/বিভাগ',
        'experience' => 'অভিজ্ঞতা',
        'bio' => 'পরিচিতি',
    ];

    /** "সব" ট্যাবে যেগুলোর যেকোনোটা বাকি থাকলে কর্মী আসে (অভিজ্ঞতা ও পরিচিতি ঐচ্ছিক, তাই আলাদা ট্যাবে)। */
    public const CORE = ['phone', 'email', 'address', 'area'];

    /** যে তিনটা ঘর এই পেইজেই সরাসরি বসানো যায় (বাকিগুলো সম্পাদনা-পেইজে)। */
    public const INLINE = ['phone', 'email', 'address'];

    /**
     * এই কর্মীর বাকি তথ্যের কী-গুলো (LABELS-এর ক্রমে)।
     *
     * @param array<string,mixed> $user
     * @return list<string>
     */
    public static function missing(array $user): array
    {
        $out = [];
        foreach (array_keys(self::LABELS) as $key) {
            $empty = match ($key) {
                'phone' => (string) ($user['phone'] ?? '') === '',
                'email' => (string) ($user['email'] ?? '') === '',
                'address' => trim((string) ($user['address'] ?? '')) === '',
                'area' => (int) ($user['upazila_id'] ?? 0) === 0,
                'experience' => ($user['experience_years'] ?? null) === null || $user['experience_years'] === '',
                'bio' => trim((string) ($user['bio'] ?? '')) === '',
            };
            if ($empty) {
                $out[] = $key;
            }
        }

        return $out;
    }

    /** একটা কী-র "বাকি" শর্ত, SQL-এ (কোনো ব্যবহারকারীর লেখা এতে ঢোকে না)। */
    public static function sql(string $key): string
    {
        return match ($key) {
            'phone' => 'phone IS NULL',
            'email' => "(email IS NULL OR email = '')",
            'address' => "(address IS NULL OR address = '')",
            'area' => '(upazila_id IS NULL OR upazila_id = 0)',
            'experience' => 'experience_years IS NULL',
            'bio' => "(bio IS NULL OR bio = '')",
            default => '1 = 1',
        };
    }

    /** ফিল্টার ('' = সব, নইলে একটা কী) অনুযায়ী SQL-এর শর্ত। */
    public static function sqlFor(string $filter): string
    {
        if ($filter === '') {
            return '(' . implode(' OR ', array_map([self::class, 'sql'], self::CORE)) . ')';
        }

        return self::sql($filter);
    }
}
