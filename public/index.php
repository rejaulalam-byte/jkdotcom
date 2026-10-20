<?php

declare(strict_types=1);

/*
 * Front controller — every request enters here (see public/.htaccess).
 * Only this "public" folder must be reachable from the web.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

App\Core\Router::dispatch();
