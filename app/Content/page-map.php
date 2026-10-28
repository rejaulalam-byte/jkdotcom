<?php

declare(strict_types=1);

/*
 * "সাইটের লেখা"-র পেইজ-মানচিত্র: অ্যাডমিনের বাঁয়ের তালিকায় কোন পেইজ কোন ক্রমে, আর প্রতিটি পেইজের ভেতরে কোন লেখা
 * কোন অংশে, পেইজে ওপর থেকে নিচে যে ক্রমে দেখা যায় সেই ক্রমে।
 *   id       — অ্যাডমিনের ঠিকানায় (?p=id) ব্যবহার হয়
 *   title    — তালিকার নাম;  url — সাইটের ঠিকানা (থাকলে "সাইটে দেখুন" লিংক)
 *   open     — খোলা পেইজ হলে তার slug (App\Models\OpenPages): পূর্ণ এডিটর বসে; open_after = এডিটরের আগে কয়টি অংশ
 *   parts    — [অংশের নাম, [লেখার কী…]]; "home.hero.*" মানে ওই নামে শুরু হওয়া সব কী (defaults.php-এর ক্রমে)
 *   children — ভেতরের পেইজ (তালিকায় একটু ভেতরে সরে দেখায়)
 * বার্তা-ধরনের কী (App\Models\SiteTexts::isMessage) নিজে নিজে পেইজের শেষে "এই পেইজের বার্তা" অংশে যায়।
 * এখানে না-থাকা নতুন কী তালিকার শেষে "অন্যান্য লেখা"-য় আসে, তাই কোনো লেখা হারায় না।
 */
return [
    ['id' => 'common', 'title' => 'সব পেইজে একই', 'parts' => [
        ['সাইটের নাম ও ওপরের পট্টি', ['site.title', 'site.description', 'site.brand', 'site.brand_suffix', 'site.tagline', 'site.top_tagline_brand', 'site.top_tagline', 'site.phone_top', 'site.email']],
        ['যোগাযোগের তথ্য (ফুটার ও যোগাযোগ পেইজ)', ['site.email_registration', 'site.phone_footer', 'site.hotline', 'site.website', 'site.address_1', 'site.address_2', 'site.address_3', 'site.coordinates']],
        ['সামাজিক মাধ্যমের ঠিকানা', ['site.social.*']],
        ['ফুটার', ['footer.*']],
        ['লগইনের পর ছবির মেনু', ['nav.*']],
        ['সব ফর্মে একই', ['gender.*', 'geo.*', 'input.bn.*', 'form.msg.*', 'consent.error']],
        ['সার্ভারের বার্তা (সব ফর্মে একই; মতামত ও ফুটারের ইমেইল-ঘরও)', ['msg.*', 'feedback.msg.*', 'subscribe.msg.*']],
    ]],
    ['id' => 'home', 'title' => 'হোম', 'url' => '/', 'parts' => [
        ['হিরো (একদম ওপরের বড় অংশ)', ['home.hero.*']],
        ['হিরোর ডান পাশের "আজকের কাজের তালিকা"', ['home.today.*']],
        ['বিজ্ঞাপন ও চলমান প্রতিনিধি-পট্টি', ['home.ads.label', 'home.rep.text']],
        ['সেবা সেকশন', ['home.services.*']],
        ['পরিচয় সেকশন', ['home.about.*']],
        ['ধাপ সেকশন', ['home.steps.*']],
        ['প্রশ্ন-উত্তর ও মতামত', ['home.faq.*', 'home.feedback.*', 'home.map.*']],
    ]],
    ['id' => 'about', 'title' => 'আমাদের সম্পর্কে', 'url' => '/about', 'open' => 'about', 'parts' => []],
    ['id' => 'services', 'title' => 'সেবা সমূহ', 'url' => '/services', 'parts' => [
        ['পেইজের লেখা', ['services.*']],
    ], 'children' => [
        ['id' => 'workers', 'title' => 'একটা সেবার কর্মী-তালিকা', 'url' => '/services/electrician', 'parts' => [
            ['তালিকার লেখা', ['workers.*']],
        ]],
        ['id' => 'worker', 'title' => 'কর্মীর ব্যক্তিগত পেইজ', 'parts' => [
            ['পেইজের লেখা', ['worker.*']],
        ]],
    ]],
    ['id' => 'today', 'title' => 'আজকের প্রকাশিত', 'parts' => [], 'children' => [
        ['id' => 'today-list', 'title' => 'কাজ সম্পর্কে জানুন', 'url' => '/today', 'parts' => [
            ['তালিকার লেখা', ['today.list.*']],
            ['ফোন/ইমেইল দেখার আগে আগ্রহের পপ-আপ', ['today.lead.*']],
        ]],
        ['id' => 'today-post', 'title' => 'কাজ সম্পর্কে জানান', 'url' => '/today/post', 'parts' => [
            ['ফর্মের লেখা', ['today.post.*']],
            ['সম্মতির লেখা', ['consent.post.*']],
        ]],
    ]],
    ['id' => 'registration', 'title' => 'রেজিস্ট্রেশন', 'parts' => [], 'children' => [
        ['id' => 'reg-rules', 'title' => 'রেজিস্ট্রেশন সম্পর্কিত তথ্যাবলী', 'url' => '/registration', 'open' => 'registration-rules', 'open_after' => 1, 'parts' => [
            ['শিরোনাম ও তিন বাটন', ['reg.rules.*', 'reg.tab.*']],
        ]],
        ['id' => 'reg-pro', 'title' => 'পেশাজীবী নির্দেশিকা', 'url' => '/registration/professional', 'open' => 'registration-professional', 'parts' => [
            ['নিচের ছবি-কার্ডের অংশ', ['reg.pro.*']],
        ]],
        ['id' => 'reg-agent', 'title' => 'প্রতিনিধি নির্দেশিকা', 'url' => '/registration/agent', 'open' => 'registration-agent', 'parts' => []],
        ['id' => 'form-worker', 'title' => 'পেশাজীবীদের রেজিস্ট্রেশন ফর্ম', 'url' => '/registration/professional/form', 'parts' => [
            ['ব্যানার ও শিরোনাম', ['regform.worker.*']],
            ['ঘরের নাম (দুই ফর্মে একই)', ['regform.f.*', 'regform.service.*', 'regform.password_note', 'regform.wd.*', 'regform.es.*', 'regform.msg.*']],
            ['ছবির ঘর', ['regform.photo.*']],
            ['সম্মতি ও জমা', ['consent.worker.*', 'regform.submit']],
        ]],
        ['id' => 'form-agent', 'title' => 'প্রতিনিধি রেজিস্ট্রেশন ফর্ম', 'url' => '/registration/agent/form', 'parts' => [
            ['ব্যানার ও শিরোনাম', ['regform.agent.*']],
            ['সম্মতি', ['consent.agent.*']],
        ]],
        ['id' => 'login', 'title' => 'লগইন (দুটি) ও পাসওয়ার্ড ভুলে গেলে', 'url' => '/login', 'parts' => [
            ['লগইন পেইজ', ['login.*']],
            ['"পাসওয়ার্ড মনে নেই" পপ-আপ', ['forgot.*']],
        ]],
        ['id' => 'welcome', 'title' => 'লগইনের পরের স্বাগত পেইজ', 'parts' => [['পেইজের লেখা', ['welcome.*']]]],
        ['id' => 'password', 'title' => 'পাসওয়ার্ড পরিবর্তন', 'parts' => [['পেইজের লেখা', ['password.*']]]],
        ['id' => 'setup', 'title' => 'পুরনো কর্মীর প্রথম লগইন', 'parts' => [['পেইজের লেখা', ['setup.*']]]],
        ['id' => 'profile', 'title' => 'প্রোফাইল', 'parts' => [['পেইজের লেখা', ['profile.*']]]],
    ]],
    ['id' => 'contact', 'title' => 'যোগাযোগ', 'url' => '/contact', 'parts' => [
        ['পেইজের লেখা', ['contact.*']],
    ]],
    ['id' => 'faq', 'title' => 'প্রশ্ন ও উত্তর', 'url' => '/faq', 'parts' => [
        ['ব্যানার ও ভূমিকা', ['faq.banner', 'faq.intro']],
        ['চারটি ভাগের নাম', ['faq.group.*']],
        ['প্রশ্ন ও উত্তর (প্রথম ৫টি হোমেও দেখায়)', ['faq.1.*', 'faq.2.*', 'faq.3.*', 'faq.4.*', 'faq.5.*', 'faq.6.*', 'faq.7.*', 'faq.8.*', 'faq.9.*', 'faq.10.*',
            'faq.11.*', 'faq.12.*', 'faq.13.*', 'faq.14.*', 'faq.15.*', 'faq.16.*', 'faq.17.*', 'faq.18.*', 'faq.19.*', 'faq.20.*']],
    ]],
    ['id' => 'privacy', 'title' => 'গোপনীয়তা নীতিমালা', 'url' => '/privacy', 'open' => 'privacy', 'open_after' => 1, 'parts' => [
        ['ব্যানার', ['legal.privacy.*']],
    ]],
    ['id' => 'terms', 'title' => 'ব্যবহারের শর্তাবলি', 'url' => '/terms', 'open' => 'terms', 'open_after' => 1, 'parts' => [
        ['ব্যানার', ['legal.terms.*']],
    ]],
];
