<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\MailTemplates;

/**
 * ইমেইলের লেখা (<admin>/mails): সদস্যদের কাছে যাওয়া ছয় রকম ইমেইলের বিষয় ও লেখা দেখা, বদলানো, শুরুর লেখায় ফেরা,
 * আর নিজের ইমেইলে নমুনা পাঠিয়ে দেখা। অনুমতি: "email" (সুপার অ্যাডমিন ও অ্যাডমিনিস্ট্রেটর দুজনেই)।
 * লেখা সাধারণ-লেখার ইমেইল; জায়গা-ধারক ({name} ইত্যাদি) থাকতে হয় ও অচেনা ধারক চলে না (MailTemplates::validate)।
 */
final class MailsController
{
    public function index(): void
    {
        $admin = AdminAuth::require('email');
        $items = [];
        foreach (MailTemplates::TEMPLATES as $id => $template) {
            $items[$id] = $template + ['current' => MailTemplates::current($id), 'original' => MailTemplates::original($id), 'changed' => MailTemplates::changed($id)];
        }

        header('Cache-Control: no-store');
        View::render('admin/mails', [
            'admin' => $admin,
            'title' => 'ইমেইলের লেখা',
            'items' => $items,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: id, do = save | reset | test, subject, body। */
    public function action(): void
    {
        $admin = AdminAuth::require('email');
        $back = admin_base() . '/mails';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $id = (string) ($_POST['id'] ?? '');
        if (!MailTemplates::exists($id)) {
            $this->fail($back, 'এই ইমেইল পাওয়া যায়নি।');
        }
        $back .= '#m-' . $id;
        $title = MailTemplates::TEMPLATES[$id]['title'];
        $log = static fn (string $what) => AdminRepository::log((int) $admin['id'], (string) $admin['name'], $what, $id);
        $do = (string) ($_POST['do'] ?? '');

        if ($do === 'reset') {
            Content::forget('mail.' . $id . '.subject');
            Content::forget('mail.' . $id . '.body');
            $log('mail_reset');
            Session::flash('admin_ok', '"' . $title . '" শুরুর লেখায় ফিরেছে।');
            redirect($back);
        }

        // বিষয় এক লাইনে; লেখায় লাইন-ভাঙা ঠিক রেখে অদরকারি কন্ট্রোল-অক্ষর বাদ
        $clean = static fn (string $v): string => trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', str_replace("\r\n", "\n", $v)));
        $subject = trim((string) preg_replace('/\s+/u', ' ', $clean((string) ($_POST['subject'] ?? ''))));
        $body = $clean((string) ($_POST['body'] ?? ''));
        $error = MailTemplates::validate($id, $subject, $body);
        if ($error !== null) {
            $this->fail($back, $error);
        }

        if ($do === 'test') {
            $mail = [
                'subject' => '[পরীক্ষা] ' . strtr($subject, MailTemplates::sample($id, AdminAuth::baseUrl())),
                'body' => strtr($body, MailTemplates::sample($id, AdminAuth::baseUrl())),
            ];
            if (!AdminAuth::mail((string) $admin['email'], $mail['subject'], $mail['body'])) {
                $this->fail($back, 'পরীক্ষার ইমেইল পাঠানো যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
            }
            $log('mail_test');
            Session::flash('admin_ok', 'নমুনা ইমেইল আপনার ঠিকানায় (' . $admin['email'] . ') পাঠানো হয়েছে। নমুনা মান বসানো; এই লেখা এখনও সংরক্ষণ হয়নি।');
            redirect($back);
        }

        $original = MailTemplates::original($id);
        $ok = $subject === $original['subject'] ? Content::forget('mail.' . $id . '.subject') : Content::set('mail.' . $id . '.subject', $subject);
        $ok = ($body === $original['body'] ? Content::forget('mail.' . $id . '.body') : Content::set('mail.' . $id . '.body', $body)) && $ok;
        if (!$ok) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
        }
        $log('mail_edit');
        Session::flash('admin_ok', '"' . $title . '" সংরক্ষণ হয়েছে; পরের ইমেইল থেকে নতুন লেখা যাবে।');
        redirect($back);
    }

    private function fail(string $back, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($back);
    }
}
