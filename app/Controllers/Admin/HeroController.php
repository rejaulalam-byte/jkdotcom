<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\PageImage;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\SiteHero;

/**
 * হিরো সাজানো (<admin>/hero; কাঠামোর কাজ, তাই শুধু সুপার অ্যাডমিন): লেখার অ্যানিমেশনের ধরন, পটভূমি (সাধারণ / নিজের রং / ছবি)
 * আর ডানের "আজকের কাজের তালিকা" দেখানো-লুকানো। লাইভ পেইজ এডিটে হোমের হিরো-পট্টির "হিরো সেটিং" বাটন থেকে আসা যায়।
 */
final class HeroController
{
    public function index(): void
    {
        $admin = $this->super();
        header('Cache-Control: no-store');
        View::render('admin/hero', [
            'admin' => $admin,
            'title' => 'হিরো সেটিং',
            'animOn' => SiteHero::animating(),
            'anim' => SiteHero::anim(),
            'colorSource' => SiteHero::colorSource(),
            'imageOn' => SiteHero::imageOn(),
            'color1' => SiteHero::color1() !== '' ? SiteHero::color1() : '#125a6c',
            'color2' => SiteHero::color2(),
            'direction' => SiteHero::direction(),
            'image' => SiteHero::image(),
            'overlay' => SiteHero::overlay(),
            'showToday' => SiteHero::showToday(),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: anim_on (1/0), anim, bg (default|color), color1, color2 (ঐচ্ছিক), dir, image_on (1/0), image (নতুন ছবি), remove_image, overlay, today (1/0)। */
    public function save(): void
    {
        $admin = $this->super();
        $back = admin_base() . '/hero';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $anim = (string) ($_POST['anim'] ?? 'scatter');
        $bg = (string) ($_POST['bg'] ?? 'default');
        $dir = (string) ($_POST['dir'] ?? 'down');
        $overlay = (int) ($_POST['overlay'] ?? 30);
        if (!isset(SiteHero::ANIMS[$anim]) || !in_array($bg, ['default', 'color'], true)
            || !isset(SiteHero::DIRECTIONS[$dir]) || !in_array($overlay, SiteHero::OVERLAYS, true)) {
            $this->fail($back, 'অনুমোদিত তালিকার বাইরের মান পাঠানো হয়েছে; পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $color1 = SiteHero::hex((string) ($_POST['color1'] ?? ''));
        $color2 = !empty($_POST['use_color2']) ? SiteHero::hex((string) ($_POST['color2'] ?? '')) : '';
        if ($bg === 'color' && $color1 === '') {
            $this->fail($back, 'রং ঠিক নয়; রঙের ঘর থেকে একটা রং বাছুন (# দিয়ে শুরু ছয় অক্ষরের কোড)।');
        }

        // ছবি: নতুন দিলে আগেরটা মুছে যায়; "সরান" টিক দিলে সরে
        $oldImage = SiteHero::image();
        $newImage = $oldImage;
        if (!empty($_FILES['image']) && ($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            [$url, $error] = PageImage::store($_FILES['image']);
            if ($url === null) {
                $this->fail($back, (string) $error);
            }
            $newImage = $url;
        } elseif (!empty($_POST['remove_image'])) {
            $newImage = '';
        }
        $imageOn = !empty($_POST['image_on']);
        if ($imageOn && $newImage === '') {
            $this->fail($back, 'ছবি চালু করতে হলে আগে একটা ছবি বাছুন।');
        }

        $ok = Content::set('hero.anim_on', !empty($_POST['anim_on']) ? '1' : '0')
            && Content::set('hero.anim', $anim)
            && Content::set('hero.bg', $bg)
            && Content::set('hero.color1', $color1)
            && Content::set('hero.color2', $color2)
            && Content::set('hero.dir', $dir)
            && Content::set('hero.image', $newImage)
            && Content::set('hero.image_on', $imageOn ? '1' : '0')
            && Content::set('hero.overlay', (string) $overlay)
            && Content::set('hero.today_show', !empty($_POST['today']) ? '1' : '0');
        if (!$ok) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
        }
        if ($oldImage !== '' && $oldImage !== $newImage) {
            PageImage::remove($oldImage);
        }

        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'hero_save',
            'অ্যানিমেশন: ' . (!empty($_POST['anim_on']) ? $anim : 'বন্ধ') . ' — রং: ' . $bg . ' — ছবি: ' . ($imageOn ? 'চালু' : 'বন্ধ') . ' — আজকের কাজের তালিকা: ' . (!empty($_POST['today']) ? 'দেখায়' : 'লুকানো'));
        Session::flash('admin_ok', 'হিরোর সেটিং সংরক্ষিত; লাইভ পেইজ এডিটে হোম পেইজে দেখে নিন।');
        redirect($back);
    }

    /** @return array<string,mixed> */
    private function super(): array
    {
        $admin = AdminAuth::require('pages');
        if (!AdminAuth::isSuper($admin)) {
            http_response_code(403);
            exit('হিরো সাজানো শুধু সুপার অ্যাডমিন পারেন।');
        }

        return $admin;
    }

    private function fail(string $back, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($back);
    }
}
