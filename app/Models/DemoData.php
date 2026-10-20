<?php

declare(strict_types=1);

namespace App\Models;

/** Sample data used ONLY when no database is configured (local preview). */
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
     * All services of the old site (database/seeds/services.php).
     * The icon is the default file public/assets/img/service-icons/<slug>.svg when it exists.
     *
     * @return list<array{name:string,slug:string,icon:?string}>
     */
    public static function services(): array
    {
        /** @var list<array{0:int,1:string,2:string}> $rows */
        $rows = require BASE_PATH . '/database/seeds/services.php';

        return array_map(
            static fn (array $r): array => [
                'name' => $r[1],
                'slug' => $r[2],
                'icon' => ServiceRepository::iconUrl('/assets/img/service-icons/' . $r[2] . '.svg'),
            ],
            $rows
        );
    }
}
