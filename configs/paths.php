<?php
declare(strict_types=1);

// Filesystem paths are independent of the URL used by the browser.
define('APP_ROOT', dirname(__DIR__));
$pathSettings = $databaseConfig ?? require __DIR__ . '/config.php';
$basePath = rtrim(trim($pathSettings['base_path']), '/');

// Allow an empty prefix or plain URL path segments, never a host or query.
if ($basePath !== '' && preg_match('~\A(?:/[A-Za-z0-9_-]+)+\z~', $basePath) !== 1) {
    throw new RuntimeException('APP_BASE_PATH must be empty or a path such as /landslide.');
}
define('APP_BASE_PATH', $basePath);
unset($pathSettings, $basePath);