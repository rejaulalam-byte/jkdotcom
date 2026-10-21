<?php

declare(strict_types=1);

namespace App\Core;

/** Renders app/Views/<name>.php inside the shared layout. */
final class View
{
    /** @param array<string,mixed> $data variables made available to the view */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $content = self::boldBrand(self::capture($view, $data));
        echo self::capture($layout, $data + ['content' => $content]);
    }

    /** @param array<string,mixed> $data */
    private static function capture(string $view, array $data): string
    {
        $file = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $view);
        }

        // Isolated scope: the view only sees $data, never controller internals
        return (static function (string $__file, array $__data): string {
            extract($__data, EXTR_SKIP);
            ob_start();
            require $__file;

            return (string) ob_get_clean();
        })($file, $data);
    }

    /**
     * Shows the site name "Jorurikaj.com" bold + coloured wherever it appears in page text
     * (not in tags/attributes, scripts, e-mail or web addresses such as info@jorurikaj.com).
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
