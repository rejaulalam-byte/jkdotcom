<?php

declare(strict_types=1);

namespace App\Core;

/** সার্ভারের মেইল-ব্যবস্থা (cPanel) দিয়ে ইমেইল পাঠায়: সাধারণ লেখার, আর ছবিসহ HTML ইমেইল। */
final class Mailer
{
    public static function send(string $to, string $subject, string $body, ?string $replyTo = null): bool
    {
        $from = self::from();
        if (!self::validAddresses([$to, $from, $replyTo])) {
            return false;
        }

        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return @mail($to, self::encodeSubject($subject), $body, implode("\r\n", $headers));
    }

    /**
     * HTML ইমেইল, সঙ্গে সাধারণ-লেখার রূপ (যে মেইল-প্রোগ্রাম HTML দেখায় না তার জন্য) এবং ঐচ্ছিক ছোট ছবি।
     * ছবি ইমেইলের ভেতরেই জোড়া থাকে (cid:), তাই বাইরের কোনো লিংক খুলতে হয় না।
     *
     * @param array{path:string,mime:string}|null $image HTML-এ <img src="cid:photo"> দিয়ে দেখানো ছবি
     */
    public static function sendHtml(string $to, string $subject, string $html, string $text, ?array $image = null, ?string $replyTo = null): bool
    {
        $from = self::from();
        if (!self::validAddresses([$to, $from, $replyTo])) {
            return false;
        }

        [$contentType, $body] = self::buildHtmlBody($html, $text, $image);
        $headers = [
            'From: ' . $from,
            'MIME-Version: 1.0',
            'Content-Type: ' . $contentType,
        ];
        if ($replyTo !== null) {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        return @mail($to, self::encodeSubject($subject), $body, implode("\r\n", $headers));
    }

    /**
     * HTML + সাধারণ লেখা + ছবির MIME কাঠামো: multipart/related { multipart/alternative { text, html }, ছবি }।
     *
     * @param array{path:string,mime:string}|null $image
     * @return array{0:string,1:string} [Content-Type হেডারের মান, বার্তার অংশ]
     */
    public static function buildHtmlBody(string $html, string $text, ?array $image): array
    {
        $alt = 'alt_' . bin2hex(random_bytes(8));
        $related = 'rel_' . bin2hex(random_bytes(8));
        $part = static fn (string $type, string $content): string => 'Content-Type: ' . $type . "; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($content));

        $alternative = '--' . $alt . "\r\n" . $part('text/plain', $text)
            . '--' . $alt . "\r\n" . $part('text/html', $html)
            . '--' . $alt . "--\r\n";

        $raw = $image !== null && is_file($image['path']) ? file_get_contents($image['path']) : false;
        if ($raw === false) {
            return ['multipart/alternative; boundary="' . $alt . '"', $alternative];
        }

        $body = '--' . $related . "\r\nContent-Type: multipart/alternative; boundary=\"" . $alt . "\"\r\n\r\n" . $alternative
            . '--' . $related . "\r\n"
            . 'Content-Type: ' . $image['mime'] . "\r\n"
            . "Content-Transfer-Encoding: base64\r\n"
            . "Content-ID: <photo>\r\n"
            . "Content-Disposition: inline; filename=\"photo\"\r\n\r\n"
            . chunk_split(base64_encode($raw))
            . '--' . $related . "--\r\n";

        return ['multipart/related; boundary="' . $related . '"', $body];
    }

    private static function from(): string
    {
        return Env::get('MAIL_FROM', 'no-reply@jorurikaj.com') ?? 'no-reply@jorurikaj.com';
    }

    /**
     * filter_var লাইন-ব্রেক বাতিল করে; এতে ইমেইল হেডার ইনজেকশন আটকায়।
     *
     * @param list<string|null> $addresses
     */
    private static function validAddresses(array $addresses): bool
    {
        foreach ($addresses as $address) {
            if ($address !== null && !filter_var($address, FILTER_VALIDATE_EMAIL)) {
                return false;
            }
        }

        return true;
    }

    private static function encodeSubject(string $subject): string
    {
        return '=?UTF-8?B?' . base64_encode(str_replace(["\r", "\n"], ' ', $subject)) . '?=';
    }
}
