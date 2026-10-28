<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সাইটের তিন ফর্মের (পেশাজীবী ও প্রতিনিধি রেজিস্ট্রেশন, কাজ সম্পর্কে জানান) ঘর, যেগুলো সুপার অ্যাডমিন
 * "লাইভ পেইজ এডিট" থেকে সাজাতে পারেন:
 *   - রং: প্রতিটি ঘরের বাক্সের পেছনের রং (COLORS-এর একটা; app.css-এর .fc--*)।
 *   - লুকানো (শুধু hideable ঘর): লুকানো ঘর দর্শক দেখেন না, আর সার্ভারও তখন সেটা আবশ্যক ধরে না (খালি জমা হয়)।
 *     নাম, মোবাইল, রেজিস্ট্রেশনের ইমেইল, পাসওয়ার্ড, পেশা, এলাকার তিন ঘর, কাজের ধরন, প্রচারের তারিখ ও বিস্তারিত
 *     লুকানো যায় না: এগুলো ছাড়া লগইন, তালিকা, খোঁজা বা অনুমোদন চলে না। (সম্মতির টিক এখানে নেই, সেটা সবসময় থাকে।)
 * অবস্থা থাকে লেখার টেবিলে (Content): "form.<ফর্ম>.hide.<ঘর>" = "1", "form.<ফর্ম>.color.<ঘর>" = রঙের নাম।
 */
final class FormFields
{
    /** ফর্ম => [ঘর => [অ্যাডমিনকে দেখানো নাম, লুকানো যায় কি না]] */
    public const FIELDS = [
        'worker' => [
            'name'           => ['নাম', false],
            'gender'         => ['জেন্ডার', true],
            'service'        => ['পেশা', false],
            'extra_services' => ['আরও পেশা', true],
            'experience'     => ['কাজের অভিজ্ঞতা', true],
            'email'          => ['ইমেইল', false],
            'phone'          => ['মোবাইল', false],
            'address'        => ['বর্তমান ঠিকানা', true],
            'upazila'        => ['থানা/উপজেলা', false],
            'district'       => ['জেলা', false],
            'division'       => ['বিভাগ', false],
            'work_districts' => ['কোন জেলায় কাজ করতে আগ্রহী', true],
            'references'     => ['রেফারেন্স (২জন)', true],
            'password'       => ['পাসওয়ার্ড', false],
            'password2'      => ['পাসওয়ার্ড আবার', false],
            'photo'          => ['ছবি', true],
            'bio'            => ['সংক্ষিপ্ত বিবরণ', true],
        ],
        'agent' => [
            'name'      => ['নাম', false],
            'gender'    => ['জেন্ডার', true],
            'nid'       => ['জাতীয় পরিচয়পত্র নম্বর', true],
            'email'     => ['ইমেইল', false],
            'phone'     => ['মোবাইল', false],
            'address'   => ['বর্তমান ঠিকানা', true],
            'upazila'   => ['থানা/উপজেলা', false],
            'district'  => ['জেলা', false],
            'division'  => ['বিভাগ', false],
            'password'  => ['পাসওয়ার্ড', false],
            'password2' => ['পাসওয়ার্ড আবার', false],
            'photo'     => ['ছবি', true],
        ],
        'post' => [
            'title'    => ['কাজের ধরন', false],
            'name'     => ['নাম', false],
            'gender'   => ['জেন্ডার', true],
            'phone'    => ['মোবাইল', false],
            'email'    => ['ইমেইল', true],
            'start'    => ['প্রচারের তারিখ', false],
            'address'  => ['কাজের ঠিকানা', true],
            'upazila'  => ['থানা/উপজেলা', false],
            'district' => ['জেলা', false],
            'division' => ['বিভাগ', false],
            'end_date' => ['প্রচার বন্ধের তারিখ', true],
            'details'  => ['বিস্তারিত', false],
        ],
    ];

    /** ফর্মের নাম => অ্যাডমিনকে দেখানো নাম */
    public const FORMS = [
        'worker' => 'পেশাজীবীদের রেজিস্ট্রেশন ফর্ম',
        'agent'  => 'প্রতিনিধি রেজিস্ট্রেশন ফর্ম',
        'post'   => 'কাজ সম্পর্কে জানান ফর্ম',
    ];

    /** ঘরের বাক্সের রং: নাম => অ্যাডমিনকে দেখানো নাম (সব হালকা, যাতে লেখা পড়া যায়; CSS ক্লাস .fc--নাম) */
    public const COLORS = [
        'white' => 'সাদা',
        'cream' => 'হালকা ক্রিম',
        'gold'  => 'হালকা সোনালি',
        'green' => 'হালকা সবুজ',
        'blue'  => 'হালকা নীল',
        'pink'  => 'হালকা গোলাপি',
        'grey'  => 'হালকা ধূসর',
        'teal'  => 'একটু গাঢ় টিল',
    ];

    /** @var list<bool> field_open() কোনটায় ঘর খুলেছে (field_close() সেই অনুযায়ী বন্ধ করে) */
    private static array $opened = [];

    public static function exists(string $form, string $field): bool
    {
        return isset(self::FIELDS[$form][$field]);
    }

    public static function hideable(string $form, string $field): bool
    {
        return self::FIELDS[$form][$field][1] ?? false;
    }

    public static function hidden(string $form, string $field): bool
    {
        return self::hideable($form, $field) && Content::get('form.' . $form . '.hide.' . $field) === '1';
    }

    /** বাছা রঙের নাম, না থাকলে খালি (সাইটের সাধারণ রং)। */
    public static function color(string $form, string $field): string
    {
        $value = Content::get('form.' . $form . '.color.' . $field);

        return self::exists($form, $field) && isset(self::COLORS[$value]) ? $value : '';
    }

    public static function label(string $form, string $field): string
    {
        return self::FIELDS[$form][$field][0] ?? '';
    }

    public static function pushOpened(bool $opened): void
    {
        self::$opened[] = $opened;
    }

    public static function popOpened(): bool
    {
        return array_pop(self::$opened) ?? false;
    }
}
