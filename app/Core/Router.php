<?php

declare(strict_types=1);

namespace App\Core;

/** Maps a URL + method (GET/POST) to a controller method, using config/routes.php. */
final class Router
{
    private static string $path = '/';

    public static function currentPath(): string
    {
        return self::$path;
    }

    public static function dispatch(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $raw = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = '/' . trim(rawurldecode($raw), '/');
        self::$path = $path;

        // Only normal characters are allowed in a path; anything odd is simply "not found"
        if (strlen($path) > 200 || !preg_match('#^/[\p{L}\p{N}/_\-.]*$#u', $path) || str_contains($path, '..')) {
            self::notFound();
            return;
        }

        /** @var list<array{0:string,1:string,2:array{0:class-string,1:string},3?:array<string,mixed>}> $routes */
        $routes = require BASE_PATH . '/config/routes.php';

        $pathExists = false;
        foreach ($routes as $route) {
            if ($route[1] !== $path) {
                continue;
            }
            $pathExists = true;
            if ($route[0] === $method) {
                [$class, $action] = $route[2];
                (new $class())->$action($route[3] ?? []);
                return;
            }
        }

        if ($pathExists) {
            http_response_code(405);
            header('Allow: GET, POST');
            View::render('pages/error', ['title' => 'অনুমোদিত নয়', 'code' => 405]);
            return;
        }

        self::notFound();
    }

    private static function notFound(): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
    }
}
