<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\SubscriberRepository;

/**
 * ফুটারের ইমেইল-বাক্সে জমা পড়া ইমেইলের তালিকা (<admin>/subscribers, অনুমতি "inbox"): খোঁজা, CSV ডাউনলোড, একটা মোছা।
 */
final class SubscribersController
{
    private const PER_PAGE = 50;

    public function index(): void
    {
        $admin = AdminAuth::require('inbox');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);

        if (($_GET['csv'] ?? '') === '1') {
            $this->csv($admin, $q);

            return;
        }

        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = SubscriberRepository::adminList($q, self::PER_PAGE, ($page - 1) * self::PER_PAGE);
        header('Cache-Control: no-store');
        View::render('admin/subscribers', [
            'admin' => $admin,
            'title' => 'ইমেইল গ্রাহক',
            'q' => $q,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: id — একটা ইমেইল চিরতরে মোছা (গ্রাহক চাইলে বা ভুল ইমেইল হলে)। */
    public function delete(): void
    {
        $admin = AdminAuth::require('inbox');
        $back = admin_base() . '/subscribers';
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            Session::flash('admin_errors', ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            redirect($back);
        }
        $item = SubscriberRepository::find((int) ($_POST['id'] ?? 0));
        if ($item === null) {
            Session::flash('admin_errors', ['ইমেইলটি পাওয়া যায়নি (হয়তো আগেই মোছা হয়েছে)।']);
            redirect($back);
        }
        if (!SubscriberRepository::delete((int) $item['id'])) {
            Session::flash('admin_errors', ['মোছা যায়নি। আবার চেষ্টা করুন।']);
            redirect($back);
        }
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'subscriber_delete', (string) $item['email']);
        Session::flash('admin_ok', $item['email'] . ' মুছে গেছে।');
        redirect($back);
    }

    /** @param array<string,mixed> $admin */
    private function csv(array $admin, string $q): void
    {
        $rows = SubscriberRepository::adminList($q, -1, 0)['rows'];
        AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'subscribers_csv', bn(count($rows)) . 'টি');
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="subscribers-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF"); // এক্সেলে বাংলা/ইউনিকোড ঠিক দেখানোর জন্য
        fputcsv($out, ['email', 'subscribed_at']);
        foreach ($rows as $r) {
            // সূত্র হিসেবে চলে এমন শুরু (=, +, -, @) আটকাতে সামনে ' বসে
            $email = (string) $r['email'];
            fputcsv($out, [preg_match('/^[=+\-@]/', $email) === 1 ? "'" . $email : $email, (string) $r['created_at']]);
        }
        fclose($out);
    }
}
