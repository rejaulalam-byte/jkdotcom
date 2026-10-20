<?php

declare(strict_types=1);

namespace App\Core;

/** Writes problems to storage/logs (never shown to visitors). */
final class Logger
{
    public static function error(string $message): void
    {
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ' . str_replace(["\r", "\n"], ' ', $message) . PHP_EOL;
        @file_put_contents($dir . '/app-' . date('Y-m') . '.log', $line, FILE_APPEND | LOCK_EX);
    }
}
