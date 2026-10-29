<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\SiteTheme;

/**
 * রং ও আকার (<admin>/theme): সাইটের থিমের মূল রং বদলানো, নমুনা দেখা, শুরুর রঙে ফেরা (App\Models\SiteTheme)।
 * কাঠামোর কাজ, তাই শুধু সুপার অ্যাডমিন। পড়া কঠিন হতে পারে এমন রঙে সতর্ক করে, তবে সংরক্ষণ আটকায় না।
 */
final class ThemeController
{
    public function index(): void
    {
        $admin = $this->super();
        $colors = [];
        foreach (array_keys(SiteTheme::COLORS) as $name) {
            $colors[$name] = SiteTheme::color($name);
        }
        header('Cache-Control: no-store');
        View::render('admin/theme', [
            'admin' => $admin,
            'title' => 'রং ও আকার',
            'colors' => $colors,
            'warnings' => SiteTheme::warnings($colors),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: color[নাম] = #rrggbb, অথবা reset = নাম / reset_all = 1। */
    public function save(): void
    {
        $admin = $this->super();
        $back = admin_base() . '/theme';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $log = static fn (string $detail) => AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'theme_colors', $detail);

        $reset = (string) ($_POST['reset'] ?? '');
        if ($reset !== '' || ($_POST['reset_all'] ?? '') === '1') {
            $names = $reset !== '' ? [$reset] : array_keys(SiteTheme::COLORS);
            foreach ($names as $name) {
                if (isset(SiteTheme::COLORS[$name])) {
                    SiteTheme::set($name, SiteTheme::COLORS[$name][1]);
                }
            }
            $log('শুরুর রং: ' . implode(', ', $names));
            Session::flash('admin_ok', 'শুরুর রঙে ফেরানো হয়েছে।');
            redirect($back);
        }

        $posted = is_array($_POST['color'] ?? null) ? $_POST['color'] : [];
        $changed = [];
        foreach (SiteTheme::COLORS as $name => [$label]) {
            $hex = strtolower(trim((string) ($posted[$name] ?? '')));
            if ($hex === '' || $hex === SiteTheme::color($name)) {
                continue;
            }
            if (!SiteTheme::valid($hex)) {
                $this->fail($back, '"' . $label . '": রং #১২৩৪৫৬ ধরনে (# আর ৬টি ইংরেজি অক্ষর/সংখ্যা) হতে হবে, যেমন #125a6c।');
            }
            if (!SiteTheme::set($name, $hex)) {
                $this->fail($back, 'সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।');
            }
            $changed[] = $name . '=' . $hex;
        }
        if ($changed !== []) {
            $log(implode(', ', $changed));
        }
        Session::flash('admin_ok', $changed === [] ? 'কোনো রং বদলায়নি।' : 'রং সংরক্ষণ হয়েছে; পুরো সাইটে এখনই নতুন রং।');
        redirect($back);
    }

    /** @return array<string,mixed> */
    private function super(): array
    {
        $admin = AdminAuth::require('pages');
        if (!AdminAuth::isSuper($admin)) {
            Session::flash('admin_errors', ['রং ও আকার বদলানো শুধু সুপার অ্যাডমিন পারেন।']);
            redirect(admin_base());
        }

        return $admin;
    }

    private function fail(string $back, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($back);
    }
}
