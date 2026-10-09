<?php

// Small shared helpers: errors, escaping, input, redirects, dates and flash messages.

// Keep the database error simple; the full error goes to the PHP log.
const DATABASE_ERROR_MESSAGE = 'Database unavailable. Check MySQL, config.php and the database setup.';

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
        $message = DATABASE_ERROR_MESSAGE;
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
