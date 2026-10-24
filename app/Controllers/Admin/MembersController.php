<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\PhotoUpload;
use App\Core\View;
use App\Models\GeoRepository;
use App\Models\MemberStatus;
use App\Models\ServiceRepository;
use App\Models\UserRepository;

/**
 * অ্যাডমিন প্যানেলে কর্মী ও প্রতিনিধির তালিকা (/admin/workers, /admin/agents) ও বিস্তারিত পেইজ (/admin/members/<id>)।
 * অনুমতি: "registrations"। এই ধাপে শুধু দেখা (অনুমোদন, হোল্ড, মোছা, সম্পাদনা পরের ধাপে)।
 */
final class MembersController
{
    private const PER_PAGE = 25;

    /** @param array<string,mixed> $meta role => 'worker'|'agent' */
    public function list(array $meta = []): void
    {
        $admin = AdminAuth::require('registrations');
        $role = ($meta['role'] ?? '') === 'agent' ? 'agent' : 'worker';

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

        $counts = UserRepository::countByRoleStatus()[$role] ?? [];
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
            'counts' => $counts,
            'services' => $services,
            'errors' => [],
            'ok' => null,
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
            'errors' => [],
            'ok' => null,
        ], 'layouts/admin');
    }
}
