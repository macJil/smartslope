<?php
declare(strict_types=1);

// PHP does not load .env files automatically. This small INI read keeps local
// configuration dependency-free; real server environment variables win.
$root = dirname(__DIR__);
$fileValues = [];
$envFile = $root . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    $parsed = parse_ini_file($envFile, false, INI_SCANNER_RAW);
    if (is_array($parsed)) {
        $fileValues = $parsed;
    }
}

$readSetting = static function (string $key, string $default) use ($fileValues): string {
    $environmentValue = getenv($key);
    if ($environmentValue !== false) {
        return (string) $environmentValue;
    }

    return isset($fileValues[$key]) ? (string) $fileValues[$key] : $default;
};

return [
    'base_path' => $readSetting('APP_BASE_PATH', ''),
    'host' => $readSetting('DB_HOST', '127.0.0.1'),
    'port' => (int) $readSetting('DB_PORT', '3306'),
    'database' => $readSetting('DB_DATABASE', 'smartslope_mvp'),
    'username' => $readSetting('DB_USERNAME', 'root'),
    'password' => $readSetting('DB_PASSWORD', ''),
];