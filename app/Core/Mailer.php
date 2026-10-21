<?php

declare(strict_types=1);

namespace App\Core;

/** সার্ভারের মেইল-ব্যবস্থা (cPanel) দিয়ে সাধারণ লেখার ইমেইল পাঠায়। */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = Env::get('MAIL_FROM', 'no-reply@jorurikaj.com') ?? 'no-reply@jorurikaj.com';

        // filter_var লাইন-ব্রেক বাতিল করে; এতে ইমেইল হেডার ইনজেকশন আটকায়
        foreach ([$to, $from, $replyTo] as $address) {
            if ($address !== null && !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }
        $encodedSubject = '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';

        return @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    }
}
