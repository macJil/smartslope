<?php
// Simple Helper Functions - Based on PDF principles

// Start session
function start_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        
        // For localhost development, use less restrictive settings
        $samesite = 'Lax';
        $secure = $isHttps;
        
        // On localhost, disable secure flag and use Lax for SameSite
        if (isset($_SERVER['HTTP_HOST']) && (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
            $samesite = 'Lax';
            $secure = false;  // Don't require HTTPS for localhost
        }
        
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $secure,
            'samesite' => $samesite,
            'path' => '/'
        ]);
        
        session_start();
    }
}

// Clear session completely
function clear_session() {
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

// HTML escape
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get POST value safely
function post($key, $default = '') {
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Get GET value safely
function get($key, $default = '') {
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Build URL - handles subdirectory installations
function url($path = '') {
    // Simple and reliable URL builder for subdirectory installations
    static $base = null;
    if ($base === null) {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptDir = dirname($scriptName);
        // Handle the case where SCRIPT_NAME is just a filename (no directory)
        if ($scriptDir === '.' || $scriptDir === '\\' || $scriptDir === '/') {
            $base = '';
        } else {
            $base = rtrim($scriptDir, '/\\');
        }
    }
    
    $path = ltrim($path, '/\\');
    return ($base !== '' ? $base . '/' : '') . $path;
}

// Redirect
function redirect($path) {
    header("Location: " . url($path), true, 303);
    exit;
}

// Flash messages
function flash($key, $message = null) {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

// CSRF protection - temporarily simplified for development
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function valid_csrf() {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
           hash_equals(csrf_token(), (string)post('csrf_token'));
}

function require_post_csrf() {
    // CSRF protection disabled for development
}

// Authentication helpers
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}

function is_admin() {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function require_login() {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function require_admin() {
    if (!is_admin()) {
        redirect('index.php');
    }
}

// Password helpers
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// Date formatting
function local_date($value) {
    if (!$value) return '';
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date ? $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') : $value;
}

// Error handler
set_exception_handler(function (Throwable $error) {
    error_log('SmartSlope: ' . $error->getMessage() . ' in ' . $error->getFile() . ':' . $error->getLine());
    http_response_code(500);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "SmartSlope operation failed; check the PHP error log.\n");
        exit(1);
    }
    
    // In development, show more detailed error (but not in production)
    if (isset($_SERVER['HTTP_HOST']) && strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
        echo '<div style="padding: 20px; background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; margin: 20px;">';
        echo '<strong>Error:</strong> SmartSlope could not complete this request.<br>';
        echo '<small>File: ' . e($error->getFile()) . ':' . $error->getLine() . '</small><br>';
        echo '<small>Message: ' . e($error->getMessage()) . '</small>';
        echo '</div>';
    } else {
        echo 'SmartSlope could not complete this request. Please try again.';
    }
});

// Also catch errors
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});