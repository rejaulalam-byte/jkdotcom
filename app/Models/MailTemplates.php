<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সদস্যদের কাছে যাওয়া ইমেইলের লেখা (বিষয় ও ভেতরের লেখা): অ্যাডমিন বদলাতে পারেন, শুরুর লেখা app/Content/defaults.php-এ
 * (কী: mail.<নাম>.subject / mail.<নাম>.body)। লেখায় {নাম}-ধাঁচের জায়গা-ধারক থাকে; পাঠানোর সময় আসল মান বসে।
 * সাধারণ-লেখার ইমেইল (HTML নয়), তাই এখানে সাজ-সজ্জা নেই, শুধু লাইন ও শব্দ।
 */
final class MailTemplates
{
    /** একটা লেখায় সর্বোচ্চ কতটা অক্ষর। */
    public const MAX_LENGTH = 3000;

    /**
     * নাম => [শিরোনাম, কখন যায়, জায়গা-ধারক => মানে, অবশ্যই থাকতে হবে এমন জায়গা-ধারক]।
     *
     * @var array<string,array{title:string,when:string,vars:array<string,string>,required:list<string>}>
     */
    public const TEMPLATES = [
        'approval_worker' => [
            'title' => 'অনুমোদনের ইমেইল — পেশাজীবী কর্মী',
            'when' => 'অ্যাডমিন কোনো কর্মীর আবেদন অনুমোদন করলে (সদস্যের ইমেইল থাকলে)।',
            'vars' => ['{name}' => 'সদস্যের নাম (মি:/মিস সহ)', '{login_id}' => 'লগইন আইডি (মোবাইল ও/বা ইমেইল)', '{login_url}' => 'লগইন পেইজের ঠিকানা',
                '{profile_url}' => 'প্রোফাইল পেইজের ঠিকানা', '{public_url}' => 'কর্মীর নিজের পাবলিক পেইজের ঠিকানা', '{helpline}' => 'হেল্পলাইন নাম্বার'],
            'required' => ['{login_id}'],
        ],
        'approval_agent' => [
            'title' => 'অনুমোদনের ইমেইল — প্রতিনিধি',
            'when' => 'অ্যাডমিন কোনো প্রতিনিধির আবেদন অনুমোদন করলে (সদস্যের ইমেইল থাকলে)।',
            'vars' => ['{name}' => 'সদস্যের নাম (মি:/মিস সহ)', '{login_id}' => 'লগইন আইডি (মোবাইল ও/বা ইমেইল)', '{login_url}' => 'লগইন পেইজের ঠিকানা',
                '{profile_url}' => 'প্রোফাইল পেইজের ঠিকানা', '{helpline}' => 'হেল্পলাইন নাম্বার'],
            'required' => ['{login_id}'],
        ],
        'reject' => [
            'title' => 'বাতিলের ইমেইল — কারণ ছাড়া',
            'when' => 'অ্যাডমিন আবেদন বাতিল করলেন, কিন্তু "সদস্যকে জানানোর কারণ" ঘর ফাঁকা রাখলে।',
            'vars' => ['{name}' => 'সদস্যের নাম (মি:/মিস সহ)', '{helpline}' => 'হেল্পলাইন নাম্বার'],
            'required' => [],
        ],
        'reject_reason' => [
            'title' => 'বাতিলের ইমেইল — কারণসহ',
            'when' => 'অ্যাডমিন আবেদন বাতিল করলেন আর "সদস্যকে জানানোর কারণ" লিখলে।',
            'vars' => ['{name}' => 'সদস্যের নাম (মি:/মিস সহ)', '{reason}' => 'অ্যাডমিনের লেখা কারণ', '{helpline}' => 'হেল্পলাইন নাম্বার'],
            'required' => ['{reason}'],
        ],
        'password_code' => [
            'title' => 'পাসওয়ার্ড পুনরুদ্ধারের কোড',
            'when' => 'কেউ লগইন পেইজে "পাসওয়ার্ড মনে নেই" চাপলে (স্বয়ংক্রিয়)।',
            'vars' => ['{code}' => '৬ সংখ্যার কোড'],
            'required' => ['{code}'],
        ],
        'fill_info' => [
            'title' => '"তথ্য পূর্ণ করুন" অনুরোধের ইমেইল',
            'when' => 'অ্যাডমিন "অসম্পূর্ণ তথ্য" পেইজ থেকে কোনো কর্মীকে অনুরোধ পাঠালে।',
            'vars' => ['{name}' => 'সদস্যের নাম (মি:/মিস সহ)', '{missing_list}' => 'যে তথ্য বাকি, প্রতি লাইনে একটি', '{login_url}' => 'লগইন পেইজের ঠিকানা', '{helpline}' => 'হেল্পলাইন নাম্বার'],
            'required' => ['{missing_list}'],
        ],
    ];

    /** পরীক্ষার ইমেইলে বসানো নমুনা মান। */
    private const SAMPLE = [
        '{name}' => 'মি: নমুনা সদস্য', '{login_id}' => '01712 345 678 অথবা sample@example.com', '{login_url}' => '/login', '{profile_url}' => '/profile',
        '{public_url}' => '/workers/1', '{helpline}' => '', '{reason}' => 'নমুনা কারণ', '{code}' => '123456', '{missing_list}' => "• ঠিকানা\n• ইমেইল",
    ];

    public static function exists(string $id): bool
    {
        return isset(self::TEMPLATES[$id]);
    }

    /** @return array{subject:string,body:string} বর্তমান (বদলানো বা শুরুর) লেখা */
    public static function current(string $id): array
    {
        return ['subject' => Content::get('mail.' . $id . '.subject'), 'body' => Content::get('mail.' . $id . '.body')];
    }

    /** @return array{subject:string,body:string} সাইটের সঙ্গে আসা শুরুর লেখা */
    public static function original(string $id): array
    {
        static $defaults = null;
        $defaults ??= require BASE_PATH . '/app/Content/defaults.php';

        return ['subject' => (string) ($defaults['mail.' . $id . '.subject'] ?? ''), 'body' => (string) ($defaults['mail.' . $id . '.body'] ?? '')];
    }

    public static function changed(string $id): bool
    {
        return Content::has('mail.' . $id . '.subject') || Content::has('mail.' . $id . '.body');
    }

    /**
     * পাঠানোর জন্য বিষয় ও লেখা, জায়গা-ধারকে আসল মান বসিয়ে।
     *
     * @param array<string,string> $vars  যেমন ['{name}' => 'মি: রেজা']; যে ধারকের মান দেওয়া হয়নি তা ফাঁকা হয়ে যায়
     * @return array{subject:string,body:string}
     */
    public static function render(string $id, array $vars): array
    {
        $current = self::current($id);
        $all = $vars + array_fill_keys(array_keys(self::TEMPLATES[$id]['vars'] ?? []), '');

        return ['subject' => strtr($current['subject'], $all), 'body' => strtr($current['body'], $all)];
    }

    /** পরীক্ষার ইমেইলের জন্য নমুনা মান (ঠিকানাগুলো সাইটের নিজের ঠিকানায়)। */
    public static function sample(string $id, string $baseUrl): array
    {
        $out = [];
        foreach (array_keys(self::TEMPLATES[$id]['vars']) as $var) {
            $value = self::SAMPLE[$var] ?? '';
            $out[$var] = str_starts_with($value, '/') ? $baseUrl . $value : $value;
        }
        $out['{helpline}'] = Content::get('today.post.helpline');

        return $out;
    }

    /**
     * সংরক্ষণের আগে লেখা যাচাই: ফাঁকা নয়, বেশি লম্বা নয়, অচেনা {ধারক} নেই, দরকারি ধারক আছে।
     * ঠিক থাকলে null, নইলে সদস্য-বান্ধব বাংলা বার্তা।
     */
    public static function validate(string $id, string $subject, string $body): ?string
    {
        $template = self::TEMPLATES[$id];
        if ($subject === '' || $body === '') {
            return '"' . $template['title'] . '": বিষয় ও লেখা ফাঁকা রাখা যাবে না।';
        }
        if (mb_strlen($subject) > 200 || mb_strlen($body) > self::MAX_LENGTH) {
            return '"' . $template['title'] . '": লেখা বেশি লম্বা হয়ে গেছে।';
        }
        preg_match_all('/\{[a-z_]+\}/', $subject . ' ' . $body, $m);
        foreach (array_unique($m[0]) as $var) {
            if (!isset($template['vars'][$var])) {
                return '"' . $template['title'] . '": ' . $var . ' এখানে চলে না। চলে: ' . implode(' ', array_keys($template['vars'])) . '।';
            }
        }
        foreach ($template['required'] as $var) {
            if (!str_contains($body, $var)) {
                return '"' . $template['title'] . '": লেখায় ' . $var . ' থাকতেই হবে (সেখানে ' . $template['vars'][$var] . ' বসে)।';
            }
        }

        return null;
    }
}
