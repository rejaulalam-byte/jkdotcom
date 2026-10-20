<?php

declare(strict_types=1);

use App\Controllers\FeedbackController;
use App\Controllers\HomeController;
use App\Controllers\PageController;
use App\Controllers\ServiceController;
use App\Controllers\SubscribeController;

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
    ['GET',  '/about',           [PageController::class, 'soon', ['title' => 'আমাদের সম্পর্কে']]],
    ['GET',  '/services',        [PageController::class, 'soon', ['title' => 'সেবা সমূহ']]],
    ['GET',  '/services/{slug}', [ServiceController::class, 'show']],
    ['GET',  '/today',           [PageController::class, 'soon', ['title' => 'আজকের প্রকাশিত কাজ']]],
    ['GET',  '/today/post',      [PageController::class, 'soon', ['title' => 'কাজ সম্পর্কে জানান']]],
    ['GET',  '/registration',    [PageController::class, 'soon', ['title' => 'রেজিস্ট্রেশন']]],
    ['GET',  '/contact',         [PageController::class, 'soon', ['title' => 'যোগাযোগ']]],
    ['GET',  '/privacy',         [PageController::class, 'soon', ['title' => 'প্রাইভেসি পলিসি']]],
    ['GET',  '/terms',           [PageController::class, 'soon', ['title' => 'শর্তাবলী']]],
];
