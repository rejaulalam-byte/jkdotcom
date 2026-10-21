<?php

declare(strict_types=1);

/*
 * ফ্রন্ট কন্ট্রোলার — সব অনুরোধ এখান দিয়ে ঢোকে (public/.htaccess দেখুন)।
 * ওয়েব থেকে শুধু এই "public" ফোল্ডারটাই খোলা থাকবে।
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

App\Core\Router::dispatch();
