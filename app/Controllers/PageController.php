<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Content;
use App\Core\Session;
use App\Core\View;
use App\Models\CustomPages;
use App\Models\OpenPages;

/** নকশা-করা কিন্তু এখনও না-বানানো পেইজে একটা ভদ্র "coming soon" পেইজ দেখায়; সঙ্গে সাধারণ লেখার পেইজ। */
final class PageController
{
    /** @param array<string,mixed> $meta */
    public function soon(array $meta = []): void
    {
        $title = (string) ($meta['title'] ?? 'শীঘ্রই আসছে');
        View::render('pages/coming-soon', ['title' => $title, 'heading' => $title, 'notice' => (string) ($meta['notice'] ?? 'পেজটি তৈরির কাজ চলছে'), 'text' => (string) ($meta['text'] ?? 'এই পেজটি খুব শীঘ্রই চালু হবে। ততক্ষণ হোম পেজ ঘুরে দেখুন।')]);
    }

    /** রেজিস্ট্রেশন নিয়মাবলীর ওভারভিউ (/registration): কারা রেজিস্ট্রেশন করতে পারবেন। */
    public function registrationRules(): void
    {
        View::render('pages/registration-rules', [
            'bodyClass' => 'page-regrules',
            'title' => 'রেজিস্ট্রেশন নিয়মাবলী — Jorurikaj.com',
            'description' => 'পেশাজীবী, সেবাগ্রহীতা ও প্রতিনিধি—কারা Jorurikaj.com-এ রেজিস্ট্রেশন করতে পারবেন।',
            'heading' => Content::get('reg.rules.title'),
            'bodyHtml' => OpenPages::html('registration-rules'),
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
            'bodyHtml' => OpenPages::html('registration-professional'),
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
            'bodyHtml' => OpenPages::html('registration-agent'),
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
    /** প্রশ্ন ও উত্তর পেইজ (/faq): ২০টি প্রশ্ন-উত্তর ৪টি শিরোনামের নিচে (একসঙ্গে একটাই খোলা) ও পাশে মতামতের ফর্ম। */
    public function faq(): void
    {
        View::render('pages/faq', [
            'bodyClass' => 'page-faq',
            'title' => 'প্রশ্ন ও উত্তর — Jorurikaj.com',
            'description' => Content::get('faq.intro'),
            'heading' => Content::get('faq.banner'),
            // ৪টি শিরোনামের নিচে ৫টি করে প্রশ্ন (FAQ.docx-এর ক্রম)
            'faqGroups' => array_map(
                static fn (int $g): array => ['title' => Content::get("faq.group.{$g}"), 'keys' => range(($g - 1) * 5 + 1, $g * 5)],
                [1, 2, 3, 4]
            ),
            'feedbackOk' => Session::pull('feedback_ok'),
            'feedbackErrors' => Session::pull('feedback_errors', []),
            'old' => Session::pull('feedback_old', []),
        ]);
    }
    /**
     * আইনি লেখার পেইজ: গোপনীয়তা নীতিমালা (/privacy) ও ব্যবহারের শর্তাবলি (/terms)।
     *
     * @param array<string,mixed> $meta ['page' => 'privacy'|'terms']
     */
    public function legal(array $meta = []): void
    {
        $page = ($meta['page'] ?? '') === 'terms' ? 'terms' : 'privacy';

        View::render('pages/legal', [
            'bodyClass' => 'page-legal',
            'title' => Content::get("legal.{$page}.crumb") . ' — Jorurikaj.com',
            'description' => Content::get("legal.{$page}.description"),
            'heading' => Content::get("legal.{$page}.title"),
            'crumb' => Content::get("legal.{$page}.crumb"),
            'bodyHtml' => OpenPages::html($page),
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
            'bodyHtml' => OpenPages::html('about'),
        ]);
    }

    /**
     * অ্যাডমিনের বানানো নতুন পেইজ (/page/<slug>, App\Models\CustomPages): দেখতে আইনি পেইজের মতো (ব্যানার + খোলা লেখা)।
     * লুকানো পেইজ সাধারণ দর্শক পান না ("পাওয়া যায়নি"); শুধু অ্যাডমিনের লাইভ পেইজ এডিটের ফ্রেমে দেখা যায়, যাতে আগে লেখা যায়।
     */
    public function custom(array $meta = []): void
    {
        $page = CustomPages::find((string) ($meta['slug'] ?? ''));
        if ($page === null || ($page['hidden'] && !live_preview())) {
            http_response_code(404);
            View::render('pages/error', ['title' => msg('msg.not_found'), 'code' => 404]);
            return;
        }

        View::render('pages/legal', [
            'bodyClass' => 'page-legal',
            'title' => $page['name'] . ' — Jorurikaj.com',
            'description' => $page['description'] !== '' ? $page['description'] : Content::get('site.description'),
            'heading' => $page['heading'],
            'crumb' => $page['name'],
            'bodyHtml' => OpenPages::html(CustomPages::OPEN_PREFIX . $page['slug']),
        ]);
    }
}
