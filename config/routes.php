<?php

declare(strict_types=1);

use App\Controllers\FeedbackController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\ServiceController;
use App\Controllers\SubscribeController;
use App\Controllers\TodayController;

/*
 * Every public URL of the website.
 * Format: [HTTP method, path, [Controller::class, 'method'], optional extra data]
 * To add a page: create the controller method + view, then add one line here.
 */
return [
    ['GET',  '/',                [HomeController::class, 'index']],
    ['POST', '/feedback',        [FeedbackController::class, 'store']],
    ['POST', '/subscribe',       [SubscribeController::class, 'store']],

    // Designed in Figma, to be built page by page (see PROGRESS.md)
    ['GET',  '/about',           [PageController::class, 'about']],
    ['GET',  '/services',        [ServiceController::class, 'index']],
    ['GET',  '/services/{slug}', [ServiceController::class, 'show']],
    ['GET',  '/today',           [TodayController::class, 'index']],
    ['POST', '/today/contact',   [TodayController::class, 'contact']],
    ['GET',  '/today/post',      [TodayController::class, 'postForm']],
    ['POST', '/today/post',      [TodayController::class, 'postStore']],
    ['GET',  '/registration',    [PageController::class, 'registrationRules']],
    ['GET',  '/registration/professional', [PageController::class, 'soon', ['title' => 'আগ্রহী পেশাদার কর্মী রেজিস্ট্রেশন']]],
    ['GET',  '/registration/agent',        [PageController::class, 'soon', ['title' => 'প্রতিনিধি রেজিস্ট্রেশন']]],
    ['GET',  '/login',                     [PageController::class, 'soon', ['title' => 'লগইন']]],
    ['GET',  '/dashboard',                 [PageController::class, 'soon', ['title' => 'ড্যাশবোর্ড']]],
    ['GET',  '/contact',         [PageController::class, 'soon', ['title' => 'যোগাযোগ']]],
    ['GET',  '/privacy',         [PageController::class, 'soon', ['title' => 'প্রাইভেসি পলিসি']]],
    ['GET',  '/terms',           [PageController::class, 'soon', ['title' => 'শর্তাবলী']]],
];
