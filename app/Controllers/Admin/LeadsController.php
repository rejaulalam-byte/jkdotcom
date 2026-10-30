<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\View;
use App\Models\ContactTrackingRepository;

/**
 * আগ্রহীর তালিকা (/admin/leads): কোন দর্শক কোন কাজের বিজ্ঞপ্তি বা কর্মীর ফোন/ইমেইল দেখতে নিজের নাম ও মোবাইল রেখে গেছেন।
 * অ্যাডমিন চাইলে বিজ্ঞপ্তিদাতা বা কর্মীকে ফোন করে যাচাই করতে পারেন। শুধু দেখা, বদলানোর কিছু নেই। অনুমতি: "inbox"।
 */
final class LeadsController
{
    private const PER_PAGE = 30;

    /** তালিকার ট্যাব: কী => নাম। */
    public const TABS = ['' => 'সব', 'job' => 'কাজের বিজ্ঞপ্তি', 'worker' => 'কর্মী'];

    public function index(): void
    {
        $admin = AdminAuth::require('inbox');
        $type = isset(self::TABS[$_GET['t'] ?? '']) ? (string) ($_GET['t'] ?? '') : '';
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = ContactTrackingRepository::leadsList($type, $q, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        header('Cache-Control: no-store');
        View::render('admin/leads', [
            'admin' => $admin,
            'title' => 'আগ্রহীর তালিকা',
            'tabs' => self::TABS,
            'type' => $type,
            'q' => $q,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => ContactTrackingRepository::leadsCounts(),
        ], 'layouts/admin');
    }
}
