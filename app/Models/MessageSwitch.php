<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Content;

/**
 * সফলতা ও তথ্যের বার্তা চালু/বন্ধ (শুধু সুপার অ্যাডমিন, লাইভ পেইজ এডিটের "এই পেইজের বার্তা" থেকে)।
 * ভুল-বার্তা কখনও বন্ধ হয় না: দর্শক তখন বুঝতেন না ফর্ম কেন জমা হচ্ছে না। বন্ধ = কী "message.off.<নাম>" = '1'।
 * ধন্যবাদ-কার্ডের নাম তার লাইনগুলোর শুরু (যেমন regform.worker.done = text_1 + text_2 একসঙ্গে)।
 */
final class MessageSwitch
{
    /** বন্ধ করা যায় এমন বার্তা => বন্ধ করলে কী হয় (বাক্সে সতর্কতা হিসেবে দেখায়)। */
    public const SWITCHABLE = [
        'regform.worker.done' => 'বন্ধ থাকলে জমার পরে ধন্যবাদ-কার্ড আসবে না; দর্শক শুধু ফাঁকা ফর্ম দেখবেন।',
        'regform.agent.done' => 'বন্ধ থাকলে জমার পরে ধন্যবাদ-কার্ড আসবে না; দর্শক শুধু ফাঁকা ফর্ম দেখবেন।',
        'today.post.done' => 'বন্ধ থাকলে জমার পরে ধন্যবাদ-কার্ড আসবে না; দর্শক শুধু ফাঁকা ফর্ম দেখবেন।',
        'feedback.msg.done' => 'বন্ধ থাকলে মতামত পাঠানোর পরে কোনো নিশ্চয়তার লেখা আসবে না।',
        'subscribe.msg.done' => 'বন্ধ থাকলে ইমেইল যুক্ত হওয়ার পরে কোনো নিশ্চয়তার লেখা আসবে না।',
        'worker.rate.thanks' => 'বন্ধ থাকলে রেটিং দেওয়ার পরে ধন্যবাদের লেখা আসবে না।',
        'profile.done' => 'বন্ধ থাকলে প্রোফাইল সংরক্ষণের পরে নিশ্চয়তার লেখা আসবে না।',
        'password.changed' => 'বন্ধ থাকলে পাসওয়ার্ড বদলের পরে নিশ্চয়তার লেখা আসবে না।',
        'setup.done' => 'বন্ধ থাকলে প্রথম লগইনের তথ্য পূরণের পরে ধন্যবাদের লেখা আসবে না।',
        'login.unlocked' => 'বন্ধ থাকলে লক তুলে নেওয়ার কথা সদস্যকে জানানো হবে না।',
    ];

    private const PREFIX = 'message.off.';

    public static function switchable(string $name): bool
    {
        return isset(self::SWITCHABLE[$name]);
    }

    public static function isOn(string $name): bool
    {
        return !self::switchable($name) || Content::get(self::PREFIX . $name) !== '1';
    }

    public static function set(string $name, bool $on): bool
    {
        return $on ? Content::forget(self::PREFIX . $name) : Content::set(self::PREFIX . $name, '1');
    }
}
