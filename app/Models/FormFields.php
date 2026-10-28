<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের তিন ফর্মের (পেশাজীবী ও প্রতিনিধি রেজিস্ট্রেশন, কাজ সম্পর্কে জানান) যে ঘরগুলো সুপার অ্যাডমিন
 * "লাইভ পেইজ এডিট" থেকে লুকাতে বা আবার দেখাতে পারেন। লুকানো ঘর দর্শক দেখেন না, আর সার্ভারও তখন সেটা
 * আবশ্যক ধরে না (খালি জমা হয়)।
 * নাম, মোবাইল, রেজিস্ট্রেশনের ইমেইল, পাসওয়ার্ড, পেশা, এলাকার তিন ঘর, কাজের ধরন, প্রচারের তারিখ, বিস্তারিত ও
 * সম্মতি এখানে নেই: এগুলো ছাড়া লগইন, তালিকা, খোঁজা বা অনুমোদন চলে না, তাই এগুলো লুকানো যায় না।
 * অবস্থা থাকে লেখার টেবিলে (Content) "form.<ফর্ম>.hide.<ঘর>" = "1" নামে।
 */
final class FormFields
{
    /** ফর্ম => [ঘর => অ্যাডমিনকে দেখানো নাম] */
    public const FIELDS = [
        'worker' => [
            'gender'         => 'জেন্ডার',
            'extra_services' => 'আরও পেশা',
            'experience'     => 'কাজের অভিজ্ঞতা',
            'address'        => 'বর্তমান ঠিকানা',
            'work_districts' => 'কোন জেলায় কাজ করতে আগ্রহী',
            'references'     => 'রেফারেন্স (২জন)',
            'photo'          => 'ছবি',
            'bio'            => 'সংক্ষিপ্ত বিবরণ',
        ],
        'agent' => [
            'gender'  => 'জেন্ডার',
            'nid'     => 'জাতীয় পরিচয়পত্র নম্বর',
            'address' => 'বর্তমান ঠিকানা',
            'photo'   => 'ছবি',
        ],
        'post' => [
            'gender'   => 'জেন্ডার',
            'email'    => 'ইমেইল',
            'address'  => 'কাজের ঠিকানা',
            'end_date' => 'প্রচার বন্ধের তারিখ',
        ],
    ];

    /** ফর্মের নাম => অ্যাডমিনকে দেখানো নাম */
    public const FORMS = [
        'worker' => 'পেশাজীবীদের রেজিস্ট্রেশন ফর্ম',
        'agent'  => 'প্রতিনিধি রেজিস্ট্রেশন ফর্ম',
        'post'   => 'কাজ সম্পর্কে জানান ফর্ম',
    ];

    public static function exists(string $form, string $field): bool
    {
        return isset(self::FIELDS[$form][$field]);
    }

    public static function hidden(string $form, string $field): bool
    {
        return self::exists($form, $field) && Content::get('form.' . $form . '.hide.' . $field) === '1';
    }

    public static function label(string $form, string $field): string
    {
        return self::FIELDS[$form][$field] ?? '';
    }
}
