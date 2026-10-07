<?php

declare(strict_types=1);

require_once __DIR__ . '/environment.php';

load_env_file();
date_default_timezone_set('UTC');

$config = [
    'db_host' => env_value('DB_HOST', '127.0.0.1'),
    'db_port' => (int)env_value('DB_PORT', '3306'),
    'db_name' => env_value('DB_DATABASE', 'smartslope_mvp'),
    'db_user' => env_value('DB_USERNAME', 'root'),
    'db_pass' => env_value('DB_PASSWORD', ''),
    'freshness_seconds' => max(60, (int)env_value('READING_MAX_AGE_SECONDS', '10800')),
    'future_tolerance_seconds' => 300,
    'base_path' => env_value('APP_BASE_PATH', ''),
];

require_once __DIR__ . '/paths.php';
require_once __DIR__ . '/Database.php';

// One connection per request, including transactions spanning several classes.
function db(): PDO
{
    static $database = null;
    if ($database === null) {
        $database = new Database($GLOBALS['config']);
    }
    return $database->connect();
}
