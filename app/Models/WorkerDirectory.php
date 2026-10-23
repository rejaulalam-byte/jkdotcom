<?php

declare(strict_types=1);

namespace App\Models;

/**
 * একটা সেবার কর্মী-তালিকা-পেইজের জন্য: খোঁজা, "বিভাগ:" শিরোনামের নাম, প্রতি-পৃষ্ঠার সংখ্যা ও পৃষ্ঠা-ভাগ।
 * (ডাটাবেস থেকে আনা তালিকা PHP-তে ছাঁকা হয়: এক সেবার কর্মী অল্প, আর ডেমো মোডেও একই নিয়ম চলে।)
 */
final class WorkerDirectory
{
    /** প্রতি-পৃষ্ঠার বাছাই; 0 = সব। প্রথমটা ডিফল্ট। */
    public const PER_PAGE = [15, 25, 50, 0];

    /**
     * নাম, মোবাইল, এলাকা (ঠিকানা), উপজেলা/থানা, জেলা, বিভাগ অথবা কাজ করতে আগ্রহী জেলার সঙ্গে লেখার অংশ মিললেই কর্মী থাকে।
     * যিনি "সব জেলা" দিয়েছেন, তিনি যেকোনো জেলার নাম খুঁজলেই আসেন।
     *
     * @param list<array<string,mixed>> $workers
     * @return list<array<string,mixed>>
     */
    public static function filter(array $workers, string $query): array
    {
        $query = trim($query);
        if ($query === '') {
            return $workers;
        }

        $needle = mb_strtolower($query);
        // মোবাইল খোঁজা: লেখায় শুধু অঙ্ক (বাংলা/ইংরেজি), ফাঁক, + ও - আছে এবং অন্তত ৩টা অঙ্ক
        $digits = normalize_digits($query);
        $byPhone = strlen($digits) >= 3 && preg_match('/^[\d০-৯\s+\-]+$/u', $query) === 1;
        if ($byPhone && strlen($digits) >= 10) {
            $digits = normalize_phone($digits);
        }

        // খোঁজার লেখা কি কোনো জেলার নামের অংশ? ("সব জেলা"-র কর্মী তখন আসবেন)
        $isDistrict = false;
        foreach (GeoRepository::all()['districts'] as $district) {
            if (str_contains(mb_strtolower($district['name']), $needle)) {
                $isDistrict = true;
                break;
            }
        }

        return array_values(array_filter($workers, static function (array $w) use ($needle, $digits, $byPhone, $isDistrict): bool {
            if ($byPhone) {
                return str_contains($w['phone'], $digits);
            }
            if ($isDistrict && !empty($w['work_all'])) {
                return true;
            }
            foreach (array_merge([$w['name'], $w['address'], $w['upazila'], $w['district'], $w['division']], (array) ($w['work_districts'] ?? [])) as $field) {
                if ($field !== '' && str_contains(mb_strtolower($field), $needle)) {
                    return true;
                }
            }

            return false;
        }));
    }

    /**
     * তালিকায় যে বিভাগগুলোর কর্মী আছে, প্রথম-দেখা ক্রমে ও অনন্য ("ঢাকা, বরিশাল")।
     *
     * @param list<array{division:string}> $workers
     * @return list<string>
     */
    public static function divisions(array $workers): array
    {
        $names = [];
        foreach ($workers as $w) {
            if ($w['division'] !== '') {
                $names[$w['division']] = true;
            }
        }

        return array_keys($names);
    }

    /** প্রতি-পৃষ্ঠার মান ঠিক করে: অনুমোদিত তালিকার বাইরে হলে প্রথমটা (১৫)। */
    public static function perPage(mixed $value): int
    {
        if ($value === 'all') {
            return 0;
        }
        $n = is_string($value) ? (int) $value : 0;

        return $n > 0 && in_array($n, self::PER_PAGE, true) ? $n : self::PER_PAGE[0];
    }

    /**
     * @template T of array
     * @param list<T> $items
     * @return array{items:list<T>,page:int,pages:int,offset:int}
     */
    public static function paginate(array $items, int $perPage, int $page): array
    {
        $pages = $perPage === 0 ? 1 : max(1, (int) ceil(count($items) / $perPage));
        $page = min(max(1, $page), $pages);
        $offset = $perPage === 0 ? 0 : ($page - 1) * $perPage;

        return [
            'items' => $perPage === 0 ? $items : array_slice($items, $offset, $perPage),
            'page' => $page,
            'pages' => $pages,
            'offset' => $offset,
        ];
    }
}
