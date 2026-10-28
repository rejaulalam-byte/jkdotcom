<?php

declare(strict_types=1);

namespace App\Models;

/**
 * অ্যাডমিনিস্ট্রেটরের অনুমতির তালিকা (কী => বাংলা নাম)। সুপার অ্যাডমিনের সব অনুমতি আছে।
 * নতুন কাজ যোগ হলে এখানে একটা কী যোগ করুন এবং সেই কাজের স্ক্রিনে AdminAuth::can('কী') দিয়ে আটকান।
 * "কাঠামো" (মেনু, সেকশন, ফর্মের ঘর, রং-ফন্ট) কোনো অনুমতি নয়: ওটা শুধু সুপার অ্যাডমিনের।
 */
final class AdminPermissions
{
    /** @var array<string,string> */
    public const ALL = [
        'registrations' => 'কর্মী ও প্রতিনিধির আবেদন (অনুমোদন, হোল্ড, মোছা, সম্পাদনা)',
        'inbox' => 'মতামত, আগ্রহীর তালিকা ও কাজের বিজ্ঞপ্তি',
        'reports' => 'কল ও ভিজিটের রিপোর্ট',
        'email' => 'ইমেইল পাঠানো ও দেখা',
        'ads' => 'বিজ্ঞাপন',
        'services' => 'সেবার তালিকা (যোগ, নাম বদল, আইকন, চালু/বন্ধ, ক্রম) ও কর্মীদের নিজের লেখা পেশা',
        'pages' => 'পেইজ তৈরি ও লেখা',
        'locks' => 'আটকানো লগইন খোলা',
    ];

    /** @return list<string> সব অনুমতির কী */
    public static function keys(): array
    {
        return array_keys(self::ALL);
    }

    /**
     * ফর্ম থেকে আসা অনুমতির তালিকা থেকে শুধু চেনা কী-গুলো রাখে।
     *
     * @return list<string>
     */
    public static function clean(mixed $posted): array
    {
        $keys = [];
        foreach (is_array($posted) ? $posted : [] as $key) {
            if (is_string($key) && isset(self::ALL[$key])) {
                $keys[$key] = $key;
            }
        }

        return array_values($keys);
    }
}
