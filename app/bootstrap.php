<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Logger;
use App\Core\Security;
use App\Core\Session;
use App\Core\View;

/*
 * Application bootstrap: autoloading, configuration, error handling,
 * security headers and the session. Runs once per request.
 */

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('PHP 8.1 or newer is required.');
}

// PSR-4 style autoloader: App\Core\Router  ->  app/Core/Router.php
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $file = BASE_PATH . '/app/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require BASE_PATH . '/app/helpers.php';

Env::load(BASE_PATH . '/.env');
date_default_timezone_set('Asia/Dhaka');
mb_internal_encoding('UTF-8');

// Errors: never shown to visitors in production, always written to the log
$isLocal = Env::get('APP_ENV', 'production') === 'local';
ini_set('display_errors', $isLocal ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
error_reporting(E_ALL);

set_exception_handler(static function (Throwable $e) use ($isLocal): void {
    Logger::error('Unhandled exception: ' . $e::class . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if ($isLocal) {
        echo '<pre>' . e((string) $e) . '</pre>';
        return;
    }
    View::render('pages/error', ['title' => 'দুঃখিত, সমস্যা হয়েছে', 'code' => 500]);
});

Security::sendHeaders();
Session::start();
