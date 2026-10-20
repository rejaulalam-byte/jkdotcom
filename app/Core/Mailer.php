<?php

declare(strict_types=1);

namespace App\Core;

/** Sends plain-text e-mail through the server's mail system (cPanel). */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = Env::get('MAIL_FROM', 'no-reply@jorurikaj.com') ?? 'no-reply@jorurikaj.com';

        // filter_var rejects line breaks, which blocks e-mail header injection
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
