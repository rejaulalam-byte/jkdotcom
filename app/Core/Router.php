<?php

declare(strict_types=1);

namespace App\Core;

/** URL ও মেথড (GET/POST) থেকে config/routes.php দেখে কন্ট্রোলারের মেথড খুঁজে চালায়। */
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

        // পাথে শুধু সাধারণ অক্ষর চলবে; অদ্ভুত কিছু থাকলে সোজা "পাওয়া যায়নি"
        if (strlen($path) > 200 || !preg_match('#^/[\p{L}\p{N}/_\-.]*$#u', $path) || str_contains($path, '..')) {
            self::notFound();
            return;
        }

        /** @var list<array{0:string,1:string,2:array{0:class-string,1:string},3?:array<string,mixed>}> $routes */
        $routes = require BASE_PATH . '/config/routes.php';

        $pathExists = false;
        foreach ($routes as $route) {
            $params = self::match($route[1], $path);
            if ($params === null) {
                continue;
            }
            $pathExists = true;
            if ($route[0] === $method) {
                [$class, $action] = $route[2];
                (new $class())->$action(($route[3] ?? []) + $params);
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

    /**
     * রুটের পাথ কি URL-এর সঙ্গে মেলে? রুটে {slug} থাকতে পারে: শুধু a-z, 0-9 ও "-" দিয়ে
     * গড়া একটা অংশ (যেমন /services/{slug} মেলে /services/electrician-এর সঙ্গে)।
     *
     * @return array<string,string>|null  {slug}-এর মান, না মিললে null
     */
    private static function match(string $routePath, string $path): ?array
    {
        if (!str_contains($routePath, '{slug}')) {
            return $routePath === $path ? [] : null;
        }
        $regex = '#^' . str_replace('\{slug\}', '([a-z0-9-]{1,80})', preg_quote($routePath, '#')) . '$#';

        return preg_match($regex, $path, $m) === 1 ? ['slug' => $m[1]] : null;
    }

    private static function notFound(): void
    {
        http_response_code(404);
        View::render('pages/error', ['title' => 'পেজটি পাওয়া যায়নি', 'code' => 404]);
    }
}
