<?php

// Settings come from .env, or from these local-development defaults.
$envFile = __DIR__ . '/.env';
if (is_file($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        if ($name !== '' && getenv($name) === false && !isset($_ENV[$name])) {
            $_ENV[$name] = trim($value, " \t\n\r\0\x0B\"'");
        }
    }
}

function env_value(string $name, string $default = ''): string
{
    $value = $_ENV[$name] ?? getenv($name);
    return $value === false ? $default : (string)$value;
}

const APP_TIMEZONE = 'UTC';
date_default_timezone_set(APP_TIMEZONE);
define('DB_HOST', env_value('DB_HOST', '127.0.0.1'));
define('DB_PORT', env_value('DB_PORT', '3306'));
define('DB_NAME', env_value('DB_DATABASE', env_value('DB_NAME', 'smartslope_mvp')));
define('DB_USER', env_value('DB_USERNAME', env_value('DB_USER', 'root')));
define('DB_PASS', env_value('DB_PASSWORD', env_value('DB_PASS', '')));
$config = [
    'db_name' => DB_NAME,
    'freshness_seconds' => max(60, (int)env_value('READING_MAX_AGE_SECONDS', '10800')),
    'future_tolerance_seconds' => 300,
];

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
