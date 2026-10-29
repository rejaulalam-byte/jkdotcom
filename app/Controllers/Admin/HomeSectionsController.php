<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\PageImage;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\HomeSections;

/**
 * হোমের সেকশন (কাঠামোর কাজ, তাই শুধু সুপার অ্যাডমিন):
 *   live — লাইভ পেইজ এডিটের ফ্রেমের পট্টি থেকে: ওপরে/নিচে সরানো, লুকানো/দেখানো, নতুন সেকশন যোগ, মোছা (JSON উত্তর);
 *   edit / save — নতুন সেকশনের নিজের পাতা (<admin>/home-section/<id>): ছোট শিরোনাম ও লোগো, বড় শিরোনাম, জায়গা,
 *                 পূর্ণ এডিটরের লেখা, বাটন। সংরক্ষণের পরে লাইভ পেইজ এডিটের হোমে ফেরে।
 */
final class HomeSectionsController
{
    /** POST JSON: _token, id, do = up | down | hide | show | add-above | add-below | delete। */
    public function live(): void
    {
        $admin = AdminAuth::require('pages');
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        $answer = static function (bool $ok, ?string $error = null): never {
            echo json_encode($ok ? ['ok' => true] : ['ok' => false, 'error' => $error], JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(403);
            $answer(false, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। অ্যাডমিন পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        if (!AdminAuth::isSuper($admin)) {
            http_response_code(403);
            $answer(false, 'হোমের সেকশন সাজানো শুধু সুপার অ্যাডমিন পারেন।');
        }
        $id = (string) ($_POST['id'] ?? '');
        $do = (string) ($_POST['do'] ?? '');
        if (!HomeSections::exists($id)) {
            http_response_code(422);
            $answer(false, 'সেকশনটি পাওয়া যায়নি; পেইজটি রিফ্রেশ করুন।');
        }
        $label = HomeSections::label($id);
        $error = match ($do) {
            'up', 'down' => HomeSections::move($id, $do),
            'hide' => HomeSections::setHidden($id, true),
            'show' => HomeSections::setHidden($id, false),
            'add-above', 'add-below' => HomeSections::add($id, $do === 'add-above', $made),
            'delete' => HomeSections::delete($id),
            default => 'অজানা কাজ।',
        };
        if ($error !== null) {
            $answer(false, $error);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'home_section_' . str_replace('-', '_', $do),
            $label . (isset($made) ? ' → নতুন ' . $made : ''));
        $answer(true);
    }

    public function edit(array $meta = []): void
    {
        $admin = $this->super();
        $id = (string) ($meta['slug'] ?? '');
        $data = HomeSections::data($id);
        if ($data === null) {
            Session::flash('admin_errors', ['সেকশনটি পাওয়া যায়নি।']);
            redirect(admin_base() . '/studio?p=home');
        }
        $old = Session::pull('admin_old_section');
        // পূর্ণ এডিটর নিজের ভেতরে style ও blob: ছবি ব্যবহার করে; স্ক্রিপ্টের নিয়ম একই কড়া
        Security::sendCsp([], true);
        header('Cache-Control: no-store');
        View::render('admin/home-section', [
            'admin' => $admin,
            'title' => 'হোমের নতুন সেকশন',
            'id' => $id,
            'data' => is_array($old) ? $old['data'] + $data : $data,
            'html' => is_array($old) ? (string) $old['html'] : HomeSections::rawHtml($id),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    public function save(array $meta = []): void
    {
        $admin = $this->super();
        $id = (string) ($meta['slug'] ?? '');
        $current = HomeSections::data($id);
        $self = admin_base() . '/home-section/' . $id;
        if ($current === null) {
            redirect(admin_base() . '/studio?p=home');
        }
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            Session::flash('admin_errors', ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। কিছু সংরক্ষণ হয়নি; পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            redirect($self);
        }

        $data = [
            'badge' => clean_input($_POST['badge'] ?? '', 80),
            'heading' => clean_input($_POST['heading'] ?? '', 200),
            'align' => (string) ($_POST['align'] ?? 'center'),
            'icon' => $current['icon'],
            'button_text' => clean_input($_POST['button_text'] ?? '', 60),
            'button_url' => trim((string) ($_POST['button_url'] ?? '')),
            'button_align' => (string) ($_POST['button_align'] ?? 'center'),
        ];
        $html = (string) ($_POST['html'] ?? '');
        $keepOld = static function (string $message) use ($data, $html, $self): never {
            Session::flash('admin_old_section', ['data' => $data, 'html' => $html]);
            Session::flash('admin_errors', [$message]);
            redirect($self);
        };
        if (strlen($html) > 400000) {
            $keepOld('লেখা অনেক বড় (সর্বোচ্চ প্রায় ৪০০ KB)।');
        }

        // লোগো: নতুন ছবি, অথবা সাধারণ লোগোয় ফেরা
        if (($_POST['icon_reset'] ?? '') === '1') {
            $data['icon'] = '';
        } elseif (($_FILES['icon']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$url, $error] = PageImage::store($_FILES['icon']);
            if ($url === null) {
                $keepOld('লোগো: ' . $error);
            }
            $data['icon'] = $url;
        }

        $error = HomeSections::update($id, $data, $html);
        if ($error !== null) {
            $keepOld($error);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'home_section_edit', HomeSections::label($id));
        Session::flash('admin_ok', 'সেকশন সংরক্ষণ হয়েছে; হোমে এখনই দেখা যাচ্ছে।');
        redirect(admin_base() . '/studio?p=home');
    }

    /** @return array<string,mixed> */
    private function super(): array
    {
        $admin = AdminAuth::require('pages');
        if (!AdminAuth::isSuper($admin)) {
            Session::flash('admin_errors', ['হোমের সেকশন সাজানো শুধু সুপার অ্যাডমিন পারেন।']);
            redirect(admin_base());
        }

        return $admin;
    }
}
