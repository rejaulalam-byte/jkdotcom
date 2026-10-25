<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdImage;
use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\PageImage;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdRepository;
use App\Models\AdminRepository;
use App\Models\PopupAd;

/**
 * হোমের বিজ্ঞাপন-স্ট্রিপ (/admin/ads): নতুন বিজ্ঞাপন (ছবি + ঐচ্ছিক লিংক ও তারিখ), বদলানো, চালু/বন্ধ, ক্রম, মোছা এবং
 * "স্ট্রিপ দেখাও" সুইচ (home.ads.show), আর পপ-আপ বিজ্ঞাপন (PopupAd)। স্ট্রিপের ছবির নিয়ম AdImage-এ। অনুমতি: "ads"।
 */
final class AdsController
{
    public function index(): void
    {
        $admin = AdminAuth::require('ads');

        header('Cache-Control: no-store');
        View::render('admin/ads', [
            'admin' => $admin,
            'title' => 'বিজ্ঞাপন',
            'ads' => AdRepository::all(),
            'stripOn' => Content::get('home.ads.show', '1') !== '0',
            'popup' => PopupAd::settings(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
            'old' => Session::pull('admin_old', []),
        ], 'layouts/admin');
    }

    /** POST: id (০ = নতুন), title, link, start, end, sort (বদলানোর সময়), active, image (নতুনে আবশ্যক)। */
    public function save(): void
    {
        $admin = AdminAuth::require('ads');
        $this->checkCsrf();

        $id = (int) ($_POST['id'] ?? 0);
        $ad = $id > 0 ? AdRepository::find($id) : null;
        if ($id > 0 && $ad === null) {
            $this->fail('বিজ্ঞাপনটি পাওয়া যায়নি।');
        }

        $title = clean_input($_POST['title'] ?? '', 150);
        $linkRaw = clean_input($_POST['link'] ?? '', 500);
        $link = $linkRaw === '' ? null : Security::safeUrl($linkRaw);
        $start = $this->date($_POST['start'] ?? '');
        $end = $this->date($_POST['end'] ?? '');
        $old = ['id' => $id, 'title' => $title, 'link' => $linkRaw, 'start' => (string) ($_POST['start'] ?? ''), 'end' => (string) ($_POST['end'] ?? '')];

        $errors = [];
        if (mb_strlen($title) < 2) {
            $errors[] = 'বিজ্ঞাপনের নাম লিখুন (যেমন বিজ্ঞাপনদাতার নাম); এটা ছবির বিকল্প-লেখা হিসেবেও যায়।';
        }
        if ($linkRaw !== '' && $link === null) {
            $errors[] = 'লিংক শুধু http:// বা https:// দিয়ে শুরু হওয়া সঠিক ওয়েব-ঠিকানা হতে পারে।';
        }
        if ($start === false || $end === false) {
            $errors[] = 'তারিখ সঠিক নয়।';
        } elseif ($start !== null && $end !== null && $end < $start) {
            $errors[] = 'শেষের তারিখ শুরুর তারিখের আগে হতে পারে না।';
        }

        [$file, $width, $height, $imageError] = AdImage::store($_FILES['image'] ?? null);
        if ($imageError !== null) {
            $errors[] = $imageError;
        } elseif ($file === null && $ad === null) {
            $errors[] = 'বিজ্ঞাপনের ছবি দিন।';
        }
        if ($errors !== []) {
            if ($file !== null) {
                AdImage::remove($file);
            }
            Session::flash('admin_old', $old);
            $this->fail(implode(' ', $errors));
        }

        $columns = [
            'title' => $title, 'link_url' => $link,
            'starts_at' => $start === null ? null : $start . ' 00:00:00', 'ends_at' => $end === null ? null : $end . ' 23:59:59',
        ];
        if ($file !== null) {
            $columns += ['image_path' => $file, 'width' => $width, 'height' => $height];
        }

        if ($ad === null) {
            $newId = AdRepository::create($columns + ['is_active' => empty($_POST['active']) ? 0 : 1]);
            if ($newId === 0) {
                AdImage::remove((string) $file);
                $this->fail('সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
            }
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'ad_create', $title);
            $this->ok('"' . $title . '" যোগ হয়েছে।');
        }

        if (isset($_POST['sort']) && $_POST['sort'] !== '') {
            $columns['sort_order'] = max(0, min(9999, (int) $_POST['sort']));
        }
        if (!AdRepository::update($id, $columns)) {
            if ($file !== null) {
                AdImage::remove($file);
            }
            $this->fail('সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        if ($file !== null) {
            AdImage::remove((string) $ad['image_path']);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'ad_update', $title);
        $this->ok('"' . $title . '" সংরক্ষিত হয়েছে।');
    }

    /** POST: id, do (activate | deactivate | delete)। */
    public function action(): void
    {
        $admin = AdminAuth::require('ads');
        $this->checkCsrf();

        $ad = AdRepository::find((int) ($_POST['id'] ?? 0));
        if ($ad === null) {
            $this->fail('বিজ্ঞাপনটি পাওয়া যায়নি (হয়তো আগেই মোছা হয়েছে)।');
        }
        $title = (string) $ad['title'];
        $do = (string) ($_POST['do'] ?? '');

        [$done, $log, $message] = match ($do) {
            'activate' => [AdRepository::update((int) $ad['id'], ['is_active' => 1]), 'ad_activate', 'চালু করা হলো।'],
            'deactivate' => [AdRepository::update((int) $ad['id'], ['is_active' => 0]), 'ad_deactivate', 'বন্ধ করা হলো; হোমে আর দেখাবে না।'],
            'delete' => [AdRepository::delete((int) $ad['id']), 'ad_delete', 'চিরতরে মুছে গেছে।'],
            default => [false, '', ''],
        };
        if ($log === '') {
            $this->fail('অজানা কাজ।');
        }
        if (!$done) {
            $this->fail('সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        if ($do === 'delete') {
            AdImage::remove((string) $ad['image_path']);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $log, $title);
        $this->ok('"' . $title . '" — ' . $message);
    }

    /** POST: on (1 = হোমে বিজ্ঞাপন-স্ট্রিপ দেখাও, 0 = লুকাও) — `home.ads.show`। */
    public function show(): void
    {
        $admin = AdminAuth::require('ads');
        $this->checkCsrf();

        $on = ($_POST['on'] ?? '') === '1';
        if (!Content::set('home.ads.show', $on ? '1' : '0')) {
            $this->fail('সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $on ? 'ads_strip_on' : 'ads_strip_off', 'হোমের বিজ্ঞাপন-স্ট্রিপ');
        $this->ok($on ? 'হোমে বিজ্ঞাপন-স্ট্রিপ দেখানো হবে।' : 'হোমে বিজ্ঞাপন-স্ট্রিপ লুকানো হলো (বিজ্ঞাপনগুলো থাকছে)।');
    }

    /** POST: active, pages[], link, title, image (নতুন ছবি, ঐচ্ছিক), remove_image — পপ-আপ বিজ্ঞাপন। */
    public function popup(): void
    {
        $admin = AdminAuth::require('ads');
        $this->checkCsrf();

        $linkRaw = clean_input($_POST['link'] ?? '', 500);
        $link = $linkRaw === '' ? '' : (string) Security::safeUrl($linkRaw);
        if ($linkRaw !== '' && $link === '') {
            $this->fail('লিংক শুধু http:// বা https:// দিয়ে শুরু হওয়া সঠিক ওয়েব-ঠিকানা হতে পারে।');
        }
        $pages = array_map('strval', is_array($_POST['pages'] ?? null) ? $_POST['pages'] : []);
        $active = !empty($_POST['active']);

        $image = null;
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$url, $error] = PageImage::store($_FILES['image']);
            if ($url === null) {
                $this->fail((string) $error);
            }
            [$w, $h] = PageImage::dimensions($url);
            $image = ['url' => $url, 'width' => $w, 'height' => $h];
        } elseif (!empty($_POST['remove_image'])) {
            $image = '';
        }
        if ($active && $image === '') {
            $active = false;
        }
        if ($active && $image === null && PopupAd::settings()['image'] === '') {
            $this->fail('চালু করার আগে একটা ছবি দিন।');
        }
        if ($active && $pages === []) {
            $this->fail('কোন পেইজে দেখাবে, অন্তত একটায় টিক দিন।');
        }

        if (!PopupAd::save($active, $pages, $link, clean_input($_POST['title'] ?? '', 150), $image)) {
            $this->fail('সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'popup_save', ($active ? 'চালু' : 'বন্ধ') . ' — ' . implode(', ', $pages));
        $this->ok($active ? 'পপ-আপ বিজ্ঞাপন চালু; বাছা পেইজগুলো খুললেই দেখাবে।' : 'পপ-আপ বিজ্ঞাপন সংরক্ষিত (বন্ধ আছে)।');
    }

    /** "Y-m-d" তারিখ; ফাঁকা হলে null, ভুল হলে false। */
    private function date(mixed $value): string|false|null
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $d !== false && $d->format('Y-m-d') === $value ? $value : false;
    }

    private function checkCsrf(): void
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail('নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
    }

    private function fail(string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect(admin_base() . '/ads');
    }

    private function ok(string $message): never
    {
        Session::flash('admin_ok', $message);
        redirect(admin_base() . '/ads');
    }
}
