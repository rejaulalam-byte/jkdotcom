<?php

declare(strict_types=1);

use App\Controllers\FeedbackController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\ServiceController;
use App\Controllers\SubscribeController;
use App\Controllers\TodayController;

/*
 * ওয়েবসাইটের প্রতিটি সর্বজনীন URL।
 * ধরন: [HTTP মেথড, পাথ, [Controller::class, 'মেথড'], ঐচ্ছিক বাড়তি তথ্য]
 * নতুন পেইজ যোগ করতে: কন্ট্রোলারের মেথড + ভিউ বানান, তারপর এখানে এক লাইন যোগ করুন।
 */
return [
    ['GET',  '/',                [HomeController::class, 'index']],
    ['POST', '/feedback',        [FeedbackController::class, 'store']],
    ['POST', '/subscribe',       [SubscribeController::class, 'store']],

    // Figma-তে নকশা করা পেইজ; একটা একটা করে বানানো হচ্ছে (PROGRESS.md দেখুন)
    ['GET',  '/about',           [PageController::class, 'about']],
    ['GET',  '/services',        [ServiceController::class, 'index']],
    ['GET',  '/services/{slug}', [ServiceController::class, 'show']],
    ['GET',  '/today',           [TodayController::class, 'index']],
    ['POST', '/today/contact',   [TodayController::class, 'contact']],
    ['GET',  '/today/post',      [TodayController::class, 'postForm']],
    ['POST', '/today/post',      [TodayController::class, 'postStore']],
    ['GET',  '/registration',    [PageController::class, 'registrationRules']],
    ['GET',  '/registration/professional', [PageController::class, 'registrationProfessional']],
    ['GET',  '/registration/professional/form', [PageController::class, 'soon', ['title' => 'আগ্রহী পেশাদার কর্মী রেজিস্ট্রেশন']]],
    ['GET',  '/registration/agent',        [PageController::class, 'registrationAgent']],
    ['GET',  '/registration/agent/form',   [PageController::class, 'soon', ['title' => 'প্রতিনিধি রেজিস্ট্রেশন']]],
    ['GET',  '/login',                     [PageController::class, 'soon', ['title' => 'লগইন']]],
    ['GET',  '/dashboard',                 [PageController::class, 'soon', ['title' => 'ড্যাশবোর্ড']]],
    ['GET',  '/contact',         [PageController::class, 'soon', ['title' => 'যোগাযোগ']]],
    ['GET',  '/privacy',         [PageController::class, 'soon', ['title' => 'প্রাইভেসি পলিসি']]],
    ['GET',  '/terms',           [PageController::class, 'soon', ['title' => 'শর্তাবলী']]],
];
