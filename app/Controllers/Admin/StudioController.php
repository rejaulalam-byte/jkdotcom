<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\AdminAuth;
use App\Core\Content;
use App\Core\PageImage;
use App\Core\RichText;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;
use App\Models\AdminRepository;
use App\Models\OpenPages;
use App\Models\PageMap;
use App\Models\SiteTexts;

/**
 * "সাইটের লেখা" (/admin/studio): মূল অ্যাডমিন মেনু ছাড়া নিজস্ব পুরো পর্দা। বাঁয়ে সাইটের পেইজের তালিকা (PageMap),
 * ডানে বাছা পেইজের সব লেখা পেইজের ক্রমে, খোলা পেইজে পূর্ণ এডিটরসহ। সব এক ফর্মে সংরক্ষণ। অনুমতি: "pages"।
 * ছোট লেখা সাইটে সাধারণ লেখা (escape করা); পূর্ণ লেখা RichText দিয়ে ছেঁকে সংরক্ষণ।
 */
final class StudioController
{
    /** খোলা পেইজের পূর্ণ লেখার সর্বোচ্চ আকার (বাইট)। */
    private const MAX_HTML = 400000;

    public function index(): void
    {
        $admin = AdminAuth::require('pages');
        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $node = PageMap::find((string) ($_GET['p'] ?? 'home')) ?? PageMap::find('home');

        if ($q !== '') {
            $owners = PageMap::owners();
            $parts = [['title' => '"' . $q . '" খোঁজার ফল', 'keys' => SiteTexts::search($q), 'messages' => false]];
            $node = ['id' => '', 'title' => 'খোঁজা', 'parts' => []];
        } else {
            $owners = [];
            $parts = PageMap::parts($node);
        }

        $rows = [];
        foreach ($parts as $i => $part) {
            foreach ($part['keys'] as $key) {
                $rows[$i][] = [
                    'key' => $key,
                    'label' => SiteTexts::label($key),
                    'value' => Content::get($key),
                    'original' => SiteTexts::original($key),
                    'changed' => Content::has($key),
                    'placeholders' => SiteTexts::placeholders($key),
                    'markup' => SiteTexts::usesColorMarkup($key),
                    'owner' => $owners[$key] ?? null,
                ];
            }
        }

        $open = isset($node['open']) && OpenPages::exists((string) $node['open']) ? (string) $node['open'] : null;
        // পূর্ণ এডিটর নিজের ভেতরে style ও blob: ছবি ব্যবহার করে; স্ক্রিপ্টের নিয়ম একই কড়া
        Security::sendCsp([], true);
        header('Cache-Control: no-store');
        View::render('admin/studio', [
            'admin' => $admin,
            'title' => 'সাইটের লেখা',
            'tree' => PageMap::tree(),
            'node' => $node,
            'q' => $q,
            'parts' => $parts,
            'rows' => $rows,
            'open' => $open,
            'openAfter' => (int) ($node['open_after'] ?? 0),
            'openHtml' => $open !== null ? (string) (Session::pull('admin_old_html') ?? OpenPages::raw($open)) : '',
            'openChanged' => $open !== null && OpenPages::isChanged($open),
            'errors' => Session::pull('admin_errors', []),
            'ok' => Session::pull('admin_ok'),
        ], 'layouts/admin-studio');
    }

    /**
     * POST: text[কী] = নতুন লেখা; open + html = খোলা পেইজের পূর্ণ লেখা (থাকলে); অথবা reset = একটা কী /
     * reset_open = খোলা পেইজ (শুরুর রূপে ফেরা); back।
     */
    public function save(): void
    {
        $admin = AdminAuth::require('pages');
        $back = $this->backPath((string) ($_POST['back'] ?? ''));
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            $this->fail($back, 'নিরাপত্তা যাচাই ব্যর্থ হয়েছে। কিছু সংরক্ষণ হয়নি; পেইজটি রিফ্রেশ করে আবার চেষ্টা করুন।');
        }
        $logName = (string) $admin['name'];

        // শুরুর রূপে ফেরা (একটা ছোট লেখা বা খোলা পেইজের পুরো লেখা)
        $reset = (string) ($_POST['reset'] ?? '');
        $resetOpen = (string) ($_POST['reset_open'] ?? '');
        if ($reset !== '' || $resetOpen !== '') {
            $ok = $reset !== '' ? SiteTexts::editable($reset) && Content::forget($reset) : OpenPages::exists($resetOpen) && Content::forget('page.' . $resetOpen . '.html');
            if (!$ok) {
                $this->fail($back, 'ফেরানো যায়নি।');
            }
            AdminRepository::log((int) $admin['id'], $logName, 'text_reset', $reset !== '' ? $reset : 'page.' . $resetOpen . '.html');
            Session::flash('admin_ok', 'শুরুর লেখায় ফেরানো হয়েছে।');
            redirect($back);
        }

        $changed = [];
        $errors = [];

        // খোলা পেইজের পুরো লেখা
        $open = (string) ($_POST['open'] ?? '');
        // এডিটর (JS) জানায় লেখা বদলেছে কি না; JS না থাকলে ঘরের লেখা সবসময় নেওয়া হয়
        $htmlChanged = ($_POST['html_js'] ?? '') !== '1' || ($_POST['html_dirty'] ?? '') === '1';
        if ($open !== '' && OpenPages::exists($open) && isset($_POST['html']) && $htmlChanged) {
            $raw = (string) $_POST['html'];
            $clean = strlen($raw) > self::MAX_HTML ? null : RichText::sanitize($raw);
            if ($clean === null) {
                Session::flash('admin_old_html', $raw);
                $errors[] = 'পেইজের পুরো লেখা অনেক বড় (সর্বোচ্চ প্রায় ৪০০ KB); সেটা সংরক্ষণ হয়নি।';
            } elseif (trim(strip_tags($clean, '<img>')) === '') {
                Session::flash('admin_old_html', $raw);
                $errors[] = 'পেইজের পুরো লেখা খালি রাখা যাবে না; সেটা সংরক্ষণ হয়নি।';
            } else {
                if (Content::set('page.' . $open . '.html', $clean)) {
                    $changed[] = 'page.' . $open . '.html';
                } else {
                    $errors[] = 'পেইজের পুরো লেখা সংরক্ষণ করা যায়নি।';
                }
            }
        }

        // ছোট লেখা
        $posted = is_array($_POST['text'] ?? null) ? $_POST['text'] : [];
        foreach ($posted as $key => $value) {
            $key = (string) $key;
            if (!SiteTexts::editable($key) || !is_string($value)) {
                continue;
            }
            $value = clean_input($value, SiteTexts::MAX_LENGTH, true);
            if ($value === Content::get($key)) {
                continue;
            }
            $short = '"' . mb_strimwidth(SiteTexts::original($key), 0, 40, '…') . '"';
            $missing = array_filter(SiteTexts::placeholders($key), static fn (string $p): bool => !str_contains($value, $p));
            if ($missing !== []) {
                $errors[] = $short . ' লেখায় ' . implode(', ', $missing) . ' থাকতে হবে (সেখানে নাম/এলাকা বসে); এটা সংরক্ষণ হয়নি।';
                continue;
            }
            if ($value === '') {
                $errors[] = $short . ' খালি রাখা যাবে না; এটা সংরক্ষণ হয়নি। আগের লেখায় ফিরতে "শুরুর লেখায় ফিরুন" চাপুন।';
                continue;
            }
            // শুরুর লেখার সমান হলে আলাদা করে রাখার দরকার নেই
            if ($value === SiteTexts::original($key) ? Content::forget($key) : Content::set($key, $value)) {
                $changed[] = $key;
            } else {
                $errors[] = $short . ' সংরক্ষণ করা যায়নি।';
            }
        }

        if ($changed !== []) {
            AdminRepository::log((int) $admin['id'], $logName, 'text_save', implode(', ', $changed));
            Session::flash('admin_ok', bn(count($changed)) . 'টি লেখা সংরক্ষণ হয়েছে; সাইটে এখনই দেখা যাচ্ছে।');
        } elseif ($errors === []) {
            Session::flash('admin_ok', 'কোনো লেখা বদলানো হয়নি।');
        }
        if ($errors !== []) {
            Session::flash('admin_errors', $errors);
        }
        redirect($back);
    }

    /** POST (এডিটর থেকে): file, _token। উত্তর JSON: {location} অথবা {error}। */
    public function image(): void
    {
        AdminAuth::require('pages');
        header('Content-Type: application/json; charset=utf-8');
        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            http_response_code(403);
            echo json_encode(['error' => 'নিরাপত্তা যাচাই ব্যর্থ। পেইজটি রিফ্রেশ করুন।'], JSON_UNESCAPED_UNICODE);

            return;
        }
        [$url, $error] = PageImage::store($_FILES['file'] ?? null);
        if ($url === null) {
            http_response_code(422);
            echo json_encode(['error' => $error], JSON_UNESCAPED_UNICODE);

            return;
        }
        echo json_encode(['location' => $url]);
    }

    /** ফেরার পথ: শুধু এই পর্দার নিজের পাথ, নইলে শুরু। */
    private function backPath(string $path): string
    {
        $own = admin_base() . '/studio';

        return $path === $own || preg_match('#^' . preg_quote($own, '#') . '\?[A-Za-z0-9_=&%.\-+]*$#', $path) === 1 ? $path : $own;
    }

    private function fail(string $path, string $message): never
    {
        Session::flash('admin_errors', [$message]);
        redirect($path);
    }
}
