<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\SiteTexts;

/**
 * "সাইটের লেখা"-র দুই ট্যাব (/admin/texts): পেইজের ছোট লেখা (শিরোনাম, বাটন, ব্যানার…) ও বার্তা (সফলতা/ভুলের বার্তা,
 * পপ-আপ)। ভাগ ধরে বা খুঁজে লেখা বের করে বদলানো ও শুরুর লেখায় ফেরা। এগুলো সাইটে সবসময় সাধারণ লেখা হিসেবে (escape করে)
 * দেখানো হয়। অনুমতি: "pages"।
 */
final class TextsController
{
    /** ট্যাব => নাম */
    public const TABS = ['page' => 'পেইজের ছোট লেখা', 'msg' => 'বার্তা'];

    public function index(): void
    {
        $admin = AdminAuth::require('pages');
        $tab = isset(self::TABS[$_GET['tab'] ?? '']) ? (string) $_GET['tab'] : 'page';
        $sections = SiteTexts::sectionsFor($tab);
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $index = max(0, min(count($sections) - 1, (int) ($_GET['s'] ?? 0)));

        if ($q !== '') {
            $keys = SiteTexts::search($tab, $q);
            $heading = '"' . $q . '" খোঁজার ফল';
        } else {
            $keys = $sections[$index]['keys'] ?? [];
            $heading = $sections[$index]['title'] ?? '';
        }

        $rows = [];
        foreach ($keys as $key) {
            $rows[] = [
                'key' => $key,
                'value' => Content::get($key),
                'original' => SiteTexts::original($key),
                'changed' => Content::has($key),
                'placeholders' => SiteTexts::placeholders($key),
                'markup' => SiteTexts::usesColorMarkup($key),
            ];
        }

        header('Cache-Control: no-store');
        View::render('admin/texts', [
            'admin' => $admin,
            'title' => 'সাইটের লেখা',
            'tab' => $tab,
            'sections' => $sections,
            'index' => $index,
            'q' => $q,
            'heading' => $heading,
            'rows' => $rows,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: text[কী] = নতুন লেখা (যতগুলো ঘর দেখানো ছিল), অথবা reset = একটা কী (শুরুর লেখায় ফেরা); back। */
    public function save(): void
    {
        $admin = AdminAuth::require('pages');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। কিছু সংরক্ষণ হয়নি; পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $reset = (string) ($_POST['reset'] ?? '');
        if ($reset !== '') {
            if (!SiteTexts::editable($reset) || !Content::forget($reset)) {
                $this->fail($back, 'ফেরানো যায়নি।');
            }
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'text_reset', $reset);
            Session::flash('admin_ok', 'লেখাটি শুরুর রূপে ফেরানো হয়েছে।');
            redirect($back);
        }

        $posted = is_array($_POST['text'] ?? null) ? $_POST['text'] : [];
        $changed = [];
        $errors = [];
        foreach ($posted as $key => $value) {
            $key = (string) $key;
            if (!SiteTexts::editable($key) || !is_string($value)) {
                continue;
            }
            $value = clean_input($value, SiteTexts::MAX_LENGTH, true);
            if ($value === Content::get($key)) {
                continue; // বদলায়নি
            }
            $missing = array_filter(SiteTexts::placeholders($key), static fn (string $p): bool => !str_contains($value, $p));
            if ($missing !== []) {
                $errors[] = '"' . mb_substr(SiteTexts::original($key), 0, 40) . '…" লেখায় ' . implode(', ', $missing) . ' থাকতে হবে (এখানে নাম/এলাকা বসে)। এটা সংরক্ষণ হয়নি।';
                continue;
            }
            if ($value === '') {
                $errors[] = '"' . mb_substr(SiteTexts::original($key), 0, 40) . '…" খালি রাখা যাবে না; এটা সংরক্ষণ হয়নি। আগের লেখায় ফিরতে "শুরুর লেখা" চাপুন।';
                continue;
            }
            // শুরুর লেখার সমান হলে আলাদা করে রাখার দরকার নেই
            $ok = $value === SiteTexts::original($key) ? Content::forget($key) : Content::set($key, $value);
            if ($ok) {
                $changed[] = $key;
            } else {
                $errors[] = 'একটা লেখা সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।';
            }
        }

        if ($changed !== []) {
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'text_save', implode(', ', $changed));
            Session::flash('admin_ok', bn(count($changed)) . 'টি লেখা সংরক্ষণ হয়েছে; সাইটে এখনই দেখা যাচ্ছে।');
        } elseif ($errors === []) {
            Session::flash('admin_ok', 'কোনো লেখা বদলানো হয়নি।');
        }
        if ($errors !== []) {
            Session::flash('admin_errors', $errors);
        }
        redirect($back);
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ, নইলে পেইজের শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/texts';

        return $path === $own || preg_match('#^' . preg_quote($own, '#') . '\?[A-Za-z0-9_=&%.\-+]*$#', $path) === 1 ? $path : $own;
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }
}
