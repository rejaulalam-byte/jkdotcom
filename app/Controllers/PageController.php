<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Session;
use App\Core\View;

/** নকশা-করা কিন্তু এখনও না-বানানো পেইজে একটা ভদ্র "coming soon" পেইজ দেখায়; সঙ্গে সাধারণ লেখার পেইজ। */
final class PageController
{
    /** @param array<string,mixed> $meta */
    public function soon(array $meta = []): void
    {
        $title = (string) ($meta['title'] ?? 'শীঘ্রই আসছে');
        View::render('pages/coming-soon', ['title' => $title, 'heading' => $title]);
    }

    /** রেজিস্ট্রেশন নিয়মাবলীর ওভারভিউ (/registration): কারা রেজিস্ট্রেশন করতে পারবেন। */
    public function registrationRules(): void
    {
        View::render('pages/registration-rules', [
            'bodyClass' => 'page-regrules',
            'title' => 'রেজিস্ট্রেশন নিয়মাবলী — Jorurikaj.com',
            'description' => 'পেশাজীবী, সেবাগ্রহীতা ও প্রতিনিধি—কারা Jorurikaj.com-এ রেজিস্ট্রেশন করতে পারবেন।',
            'heading' => Content::get('reg.rules.title'),
            'bodyHtml' => Content::get('page.registration-rules.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/registration-rules.html')),
        ]);
    }

    /** পেশাজীবীদের রেজিস্ট্রেশন-নির্দেশিকা (/registration/professional); ফর্ম থাকবে .../form-এ। */
    public function registrationProfessional(): void
    {
        View::render('pages/registration-professional', [
            'bodyClass' => 'page-regrules page-regguide',
            'title' => 'পেশাজীবীদের রেজিস্ট্রেশন নির্দেশিকা — Jorurikaj.com',
            'description' => 'পেশাজীবী হিসেবে Jorurikaj.com-এ রেজিস্ট্রেশনের যোগ্যতা, শর্তাবলি ও নিয়মাবলী।',
            'heading' => Content::get('reg.rules.title'),
            'bodyHtml' => Content::get('page.registration-professional.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/registration-professional.html')),
        ]);
    }

    /** প্রতিনিধির রেজিস্ট্রেশন-নির্দেশিকা (/registration/agent); ফর্ম থাকবে .../form-এ। */
    public function registrationAgent(): void
    {
        View::render('pages/registration-agent', [
            'bodyClass' => 'page-regrules page-regguide',
            'title' => 'প্রতিনিধি রেজিস্ট্রেশনের নিয়মাবলী — Jorurikaj.com',
            'description' => 'এলাকার প্রতিনিধি (এজেন্ট) হিসেবে Jorurikaj.com-এর সঙ্গে কাজ করার যোগ্যতা, দায়িত্ব ও শর্তাবলি।',
            'heading' => Content::get('reg.rules.title'),
            'bodyHtml' => Content::get('page.registration-agent.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/registration-agent.html')),
        ]);
    }

    /** যোগাযোগ পেইজ (/contact): ঠিকানা-কার্ড, প্রধান নির্বাহীর পরিচিতি, মতামতের ফর্ম ও গুগল ম্যাপ। */
    public function contact(): void
    {
        View::render('pages/contact', [
            'bodyClass' => 'page-contact',
            'title' => 'যোগাযোগ — Jorurikaj.com',
            'description' => Content::get('contact.intro'),
            'heading' => Content::get('contact.banner'),
            'feedbackOk' => Session::pull('feedback_ok'),
            'feedbackErrors' => Session::pull('feedback_errors', []),
            'old' => Session::pull('feedback_old', []),
        ]);
    }
    /** আমাদের সম্পর্কে পেইজ (/about)। */
    public function about(): void
    {
        View::render('pages/about', [
            'bodyClass' => 'page-about',
            'title' => 'আমাদের সম্পর্কে — Jorurikaj.com',
            'description' => 'মানুষের প্রয়োজনে, মানুষের পাশে — Jorurikaj.com-এর পরিচয় ও লক্ষ্য।',
            'heading' => 'আমাদের পরিচয়, আমাদের লক্ষ্য',
            'bodyHtml' => Content::get('page.about.html', (string) file_get_contents(BASE_PATH . '/app/Content/pages/about.html')),
        ]);
    }
}
