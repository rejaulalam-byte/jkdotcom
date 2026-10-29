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

    /** বাক্সের ডিজাইন বাছা যায় এমন বার্তা (রেটিংয়ের ছোট লেখা বাদ; তার শুধু চালু-বন্ধ)। */
    public const DESIGNABLE = [
        'regform.worker.done', 'regform.agent.done', 'today.post.done', 'feedback.msg.done', 'subscribe.msg.done',
        'profile.done', 'password.changed', 'setup.done', 'login.unlocked',
    ];

    /** শুরু থেকেই সবুজ-টিক কার্ডে দেখানো তিনটি; বাকিগুলো শুরুতে পট্টি। */
    private const CARDS = ['regform.worker.done', 'regform.agent.done', 'today.post.done'];

    /** ডিজাইন => নাম। card = সবুজ টিকের কার্ড, popup = সাধারণ পপ-আপ, strip = পট্টি। */
    public const DESIGNS = [
        'card' => 'কার্ড (সবুজ টিকসহ)',
        'popup' => 'সাধারণ পপ-আপ',
        'strip' => 'রঙিন পট্টি',
    ];

    /** রং => নাম; '' = ডিজাইনের নিজের শুরুর রং। CSS-এ .msgc--<রং>। */
    public const COLORS = [
        '' => 'শুরুর রং',
        'green' => 'সবুজ',
        'teal' => 'টিল',
        'blue' => 'নীল',
        'amber' => 'হলুদ-কমলা',
        'grey' => 'ধূসর',
    ];

    /** কার্ড/পপ-আপ/ওপরের-পট্টি কত সেকেন্ড থাকবে (0 = নিজে নিজে যাবে না, শুধু ✕-এ বন্ধ); CSS-এর data-seconds নিয়মের সঙ্গে মেলে। */
    public const SECONDS = [0, 5, 10, 20, 30, 60, 120];

    public const DEFAULT_SECONDS = 20;

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

    public static function designable(string $name): bool
    {
        return in_array($name, self::DESIGNABLE, true);
    }

    public static function design(string $name): string
    {
        $v = Content::get('message.design.' . $name);

        return isset(self::DESIGNS[$v]) ? $v : (in_array($name, self::CARDS, true) ? 'card' : 'strip');
    }

    public static function color(string $name): string
    {
        $v = Content::get('message.color.' . $name);

        return $v !== '' && isset(self::COLORS[$v]) ? $v : '';
    }

    public static function seconds(string $name): int
    {
        $v = Content::get('message.seconds.' . $name);

        return $v !== '' && in_array((int) $v, self::SECONDS, true) ? (int) $v : self::DEFAULT_SECONDS;
    }

    /** ডিজাইন, রং ও সময় একসঙ্গে রাখে; শুরুর মান হলে কী মুছে ফেলে। মান আগেই যাচাই করা থাকতে হবে। */
    public static function setStyle(string $name, string $design, string $color, int $seconds): bool
    {
        $default = in_array($name, self::CARDS, true) ? 'card' : 'strip';
        $ok = $design === $default ? Content::forget('message.design.' . $name) : Content::set('message.design.' . $name, $design);
        $ok = ($color === '' ? Content::forget('message.color.' . $name) : Content::set('message.color.' . $name, $color)) && $ok;
        $ok = ($seconds === self::DEFAULT_SECONDS ? Content::forget('message.seconds.' . $name) : Content::set('message.seconds.' . $name, (string) $seconds)) && $ok;

        return $ok;
    }
}
