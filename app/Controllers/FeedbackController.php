<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\Logger;
use App\Core\Mailer;
use App\Core\RateLimiter;
use App\Core\Security;
use App\Core\Session;
use App\Models\FeedbackRepository;

/** "মতামত জমা দিন" ফর্ম (হোম ও যোগাযোগ পেইজ) সামলায়। */
final class FeedbackController
{
    /** ফর্ম থেকে ফেরার অনুমোদিত ঠিকানা (open-redirect ঠেকাতে তালিকা বাঁধা)। */
    private const RETURN_TO = ['/#feedback', '/contact#feedback', '/faq#feedback'];

    /** @param array<string,mixed> $meta */
    public function store(array $meta = []): void
    {
        // জমার পর যে পেইজ থেকে ফর্ম এসেছিল সেখানে ফেরা; অনুমোদিত ঠিকানার বাইরে হলে হোম
        $back = in_array($_POST['return'] ?? '', self::RETURN_TO, true) ? (string) $_POST['return'] : '/#feedback';

        if (!Security::verifyCsrf($_POST['_token'] ?? null)) {
            Session::flash('feedback_errors', ['নিরাপত্তা যাচাই ব্যর্থ হয়েছে। পেজটি রিফ্রেশ করে আবার চেষ্টা করুন।']);
            redirect($back);
        }

        // লুকানো "website" ঘর: আসল মানুষ ফাঁকা রাখে, স্প্যাম-বট ভরে ফেলে
        if (trim((string) ($_POST['website'] ?? '')) !== '') {
            redirect($back);
        }

        if (RateLimiter::tooMany('feedback', 5, 3600)) {
            Session::flash('feedback_errors', ['অনেকবার চেষ্টা করা হয়েছে। অনুগ্রহ করে কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
            redirect($back);
        }

        $data = [
            'name' => $this->clean($_POST['name'] ?? '', 100),
            'email' => $this->clean($_POST['email'] ?? '', 150),
            'phone' => $this->clean($_POST['phone'] ?? '', 20),
            'subject' => $this->clean($_POST['subject'] ?? '', 150),
            'message' => $this->clean($_POST['message'] ?? '', 2000, true),
        ];

        // পাঁচটি ঘরই আবশ্যক
        $errors = [];
        if ($data['name'] === '') {
            $errors[] = 'আপনার নাম লিখুন।';
        }
        if ($data['email'] === '') {
            $errors[] = 'ইমেইল ঠিকানা লিখুন।';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'ইমেইল ঠিকানাটি সঠিক নয়।';
        }
        if ($data['phone'] === '') {
            $errors[] = 'ফোন নাম্বার লিখুন।';
        } elseif (!preg_match('/^[0-9+\-\s()০-৯]{6,20}$/u', $data['phone'])) {
            $errors[] = 'ফোন নাম্বারটি সঠিক নয়।';
        }
        if ($data['subject'] === '') {
            $errors[] = 'বিষয় বস্তু লিখুন।';
        }
        if (mb_strlen($data['message']) < 5) {
            $errors[] = 'আপনার পরামর্শ / মতামত লিখুন।';
        }

        if ($errors !== []) {
            Session::flash('feedback_errors', $errors);
            Session::flash('feedback_old', $data);
            redirect($back);
        }

        if (!FeedbackRepository::save($data)) {
            Session::flash('feedback_errors', ['দুঃখিত, মতামতটি সংরক্ষণ করা যায়নি। কিছুক্ষণ পরে আবার চেষ্টা করুন।']);
            Session::flash('feedback_old', $data);
            redirect($back);
        }

        $to = Env::get('MAIL_TO', 'info@jorurikaj.com') ?? 'info@jorurikaj.com';
        $body = "নতুন মতামত — jorurikaj.com\n\n"
            . "নাম: {$data['name']}\nইমেইল: {$data['email']}\nফোন: {$data['phone']}\n"
            . "বিষয়: {$data['subject']}\n\n{$data['message']}\n";
        $replyTo = filter_var($data['email'], FILTER_VALIDATE_EMAIL) ? $data['email'] : null;
        if (!Mailer::send($to, 'নতুন মতামত: ' . ($data['subject'] !== '' ? $data['subject'] : $data['name']), $body, $replyTo)) {
            // অ্যাডমিন প্যানেলের জন্য আগেই সংরক্ষিত, তাই কিছু হারাবে না
            Logger::error('Feedback e-mail could not be sent');
        }

        Session::flash('feedback_ok', 'আপনার মতামত পাঠানো হয়েছে। ধন্যবাদ!');
        redirect($back);
    }

    /**
     * ফাঁকা অংশ ছাঁটে, নিয়ন্ত্রণ-অক্ষর সরায় ও দৈর্ঘ্য সীমিত করে।
     * শুধু "Cc" (নিয়ন্ত্রণ) অক্ষর বাদ যায় — বাংলা যুক্তাক্ষরের জন্য দরকারি
     * অদৃশ্য সংযোগকারী (ZWJ/ZWNJ) রেখে দেওয়া হয়।
     */
    private function clean(mixed $value, int $max, bool $multiline = false): string
    {
        $value = is_string($value) ? trim($value) : '';
        $pattern = $multiline ? '/[^\P{Cc}\n]/u' : '/\p{Cc}/u';
        $value = (string) preg_replace($pattern, '', $value);

        return mb_substr($value, 0, $max);
    }
}
