<?php

declare(strict_types=1);


date_default_timezone_set('Asia/Manila');


$databaseConfig = require dirname(__DIR__) . '/configs/config.php';
require_once dirname(__DIR__) . '/configs/paths.php';
$basePath = APP_BASE_PATH;

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
        'path' => $basePath === '' ? '/' : $basePath,
    ]);
    session_start();
}


require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/UserRepository.php';
require_once __DIR__ . '/ReportRepository.php';
require_once __DIR__ . '/ReadingRepository.php';
require_once __DIR__ . '/SensorRepository.php';
require_once __DIR__ . '/AlertRepository.php';
require_once __DIR__ . '/LocationRepository.php';
require_once __DIR__ . '/StudyArea.php';
require_once __DIR__ . '/RiskAnalyzer.php';
require_once __DIR__ . '/WeatherApiClient.php';


try {
    $pdo = Database::connect($databaseConfig);
} catch (PDOException $exception) {
    error_log('SmartSlope database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    if (defined('SMARTSLOPE_JSON_REQUEST') && SMARTSLOPE_JSON_REQUEST) {
        header('Content-Type: application/json; charset=utf-8');
        exit(json_encode(['error' => 'database_unavailable']));
    }
    exit('SmartSlope is temporarily unavailable. Check the local database configuration.');
}
