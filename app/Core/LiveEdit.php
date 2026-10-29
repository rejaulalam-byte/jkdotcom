<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\CustomFields;
use App\Models\FormFields;
use App\Models\SiteBoxes;
use App\Models\SiteImages;
use App\Models\SiteTexts;

/**
 * "লাইভ পেইজ এডিট"-এর ফ্রেমে (Security::isLivePreview) প্রতিটি সম্পাদনাযোগ্য লেখার দুই পাশে অদৃশ্য চিহ্ন বসায়,
 * যাতে live-edit.js পেইজে খুঁজে পায় কোন লেখা কোথায়, আর ক্লিকে সেটাই বদলানো যায়।
 * চিহ্ন: শুরুতে U+2061 + লেখার ক্রমিক নম্বর (দুই-অঙ্কে: U+2062 = ০, U+2063 = ১) + U+2061, শেষে U+2064।
 * এগুলো ছাপায় দেখা যায় না, বাংলা যুক্তাক্ষরেও (ZWJ/ZWNJ) হাত দেয় না; ব্রাউজারে live-edit.js সব মুছে দেয়।
 * সাধারণ দর্শকের পেইজে কিছুই বদলায় না।
 */
final class LiveEdit
{
    private const START = "\u{2061}";
    private const ZERO = "\u{2062}";
    private const ONE = "\u{2063}";
    private const END = "\u{2064}";

    /** লেখা নয় এমন মান (লিংক, ফোনের tel:, মানচিত্রের স্থানাঙ্ক): চিহ্ন বসালে ঠিকানা ভাঙত। */
    private const NOT_TEXT = '/^site\.social\.|_tel$|^site\.coordinates$/';

    /** @var array<string,int> এই পেইজে ব্যবহার হওয়া কী => ক্রমিক নম্বর */
    private static array $keys = [];

    /** @var array<string,bool> যে লেখাগুলো t() দিয়ে দেখানো (বোল্ড/রং ইত্যাদি চলে) */
    private static array $formattable = [];

    /** @var array<string,bool> এই পেইজে দেখানো বদলানো-যায় এমন ছবি (site_image()) */
    private static array $images = [];

    /** @var array<string,bool> এই পেইজে দেখানো ক্যাপসুল-বক্স (pill_attrs()) */
    private static array $boxes = [];

    public static function box(string $slot): void
    {
        self::$boxes[$slot] = true;
    }

    /** @var array<string,array{0:string,1:string}> এই পেইজের লুকানো-যায় এমন ফর্মের ঘর (field_open()) */
    private static array $fields = [];

    /** @var array<string,list<array<string,mixed>>> এই পেইজের ফর্মে অ্যাডমিনের যোগ করা ঘর (partials/custom-fields.php) */
    private static array $customForms = [];

    /** @param list<array<string,mixed>> $fields */
    public static function customForm(string $form, array $fields): void
    {
        self::$customForms[$form] = $fields;
    }

    public static function field(string $form, string $field): void
    {
        self::$fields[$form . '.' . $field] = [$form, $field];
    }

    public static function image(string $slot): void
    {
        self::$images[$slot] = true;
    }

    public static function formattable(string $key): void
    {
        self::$formattable[$key] = true;
    }

    public static function mark(string $key, string $value): string
    {
        if ($value === '' || preg_match(self::NOT_TEXT, $key) === 1 || !SiteTexts::editable($key)) {
            return $value;
        }
        $index = self::$keys[$key] ??= count(self::$keys);
        $bits = strtr(decbin($index), ['0' => self::ZERO, '1' => self::ONE]);

        return self::START . $bits . self::START . $value . self::END;
    }

    /**
     * ফ্রেমের জাভাস্ক্রিপ্টের জন্য তথ্য: ক্রম অনুযায়ী প্রতিটি লেখার কী, আসল মান, শুরুর লেখা ইত্যাদি,
     * আর সংরক্ষণের ঠিকানা ও CSRF টোকেন (শুধু লগইন-করা অ্যাডমিনের ফ্রেমেই বসে)।
     *
     * @return array<string,mixed>
     */
    public static function data(): array
    {
        $texts = [];
        foreach (array_keys(self::$keys) as $key) {
            $texts[] = [
                'key' => $key,
                'value' => Content::get($key),
                'original' => SiteTexts::original($key),
                'label' => SiteTexts::label($key),
                'changed' => Content::has($key),
                'placeholders' => SiteTexts::placeholders($key),
                'markup' => SiteTexts::usesColorMarkup($key) || isset(self::$formattable[$key]),
            ];
        }

        $images = [];
        foreach (array_keys(self::$images) as $slot) {
            $images[$slot] = [
                'label' => SiteImages::label($slot),
                'state' => SiteImages::state($slot),
                'src' => SiteImages::src($slot),
                'original' => SiteImages::original($slot),
            ];
        }
        $boxes = [];
        foreach (array_keys(self::$boxes) as $slot) {
            $boxes[$slot] = ['label' => SiteBoxes::label($slot), 'color' => SiteBoxes::color($slot), 'original' => SiteBoxes::original($slot)];
        }
        $fields = [];
        foreach (self::$fields as $id => [$form, $field]) {
            $fields[$id] = [
                'label' => FormFields::label($form, $field),
                'form' => FormFields::FORMS[$form] ?? '',
                'hideable' => FormFields::hideable($form, $field),
                'hidden' => FormFields::hidden($form, $field),
                'color' => FormFields::color($form, $field),
            ];
        }
        $customForms = [];
        foreach (self::$customForms as $form => $list) {
            $customForms[$form] = ['label' => FormFields::FORMS[$form] ?? '', 'fields' => $list];
        }
        $admin = AdminAuth::current();

        return [
            'texts' => $texts,
            'images' => $images,
            'imageSave' => admin_base() . '/studio/live-image',
            'boxes' => $boxes,
            'boxColors' => array_map(static fn (array $c): string => $c[0], SiteBoxes::COLORS),
            'boxSave' => admin_base() . '/studio/live-box',
            'messageSave' => admin_base() . '/studio/live-message',
            'fields' => $fields,
            'fieldSave' => admin_base() . '/studio/live-field',
            'fieldColors' => FormFields::COLORS,
            'customForms' => $customForms === [] ? new \stdClass() : $customForms,
            'customTypes' => CustomFields::TYPES,
            'customSave' => admin_base() . '/studio/live-custom',
            // লুকানো/দেখানো কাঠামোর কাজ: শুধু সুপার অ্যাডমিন
            'super' => $admin !== null && AdminAuth::isSuper($admin),
            'save' => admin_base() . '/studio/live-save',
            'token' => Security::csrfToken(),
            'colors' => array_keys(TEXT_COLORS),
        ];
    }
}
