<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\FeedbackController;
use App\Controllers\HomeController;
use App\Controllers\PasswordResetController;
use App\Controllers\PageController;
use App\Controllers\ProfileController;
use App\Controllers\RegistrationController;
use App\Controllers\ServiceController;
use App\Controllers\SubscribeController;
use App\Controllers\TodayController;

/*
 * ওয়েবসাইটের প্রতিটি সর্বজনীন URL।
 * ধরন: [HTTP মেথড, পাথ, [Controller::class, 'মেথড', ঐচ্ছিক বাড়তি তথ্য]]
 * (বাড়তি তথ্য যেমন ['role' => 'agent'] কন্ট্রোলারের মেথডে $meta হয়ে পৌঁছায়।)
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
    ['GET',  '/registration/professional/form', [RegistrationController::class, 'form', ['role' => 'worker']]],
    ['POST', '/registration/professional/form', [RegistrationController::class, 'store', ['role' => 'worker']]],
    ['GET',  '/registration/agent',        [PageController::class, 'registrationAgent']],
    ['GET',  '/registration/agent/form',   [RegistrationController::class, 'form', ['role' => 'agent']]],
    ['POST', '/registration/agent/form',   [RegistrationController::class, 'store', ['role' => 'agent']]],
    ['GET',  '/login',                     [AuthController::class, 'loginForm', ['role' => 'worker']]],
    ['POST', '/login',                     [AuthController::class, 'loginStore', ['role' => 'worker']]],
    ['GET',  '/login/agent',               [AuthController::class, 'loginForm', ['role' => 'agent']]],
    ['POST', '/login/agent',               [AuthController::class, 'loginStore', ['role' => 'agent']]],
    ['POST', '/logout',                    [AuthController::class, 'logout']],
    ['POST', '/password/forgot',           [PasswordResetController::class, 'request']],
    ['POST', '/password/verify',           [PasswordResetController::class, 'verify']],
    ['POST', '/password/reset',            [PasswordResetController::class, 'reset']],
    ['GET',  '/welcome',                   [AuthController::class, 'welcome']],
    ['GET',  '/account/password',          [AuthController::class, 'passwordForm']],
    ['POST', '/account/password',          [AuthController::class, 'passwordStore']],
    ['GET',  '/photo/{slug}',              [AuthController::class, 'photo']],
    ['GET',  '/profile',                   [ProfileController::class, 'form']],
    ['POST', '/profile',                   [ProfileController::class, 'update']],
    ['GET',  '/dashboard',                 [ProfileController::class, 'dashboardRedirect']],
    ['GET',  '/contact',         [PageController::class, 'soon', ['title' => 'যোগাযোগ']]],
    ['GET',  '/privacy',         [PageController::class, 'soon', ['title' => 'প্রাইভেসি পলিসি']]],
    ['GET',  '/terms',           [PageController::class, 'soon', ['title' => 'শর্তাবলী']]],
];
