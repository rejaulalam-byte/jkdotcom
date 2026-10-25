<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\PageImage;
use App\Core\RichText;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\OpenPages;

/**
 * খোলা পেইজের লেখা (/admin/pages): পূর্ণ ফরম্যাটিং এডিটরে (TinyMCE) সম্পাদনা, ছবি আপলোড, শুরুর লেখায় ফেরা।
 * সংরক্ষণের আগে লেখা RichText দিয়ে ছেঁকে নেওয়া হয়। অনুমতি: "pages"।
 */
final class PagesController
{
    /** সংরক্ষণযোগ্য লেখার সর্বোচ্চ আকার (বাইট)। */
    private const MAX_HTML = 400000;

    public function index(): void
    {
        $admin = AdminAuth::require('pages');
        $pages = [];
        foreach (OpenPages::ALL as $slug => $page) {
            $pages[] = $page + ['slug' => $slug, 'changed' => OpenPages::isChanged($slug)];
        }

        header('Cache-Control: no-store');
        View::render('admin/pages', [
            'admin' => $admin,
            'title' => 'সাইটের লেখা',
            'pages' => $pages,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** @param array<string,mixed> $meta slug = পেইজের নাম */
    public function edit(array $meta = []): void
    {
        $admin = AdminAuth::require('pages');
        $slug = (string) ($meta['slug'] ?? '');
        if (!OpenPages::exists($slug)) {
            http_response_code(404);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'পাওয়া যায়নি', 'message' => 'এই পেইজটি পাওয়া যায়নি।'], 'layouts/admin');

            return;
        }

        // এডিটর নিজের ভেতরে style ব্যবহার করে ও আপলোডের আগে ছবি blob: হিসেবে দেখায়; স্ক্রিপ্টের নিয়ম একই কড়া
        Security::sendCsp([], true);
        header('Cache-Control: no-store');
        View::render('admin/page-edit', [
            'admin' => $admin,
            'title' => 'লেখা সম্পাদনা: ' . OpenPages::ALL[$slug]['title'],
            'slug' => $slug,
            'page' => OpenPages::ALL[$slug],
            'html' => (string) (Session::pull('admin_old_html') ?? OpenPages::raw($slug)),
            'changed' => OpenPages::isChanged($slug),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: slug, html, do (save | reset)। */
    public function save(): void
    {
        $admin = AdminAuth::require('pages');
        $slug = (string) ($_POST['slug'] ?? '');
        if (!OpenPages::exists($slug)) {
            Session::flash('admin_errors', ['এই পেইজটি পাওয়া যায়নি।']);
            redirect(admin_base() . '/pages');
        }
        $back = admin_base() . '/pages/edit/' . $slug;
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। লেখা সংরক্ষণ হয়নি; পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $title = OpenPages::ALL[$slug]['title'];

        if (($_POST['do'] ?? '') === 'reset') {
            if (!Content::forget('page.' . $slug . '.html')) {
                $this->fail($back, 'ফেরানো যায়নি। আবার চেষ্টা করুন।');
            }
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'page_reset', $title);
            Session::flash('admin_ok', 'শুরুর লেখায় ফেরানো হয়েছে।');
            redirect($back);
        }

        $raw = (string) ($_POST['html'] ?? '');
        if (strlen($raw) > self::MAX_HTML) {
            Session::flash('admin_old_html', $raw);
            $this->fail($back, 'লেখা অনেক বড় (সর্বোচ্চ প্রায় ৪০০ KB)। কিছু অংশ কমিয়ে আবার সংরক্ষণ করুন।');
        }
        $clean = RichText::sanitize($raw);
        if (trim(strip_tags($clean, '<img>')) === '') {
            Session::flash('admin_old_html', $raw);
            $this->fail($back, 'পেইজের লেখা খালি রাখা যাবে না।');
        }
        if (!Content::set('page.' . $slug . '.html', $clean)) {
            Session::flash('admin_old_html', $raw);
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }

        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'page_save', $title);
        Session::flash('admin_ok', 'সংরক্ষণ হয়েছে। পেইজে এখনই নতুন লেখা দেখা যাচ্ছে।');
        redirect($back);
    }

    /** POST (এডিটর থেকে): file, _token। উত্তর JSON: {location} অথবা {error}। */
    public function image(): void
    {
        AdminAuth::require('pages');
        header('Content-Type: application/json; charset=utf-8');
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'নিরাপত্তা যাচাই ব্যর্থ। পেইজটি রিফ্রেশ করুন।'], JSON_UNESCAPED_UNICODE);

            return;
        }
        [$url, $error] = PageImage::store($_FILES['file'] ?? null);
        if ($url === null) {
            http_response_code(422);
            echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);

            return;
        }
        echo json_encode(['location' => $url]);
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }
}
