<?php
/**
 * SmartSlope - Configuration File
 * Central configuration for database, application settings, and constants
 */

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'smartslope_mvp';
const DB_USER = 'root';
const DB_PASS = '';

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

const APP_NAME = 'SmartSlope';
const APP_VERSION = '1.0.0';
const READING_MAX_AGE_SECONDS = 10800; // 3 hours
const NOMINATIM_ENABLED = true;
const NOMINATIM_USER_AGENT = 'SmartSlope/1.0';

// ============================================================================
// RISK THRESHOLDS (mm)
// ============================================================================

const RISK_LOW_1H = 10;
const RISK_LOW_24H = 25;
const RISK_LOW_72H = 50;

const RISK_MEDIUM_1H = 25;
const RISK_MEDIUM_24H = 50;
const RISK_MEDIUM_72H = 100;

const RISK_HIGH_1H = 50;
const RISK_HIGH_24H = 100;
const RISK_HIGH_72H = 150;

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

/**
 * Get PDO database connection
 * Uses static caching for single connection per request
 */
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . 
               ";dbname=" . DB_NAME . ";charset=utf8mb4";
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            $pdo->exec("SET time_zone = '+00:00'");
        } catch (PDOException $e) {
            error_log('SmartSlope Database Error: ' . $e->getMessage());
            throw $e;
        }
    }
    return $pdo;
}

// ============================================================================
// JSON RESPONSE HELPER
// ============================================================================

/**
 * Send JSON response and exit
 */
function json_response($data, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
