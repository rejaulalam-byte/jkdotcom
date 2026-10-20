<?php

declare(strict_types=1);

namespace App\Core;

/** Renders app/Views/<name>.php inside the shared layout. */
final class View
{
    /** @param array<string,mixed> $data variables made available to the view */
    public static function render(string $view, array $data = [], string $layout = 'layouts/main'): void
    {
        $content = self::capture($view, $data);
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
}
