<?php

// Small shared helpers: errors, escaping, input, redirects, dates and flash messages.

// Turn database failures into useful messages without showing SQL or passwords.
function database_error_message(PDOException $error): string
{
    $code = (int)($error->errorInfo[1] ?? 0);
    if ($code === 1049) {
        return 'Database not found. Check DB_NAME in config.php against your MySQL database name.';
    }
    if ($code === 1045 || $code === 1044) {
        return 'Database access denied. Check DB_USER and DB_PASS in config.php.';
    }
    if ($code === 2002 || $code === 2003) {
        return 'Cannot connect to MySQL. Start the database server and check DB_HOST and DB_PORT in config.php.';
    }
    if ($error->getCode() === '42S02') {
        return 'A required SmartSlope table is missing. Select your SmartSlope database in config.php; use database/schema.sql only for a new database.';
    }
    if ($error->getCode() === '42S22') {
        return 'The database columns do not match this SmartSlope version. Check the selected database and run the documented migration.';
    }
    if (str_contains($error->getMessage(), 'could not find driver')) {
        return 'PHP needs the PDO MySQL extension enabled. Check the PHP version used by XAMPP or MAMP.';
    }
    return 'The database operation failed. Check the PHP error log for the recorded database error.';
}

function show_application_error(Throwable $error): void
{
    error_log('SmartSlope: ' . $error->getMessage());
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, $error->getMessage() . "\n");
        exit(1);
    }
    http_response_code(500);
    $message = 'This page could not load. Check the PHP error log for details.';
    if ($error instanceof PDOException) {
        $message = database_error_message($error);
    }
    echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
}
set_exception_handler('show_application_error');

// Basic input and output helpers

function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Get POST value safely

function post(string $key, $default = '')
{
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Get GET value safely

function get(string $key, $default = '')
{
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

// Redirect

function redirect(string $path): void
{
    header("Location: " . $path, true, 303);
    exit;
}

// Display local datetime

function local_date(?string $value): string
{
    if (!$value) {
        return '';
    }
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date ? $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') : $value;
}

// Store or retrieve a flash message

function flash(string $key, ?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}
