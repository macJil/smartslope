<?php
declare(strict_types=1);

// Environment, application paths and lazy PDO connection.

function load_env_file(): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_file($envFile)) {
        return;
    }

    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }

        [$name, $value] = array_pad(explode('=', $line, 2), 2, '');
        $name = trim($name);
        $value = trim($value);

        if ($name === '') {
            continue;
        }

        $value = trim($value, " \t\n\r\0\x0B\"'");

        if (getenv($name) !== false || array_key_exists($name, $_ENV)) continue;
        if (!array_key_exists($name, $_ENV)) {
            $_ENV[$name] = $value;
        }
        if (!array_key_exists($name, $_SERVER)) {
            $_SERVER[$name] = $value;
        }
        putenv("{$name}={$value}");
    }
}

function env_value(string $name, string $default = ''): string {
    $value = $_ENV[$name] ?? getenv($name);
    return $value === false ? $default : (string)$value;
}

load_env_file();
date_default_timezone_set('UTC');

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================
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

// ============================================================================
// PATHS
// ============================================================================
define('APP_ROOT', dirname(__DIR__));
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
if (in_array(basename($scriptDirectory), ['actions', 'api', 'pages', 'scripts', 'tests'], true)) {
    $scriptDirectory = dirname($scriptDirectory);
}
define('APP_BASE_PATH', $config['base_path'] !== '' ? $config['base_path'] : (in_array($scriptDirectory, ['/', '.'], true) ? '' : $scriptDirectory));

// ============================================================================
// DATABASE CONNECTION
// ============================================================================
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        global $config;
        $dsn = "mysql:host={$config['db_host']};port={$config['db_port']};dbname={$config['db_name']};charset=utf8mb4";
        $pdo = new PDO($dsn, $config['db_user'], $config['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    return $pdo;
}
