<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\FeedbackRepository;

/**
 * দর্শকের মতামতের ইনবক্স (/admin/feedback): পড়া, "রাখা" চিহ্ন, চিরতরে মোছা। অনুমতি: "inbox"।
 */
final class FeedbackInboxController
{
    private const PER_PAGE = 20;

    public function index(): void
    {
        $admin = AdminAuth::require('inbox');
        $tab = isset(FeedbackRepository::TABS[$_GET['t'] ?? '']) ? (string) ($_GET['t'] ?? '') : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = FeedbackRepository::adminList($tab, $q, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        header('Cache-Control: no-store');
        View::render('admin/feedback', [
            'admin' => $admin,
            'title' => 'মতামত',
            'tab' => $tab,
            'q' => $q,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => FeedbackRepository::counts(),
            'open' => (int) ($_GET['open'] ?? 0),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** POST: id, do (read | unread | keep | unkeep | delete), back। */
    public function action(): void
    {
        $admin = AdminAuth::require('inbox');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $id = (int) ($_POST['id'] ?? 0);
        $item = FeedbackRepository::find($id);
        if ($item === null) {
            $this->fail($back, 'মতামতটি পাওয়া যায়নি (হয়তো আগেই মোছা হয়েছে)।');
        }
        $who = (string) $item['name'];
        $do = (string) ($_POST['do'] ?? '');

        [$done, $log, $message] = match ($do) {
            'read' => [FeedbackRepository::setFlag($id, 'is_read', true), 'feedback_read', 'পড়া হয়েছে বলে চিহ্নিত।'],
            'unread' => [FeedbackRepository::setFlag($id, 'is_read', false), 'feedback_unread', 'আবার "নতুন" করা হলো।'],
            'keep' => [FeedbackRepository::setFlag($id, 'is_kept', true), 'feedback_keep', 'রাখা হলো (রাখা ট্যাবে পাবেন)।'],
            'unkeep' => [FeedbackRepository::setFlag($id, 'is_kept', false), 'feedback_unkeep', '"রাখা" চিহ্ন তোলা হলো।'],
            'delete' => [FeedbackRepository::delete($id), 'feedback_delete', 'চিরতরে মুছে গেছে।'],
            default => [false, '', ''],
        };
        if ($log === '') {
            $this->fail($back, 'অজানা কাজ।');
        }
        if (!$done) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }

        AdminRepository::log((int) $admin['id'], (string) $admin['name'], $log, $who . ' — ' . (string) $item['subject']);
        $this->ok($back, $who . ' — ' . $message);
    }

    /** ফেরার পথ: শুধু এই পেইজের নিজের পাথ (ট্যাব, খোঁজা, পৃষ্ঠা-সহ), নইলে পেইজের শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/feedback';

        return $path === $own || preg_match('#^' . preg_quote($own, '#') . '\?[A-Za-z0-9_=&%.\-]*$#', $path) === 1 ? $path : $own;
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }

    private function ok(string $path, string $message): never
    {
        Session::flash('admin_ok', $message);
        redirect($path);
    }
}
