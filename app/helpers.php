<?php
// Basic helpers
function e($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post(string $key, $default = '') {
    $value = $_POST[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

function get(string $key, $default = '') {
    $value = $_GET[$key] ?? $default;
    return is_scalar($value) ? $value : $default;
}

function redirect(string $path): void {
    header("Location: " . $path, true, 303);
    exit;
}

function local_date(?string $value): string {
    if (!$value) return '';
    $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, new DateTimeZone('UTC'));
    return $date ? $date->setTimezone(new DateTimeZone('Asia/Manila'))->format('M j, Y g:i A') : $value;
}

function flash(string $key, ?string $message = null): ?string {
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $value = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return is_string($value) ? $value : null;
}

// Error handling
function database_error_message(PDOException $error): string {
    $code = (int)($error->errorInfo[1] ?? 0);
    if ($code === 1049) return 'Database not found. Check DB_DATABASE in .env.';
    if ($code === 1045 || $code === 1044) return 'Database access denied. Check DB_USERNAME and DB_PASSWORD in .env.';
    if ($code === 2002 || $code === 2003) return 'Cannot connect to MySQL. Start the database server and check DB_HOST and DB_PORT in .env.';
    if ($error->getCode() === '42S02') return 'A required SmartSlope table is missing.';
    if ($error->getCode() === '42S22') return 'The database columns do not match this version.';
    if (str_contains($error->getMessage(), 'could not find driver')) return 'PHP needs the PDO MySQL extension enabled.';
    return 'The database operation failed. Check the PHP error log.';
}

function show_application_error(Throwable $error): void {
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