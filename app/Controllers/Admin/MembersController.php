<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\PhotoUpload;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\MailTemplates;
use App\Models\GeoRepository;
use App\Models\MemberStatus;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * অ্যাডমিন প্যানেলে কর্মী ও প্রতিনিধির তালিকা (/admin/workers, /admin/agents), বিস্তারিত পেইজ (/admin/members/<id>)
 * এবং অবস্থা-বদলের কাজ (অনুমোদন, হোল্ড, বাতিল, মোছা তিন ধাপে, ফিরিয়ে আনা)। অনুমতি: "registrations"।
 * চিরতরে মোছা শুধু সুপার অ্যাডমিনের। সম্পাদনা, ফোন-টিক ও অসম্পূর্ণ-তথ্য পরের ধাপে।
 */
final class MembersController
{
    private const PER_PAGE = 25;

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function list(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $role = ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';
        // পুরনো সংরক্ষিত/ডাস্টবিন ঝেড়ে ফেলা (আলাদা সময়-ভিত্তিক কাজ নেই)
        $swept = UserRepository::sweep();
        if ($swept['trashed'] + $swept['purged'] > 0) {
            AdminRepository::log((int) $admin['id'], (string) $admin['name'], 'sweep', $swept['trashed'] . ' জন ডাস্টবিনে, ' . $swept['purged'] . ' জন চিরতরে মোছা');
        }

        $status = (string) ($_GET['status'] ?? '');
        $status = isset(MemberStatus::LABELS[$status]) ? $status : '';
        $source = in_array($_GET['source'] ?? '', ['legacy', 'new'], true) ? (string) $_GET['source'] : '';
        $filter = [
            'status' => $status,
            'q' => mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100),
            'source' => $source,
            'nophone' => !empty($_GET['nophone']),
        ];
        $page = max(1, (int) ($_GET['p'] ?? 1));
        $found = UserRepository::adminSearch($role, $filter, self::PER_PAGE, ($page - 1) * self::PER_PAGE);

        $services = [];
        foreach (ServiceRepository::all() as $s) {
            $services[$s['slug']] = $s['name'];
        }

        header('Cache-Control: no-store');
        View::render('admin/members', [
            'admin' => $admin,
            'title' => $role === 'agent' ? 'প্রতিনিধি' : 'কর্মী (পেশাজীবী)',
            'role' => $role,
            'filter' => $filter,
            'rows' => $found['rows'],
            'total' => $found['total'],
            'page' => $page,
            'pages' => max(1, (int) ceil($found['total'] / self::PER_PAGE)),
            'counts' => UserRepository::countByRoleStatus()[$role] ?? [],
            'services' => $services,
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /** @param array<string,mixed> $meta slug = সদস্যের id */
    public function show(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $id = ctype_digit((string) ($meta['slug'] ?? '')) ? (int) $meta['slug'] : 0;
        $member = $id > 0 ? UserRepository::findById($id) : null;
        if ($member === null) {
            http_response_code(404);
            View::render('admin/forbidden', ['admin' => $admin, 'title' => 'পাওয়া যায়নি', 'message' => 'এই সদস্যকে পাওয়া যায়নি।'], 'layouts/admin');

            return;
        }

        $service = (string) ($member['service_slug'] ?? '') !== '' ? ServiceRepository::findBySlug((string) $member['service_slug']) : null;
        $extra = [];
        foreach (UserRepository::extraServices($member) as $slug) {
            $extra[] = ServiceRepository::findBySlug($slug)['name'] ?? $slug;
        }
        $work = UserRepository::workDistricts($member);

        header('Cache-Control: no-store');
        View::render('admin/member', [
            'admin' => $admin,
            'title' => (string) $member['name'],
            'member' => $member,
            'geo' => GeoRepository::names((int) $member['upazila_id'], (int) $member['district_id'], (int) $member['division_id']),
            'serviceName' => $service['name'] ?? '',
            'extraServices' => $extra,
            'workText' => GeoRepository::workDistrictsText($work['ids'], $work['all']),
            'photoUrl' => PhotoUpload::url($member['photo'] ?? null),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin');
    }

    /**
     * POST /admin/members/action: id, action (approve|hold|reject|reserve|trash|restore|purge), note (ঐচ্ছিক), back।
     * কাজটা সদস্যের এখনকার অবস্থা থেকে চলে কি না দেখে (MemberStatus::ACTIONS); অনুমোদনে সদস্যকে ইমেইল যায়; সবকিছু লগে লেখা হয়।
     */
    public function action(): void
    {
        $admin = AdminAuth::require('registrations');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }

        $member = UserRepository::findById((int) ($_POST['id'] ?? 0));
        $action = (string) ($_POST['action'] ?? '');
        if ($member === null) {
            $this->fail($back, 'সদস্যকে পাওয়া যায়নি।');
        }
        $name = (string) $member['name'];
        $current = (string) $member['status'];
        $note = clean_input($_POST['note'] ?? '', 255);
        $logName = (string) $admin['name'];

        if ($action === 'purge') {
            if (!AdminAuth::isSuper($admin)) {
                $this->fail($back, 'চিরতরে মোছা শুধু সুপার অ্যাডমিন পারেন।');
            }
            if ($current !== 'trashed') {
                $this->fail($back, 'চিরতরে মোছা যায় শুধু ডাস্টবিনের সদস্যকে।');
            }
            UserRepository::purge((int) $member['id']) ?: $this->fail($back, 'মোছা যায়নি। আবার চেষ্টা করুন।');
            AdminRepository::log((int) $admin['id'], $logName, 'member_purged', $name);
            $this->ok(admin_base() . '/' . ($member['role'] === 'agent' ? 'agents' : 'workers') . '?status=trashed', $name . ' চিরতরে মুছে গেছে।');
        }

        if (!isset(MemberStatus::ACTIONS[$action])) {
            $this->fail($back, 'অজানা কাজ।');
        }
        [$from, $to] = MemberStatus::ACTIONS[$action];
        if (!in_array($current, $from, true)) {
            $this->fail($back, 'এই অবস্থা (' . MemberStatus::label($current) . ') থেকে এই কাজ চলে না।');
        }
        if ($action === 'restore') {
            $previous = (string) ($member['previous_status'] ?? '');
            $to = in_array($previous, UserRepository::LISTED_STATUSES, true) ? $previous : 'pending';
        }

        if (!UserRepository::setStatus((int) $member['id'], $to, $action === 'reject' ? $note : null)) {
            $this->fail($back, 'সংরক্ষণ করা যায়নি। আবার চেষ্টা করুন।');
        }
        AdminRepository::log((int) $admin['id'], $logName, 'member_' . $action, $name . ($note !== '' ? ' — ' . $note : ''));

        $message = $name . ' — এখন "' . MemberStatus::label($to) . '"।';
        if ($action === 'reject') {
            $message .= $this->sendRejectionMail($member, clean_input($_POST['member_reason'] ?? '', 255))
                ? ' বাতিলের ইমেইল পাঠানো হয়েছে।'
                : ((string) ($member['email'] ?? '') === '' ? ' (ইমেইল নেই, তাই ইমেইল যায়নি; ফোনে জানান।)' : ' (ইমেইল পাঠানো যায়নি।)');
        }
        if ($action === 'approve') {
            $message .= $this->sendApprovalMail($member)
                ? ' অনুমোদনের ইমেইল পাঠানো হয়েছে।'
                : ((string) ($member['email'] ?? '') === '' ? ' (ইমেইল নেই, তাই ইমেইল যায়নি; ফোনে জানান।)' : ' (ইমেইল পাঠানো যায়নি।)');
        }
        $this->ok($back, $message);
    }

    /**
     * অনুমোদনের ইমেইল: অভিনন্দন, লগইন আইডি, লগইন ও (কর্মীর) নিজের পেইজের লিংক। পাসওয়ার্ড কখনও নয় (আমরা শুধু হ্যাশ রাখি)।
     * ইমেইল না থাকলে কিছু যায় না, false ফেরে।
     *
     * @param array<string,mixed> $member
     */
    private function sendApprovalMail(array $member): bool
    {
        $email = (string) ($member['email'] ?? '');
        if ($email === '') {
            return false;
        }
        $isWorker = $member['role'] === 'worker';
        $base = AdminAuth::baseUrl();
        $loginId = (string) ($member['phone'] ?? '') !== '' ? format_phone((string) $member['phone']) . ' অথবা ' . $email : $email;
        // বিষয় ও লেখা অ্যাডমিনের "ইমেইলের লেখা" পেইজ থেকে (App\Models\MailTemplates)
        $mail = MailTemplates::render($isWorker ? 'approval_worker' : 'approval_agent', [
            '{name}' => honorific_name($member['gender'] ?? null, (string) $member['name']),
            '{login_id}' => $loginId,
            '{login_url}' => $base . ($isWorker ? '/login' : '/login/agent'),
            '{profile_url}' => $base . '/profile',
            '{public_url}' => $base . '/workers/' . (int) $member['id'],
            '{helpline}' => content('today.post.helpline'),
        ]);

        return AdminAuth::mail($email, $mail['subject'], $mail['body']);
    }

    /**
     * বাতিলের ভদ্র ইমেইল। $reason (সদস্যকে জানানোর কারণ) ফাঁকা হলে কারণ ছাড়া সংক্ষিপ্ত রূপ যায়।
     * অ্যাডমিনের নিজের নোট (status_note) কখনও সদস্যকে পাঠানো হয় না। ইমেইল না থাকলে false।
     *
     * @param array<string,mixed> $member
     */
    private function sendRejectionMail(array $member, string $reason): bool
    {
        $email = (string) ($member['email'] ?? '');
        if ($email === '') {
            return false;
        }
        $mail = MailTemplates::render($reason !== '' ? 'reject_reason' : 'reject', [
            '{name}' => honorific_name($member['gender'] ?? null, (string) $member['name']),
            '{reason}' => $reason,
            '{helpline}' => content('today.post.helpline'),
        ]);

        return AdminAuth::mail($email, $mail['subject'], $mail['body']);
    }

    /** কাজ শেষে ফেরার পথ: শুধু /admin/ দিয়ে শুরু হওয়া নিজের পাথ, নইলে কর্মীর তালিকা। */
    private function backPath(string $path): string
    {
        return preg_match('#^' . preg_quote(admin_base(), '#') . '/[A-Za-z0-9\/_\-?=&%.]*$#', $path) === 1 ? $path : admin_base() . '/workers';
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
