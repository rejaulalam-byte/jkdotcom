<?php

declare(strict_types=1);

namespace App\Core;

/** app/Views/<নাম>.php ভিউকে সবার সাধারণ কাঠামো (layout)-এর ভেতরে সাজিয়ে দেখায়। */
final class View
{
    /** @param array<string,mixed> $data ভিউয়ের ভেতরে যে ভেরিয়েবলগুলো পাওয়া যাবে */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $content = self::boldBrand(self::capture($view, $data));
        $page = self::capture($layout, $data + ['content' => $content]);
        // এডিটরের লেখায় style থাকলে শুধু সেই মানগুলো CSP-তে অনুমতি পায় (RichText)
        $hashes = RichText::styleHashes();
        if ($hashes !== []) {
            Security::sendCsp($hashes);
        }
        echo $page;
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        // আলাদা স্কোপ: ভিউ শুধু $data-র জিনিস দেখতে পায়, কন্ট্রোলারের ভেতরের কিছু নয়
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        })($file, $data);
    }

    /**
     * পেইজের লেখার যেখানেই সাইটের নাম "Jorurikaj.com" আছে, সেখানে মোটা ও রঙিন করে।
     * (ট্যাগ/অ্যাট্রিবিউট, স্ক্রিপ্ট, ইমেইল বা ওয়েব ঠিকানা, যেমন info@jorurikaj.com, বাদ থাকে।)
     */
    private static function boldBrand(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];
        $skip = 0;
        foreach ($parts as $i => $part) {
            if ($part !== '' && $part[0] === '<') {
                if (preg_match('#^<(script|style|textarea|option|title)\b#i', $part)) {
                    $skip++;
                } elseif (preg_match('#^</(script|style|textarea|option|title)\b#i', $part)) {
                    $skip = max(0, $skip - 1);
                }
                continue;
            }
            if ($skip === 0) {
                $parts[$i] = (string) preg_replace('/(?<![\w@.\/-])jorurikaj\.com(?![A-Za-z0-9_])/iu', '<strong class="brandname">$0</strong>', $part);
            }
        }

        return implode('', $parts);
    }
}
