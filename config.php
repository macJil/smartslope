<?php

// Edit these values to match your XAMPP or MAMP MySQL server.
// XAMPP commonly uses port 3306 and a blank root password.
// For MAMP, check the MySQL port and password in the application settings.
const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'smartslope_mvp';
const DB_USER = 'root';
const DB_PASS = '';
const NOMINATIM_ENABLED = false;

const APP_TIMEZONE = 'UTC';
date_default_timezone_set(APP_TIMEZONE);
$config = [
    'db_name' => DB_NAME,
    'freshness_seconds' => 10800,
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

// Local HTTP development: each page calls session_start() directly.
ini_set('session.use_strict_mode', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
