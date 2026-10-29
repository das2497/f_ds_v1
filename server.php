<?php

/**
 * Laravel - PHP built-in server router.
 *
 * Serves existing files under public/ as-is; everything else goes
 * through the framework. Needed because `php -S` with a router script
 * never falls back to static files on its own.
 */
$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

require_once __DIR__.'/public/index.php';
