<?php

declare(strict_types=1);

namespace App\Models;

/** নমুনা তথ্য — শুধু ডাটাবেস সেট করা না থাকলে (লোকাল প্রিভিউ) ব্যবহার হয়। */
final class DemoData
{
    /** @return list<array{id:int,title:string}> */
    public static function jobs(): array
    {
        $titles = [
            'এসি রিপেয়ারিং।', 'ফ্যান বদল করা।', 'গ্যাসের চুলা মেরামত করা।', 'বাড়ি রং করা।',
            'বাসা পরিবর্তন করা।', 'গ্যাসের চুলা মেরামত করা।', 'বাড়ি রং করা।', 'বাসা পরিবর্তন করা।',
            'সিলিং ফ্যান পরিবর্তন করা।', 'এসি সার্ভিসিং এর কাজ।', 'প্লাম্বিং কাজ।', 'ইলেকট্রিক লাইন ঠিক করা।',
        ];
        $rows = [];
        foreach ($titles as $i => $title) {
            $rows[] = ['id' => $i + 1, 'title' => $title];
        }

        return $rows;
    }

    /**
     * তালিকা-পেইজের জন্য দুটো নমুনা বিজ্ঞপ্তি (শুধু ডাটাবেস সংযুক্ত না থাকলে)।
     *
     * @return list<array<string,mixed>>
     */
    public static function jobPosts(): array
    {
        $today = date('Y-m-d');

        return [
            [
                'id' => 1, 'title' => 'এসি সার্ভিসিং এর কাজ।',
                'details' => 'চারতলা বাসায় চালমান একটি এসি সার্ভিসিং করা লাগবে। যোগাযোগ করতে সকাল ১০টা থেকে বিকাল ৫টার মধ্যে অনুরোধ করা যাচ্ছে।',
                'contact_name' => 'মি: আব্দুল আজিজ সরকার', 'phone' => '01774123443', 'email' => 'mr.azizsarkar@gmail.com',
                'address' => 'বাসা-১৩৩, রোড নং- ১৭বি, এলাকা- কাফরুল',
                'upazila_id' => 506, 'district_id' => 47, 'division_id' => 6,
                'start_date' => $today, 'created_at' => $today . ' 10:55:17',
            ],
            [
                'id' => 2, 'title' => 'সিলিং ফ্যান পরিবর্তন করা।',
                'details' => 'বাসার তিনটি সিলিং ফ্যান পরিবর্তন করতে হবে। নতুন ফ্যান আমরা কিনে রেখেছি।',
                'contact_name' => 'মিসেস রাবেয়া খাতুন', 'phone' => '01812345699', 'email' => '',
                'address' => 'বাসা-২৫, রোড নং- ৪, সেক্টর- ১১',
                'upazila_id' => 499, 'district_id' => 47, 'division_id' => 6,
                'start_date' => $today, 'created_at' => $today . ' 00:25:47',
            ],
        ];
    }

    /** @return list<array{title:string,image:string,width:int,height:int,link:?string}> */
    public static function ads(): array
    {
        return [
            ['title' => 'বিজ্ঞাপন ১', 'image' => '/assets/img/ads/ad-1.png', 'width' => 462, 'height' => 278, 'link' => null],
            ['title' => 'বিজ্ঞাপন ২', 'image' => '/assets/img/ads/ad-2.png', 'width' => 462, 'height' => 278, 'link' => null],
            ['title' => 'বিজ্ঞাপন ৩', 'image' => '/assets/img/ads/ad-3.png', 'width' => 462, 'height' => 278, 'link' => null],
        ];
    }

    /**
     * লোকাল প্রিভিউয়ের সেবা-তালিকা: ServiceAdmin-এর ফাইল (না থাকলে database/seeds/services.php-এর পুরনো সাইটের সব সেবা),
     * শুধু চালুগুলো, অ্যাডমিনের ক্রমে। আইকন: অ্যাডমিনের দেওয়া ছবি বা public/assets/img/service-icons/<slug>.svg।
     *
     * @return list<array{name:string,slug:string,icon:?string}>
     */
    public static function services(): array
    {
        $rows = array_values(array_filter(ServiceAdmin::demoRead(), static fn (array $r): bool => (bool) $r['is_active']));
        usort($rows, static fn (array $x, array $y): int => [(int) $x['sort_order'], (int) $x['id']] <=> [(int) $y['sort_order'], (int) $y['id']]);

        return array_map(
            static fn (array $r): array => [
                'name' => (string) $r['name'],
                'slug' => (string) $r['slug'],
                'icon' => ServiceRepository::iconUrl($r['icon_path'] !== null ? (string) $r['icon_path'] : null),
            ],
            $rows
        );
    }
}
