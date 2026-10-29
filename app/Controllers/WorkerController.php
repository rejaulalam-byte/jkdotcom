<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\PhotoUpload;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\ContactTrackingRepository;
use App\Models\GeoRepository;
use App\Models\RatingRepository;
use App\Models\ServiceRepository;
use App\Models\UserRepository;
use App\Models\WorkerRotation;

/**
 * কর্মীর ব্যক্তিগত পেইজ ও তার পেছনের অনুরোধ:
 *  - GET  /workers/<id>      কর্মীর বিস্তারিত তথ্য (ফোন/ইমেইল লুকানো), রেটিং-ফর্ম;
 *  - POST /workers/rate      কাজের মূল্যায়ন (রেটিং) জমা;
 *  - POST /workers/contact   লুকানো ফোন বা ইমেইল দেখানো (কাজের তালিকার /today/contact-এর মতোই দুই ধাপে, JSON)।
 */
final class WorkerController
{
    /** @param array<string,mixed> $meta */
    public function show(array $meta = []): void
    {
        $id = ctype_digit((string) ($meta['slug'] ?? '')) ? (int) $meta['slug'] : 0;
        $user = UserRepository::findPublicWorker($id);
        if ($user === null) {
            http_response_code(404);
            View::render('pages/error', ['title' => msg('msg.not_found'), 'code' => 404]);

            return;
        }

        // তালিকা থেকে এলে ?s=<পেশা>: কোন পেশার তালিকা থেকে এসেছেন তা মনে রাখা হয়, যাতে "কল" ওই তালিকার ক্রমে গোনা হয়
        $extraSlugs = UserRepository::extraServices($user);
        $mySlugs = array_merge([(string) ($user['service_slug'] ?? '')], $extraSlugs);
        $from = (string) ($_GET['s'] ?? '');
        $_SESSION['wk_service'][$id] = in_array($from, $mySlugs, true) ? $from : $mySlugs[0];
        if (!live_preview()) { // অ্যাডমিনের লাইভ দেখা দর্শকের হিসাবে গোনা হয় না
            WorkerRotation::recordVisit($id);
        }

        $service = ServiceRepository::findBySlug((string) ($user['service_slug'] ?? ''));
        $profession = (string) ($user['service_custom'] ?? '') !== '' ? (string) $user['service_custom'] : ($service['name'] ?? '');
        // প্রধান পেশার পরে আরও পেশাগুলো একই লাইনে
        $extraNames = array_filter(array_map(static fn (string $s): string => ServiceRepository::findBySlug($s)['name'] ?? '', $extraSlugs));
        $professions = implode(', ', array_filter([$profession, ...$extraNames]));
        $geo = GeoRepository::names((int) $user['upazila_id'], (int) $user['district_id'], (int) $user['division_id']);
        $address = implode(', ', array_filter(
            [(string) $user['address'], $geo['upazila'], $geo['district'], $geo['division'], 'বাংলাদেশ'],
            static fn (string $part): bool => $part !== ''
        ));
        $email = (string) ($user['email'] ?? '');
        $listUrl = $service !== null ? '/services/' . $service['slug'] : '/services';

        View::render('pages/worker', [
            'bodyClass' => 'page-worker',
            'title' => $user['name'] . ' — Jorurikaj.com',
            'description' => content('worker.title'),
            'heading' => content('worker.banner'),
            'id' => (int) $user['id'],
            'name' => (string) $user['name'],
            'photo' => PhotoUpload::url($user['photo'] !== null ? (string) $user['photo'] : null),
            'profession' => $profession,
            'professions' => $professions,
            'listUrl' => $listUrl,
            'experience' => $user['experience_years'] !== null ? (int) $user['experience_years'] : null,
            'bio' => (string) ($user['bio'] ?? ''),
            'address' => $address . '।',
            'phoneMasked' => mask_phone((string) $user['phone']),
            'emailMasked' => $email !== '' ? mask_email($email) : '',
            'rating' => RatingRepository::summary((int) $user['id']),
            'choices' => RatingRepository::CHOICES,
            'ratingMessage' => Session::pull('rating_message'),
            'token' => Security::csrfToken(),
        ]);
    }

    /** কাজের মূল্যায়ন জমা: একই ব্রাউজার-সেশন একই কর্মীকে একটাই রেটিং দিতে পারে, আবার দিলে বদলে যায়। @param array<string,mixed> $meta */
    public function rate(array $meta = []): void
    {
        $id = (int) ($_POST['id'] ?? 0);
        $back = '/workers/' . $id . '#rating';

        if (!Security::verifyCsrf($_POST['_token'] ?? null) || UserRepository::findPublicWorker($id) === null) {
            redirect($id > 0 ? '/workers/' . $id : '/services');
        }
        if (RateLimiter::tooMany('worker_rate', 10, 3600)) {
            Session::flash('rating_message', ['error', msg('worker.rate.limit')]);
            redirect($back);
        }

        $rating = RatingRepository::parse($_POST['rating'] ?? null);
        if ($rating === null) {
            Session::flash('rating_message', ['error', msg('worker.rate.invalid')]);
            redirect($back);
        }

        $_SESSION['voter'] ??= bin2hex(random_bytes(16));
        $ok = RatingRepository::submit($id, (string) $_SESSION['voter'], $rating);
        if (!$ok) {
            Session::flash('rating_message', ['error', msg('worker.rate.error')]);
        } elseif (msg_on('worker.rate.thanks')) {
            Session::flash('rating_message', ['ok', msg('worker.rate.thanks')]);
        }
        redirect($back);
    }
    /**
     * step=click: প্রতিটি ক্লিক একটা "কল" হিসেবে গোনা হয়; দর্শক আগে পরিচয় দিয়ে থাকলে যোগাযোগ দেখায়, নইলে {need_lead: true}।
     * step=lead: দর্শকের নাম + মোবাইল সংরক্ষণ, অ্যাডমিনকে ইমেইল, তারপর যোগাযোগ দেখানো।
     *
     * @param array<string,mixed> $meta
     */
    public function contact(array $meta = []): void
    {
        $this->jsonHeaders();
        if (!$this->guard('worker_contact', 60)) {
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $kind = (string) ($_POST['kind'] ?? '');
        $step = (string) ($_POST['step'] ?? 'click');
        $user = in_array($kind, ['phone', 'email'], true) ? UserRepository::findPublicWorker($id) : null;
        $value = $user === null ? '' : (string) ($kind === 'phone' ? $user['phone'] : ($user['email'] ?? ''));
        if ($user === null || $value === '') {
            $this->reply(['error' => 'none'], 404);

            return;
        }

        $show = static fn (): array => $kind === 'phone'
            ? ['kind' => 'phone', 'value' => format_phone($value), 'href' => 'tel:+88' . normalize_phone($value)]
            : ['kind' => 'email', 'value' => $value, 'href' => 'mailto:' . $value];

        if ($step === 'click') {
            ContactTrackingRepository::click('worker', $id, $kind);
            if (!empty($_SESSION['contact_ok']['worker'][$id])) {
                $this->countCall($id, $user);
            }
            $this->reply(!empty($_SESSION['contact_ok']['worker'][$id]) ? $show() : ['need_lead' => true]);

            return;
        }

        // ধাপ = lead
        if (RateLimiter::tooMany('worker_lead', 10, 3600)) {
            $this->reply(['error' => 'limit'], 429);

            return;
        }
        $name = clean_input($_POST['name'] ?? '', 100);
        $phone = normalize_phone(clean_input($_POST['phone'] ?? '', 20));
        if (mb_strlen($name) < 2) {
            $this->reply(['error' => 'invalid', 'message' => msg('msg.name')], 422);

            return;
        }
        if (!preg_match('/^01[3-9][0-9]{8}$/', $phone)) {
            $this->reply(['error' => 'invalid', 'message' => msg('msg.phone_bad')], 422);

            return;
        }
        if (!ContactTrackingRepository::lead('worker', $id, $kind, $name, $phone)) {
            $this->reply(['error' => 'save', 'message' => msg('msg.save_failed_short')], 500);

            return;
        }
        $_SESSION['contact_ok']['worker'][$id] = true;
        $this->countCall($id, $user);

        // অ্যাডমিন জানতে পান, যাতে প্রয়োজনে কর্মীকে ফোন করে যাচাই করতে পারেন
        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = 'কেউ একজন পেশাজীবীর ' . ($kind === 'phone' ? 'ফোন নাম্বার' : 'ইমেইল') . " দেখতে চেয়েছেন — jorurikaj.com\n\n"
            . 'পেশাজীবী: ' . $user['name'] . ' (' . format_phone((string) $user['phone']) . ")\n\n"
            . "যিনি আগ্রহ দেখিয়েছেন:\nনাম: {$name}\nমোবাইল: {$phone}\n\n"
            . "(প্রয়োজনে পেশাজীবীকে ফোন করে জেনে নিন এই নামে কেউ খোঁজ নিয়েছেন কিনা।)\n";
        if (!Mailer::send($to, 'পেশাজীবীর যোগাযোগ দেখতে চেয়েছেন: ' . $user['name'], $body)) {
            Logger::error('Worker contact-lead e-mail could not be sent');
        }

        $this->reply($show());
    }

    /** "কল" গোনা: ঘূর্ণি ক্রমে কর্মী ওই পেশার তালিকায় নিচে নামেন (পেশা না থাকলে কিছু হয় না)। @param array<string,mixed> $user */
    private function countCall(int $id, array $user): void
    {
        $slug = (string) ($_SESSION['wk_service'][$id] ?? $user['service_slug'] ?? '');
        if ($slug !== '') {
            WorkerRotation::recordCall($id, $slug);
        }
    }

    private function jsonHeaders(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
    }

    /** CSRF ও অনুরোধের সংখ্যার সীমা দেখে; ঠিক থাকলে true, নইলে উত্তর পাঠিয়ে false। */
    private function guard(string $bucket, int $perHour): bool
    {
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->reply(['error' => 'csrf'], 403);

            return false;
        }
        if (RateLimiter::tooMany($bucket, $perHour, 3600)) {
            $this->reply(['error' => 'limit'], 429);

            return false;
        }

        return true;
    }

    /** @param array<string,mixed> $body */
    private function reply(array $body, int $status = 200): void
    {
        http_response_code($status);
        echo json_encode($body, JSON_UNESCAPED_UNICODE);
    }
}
