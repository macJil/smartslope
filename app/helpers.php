<?php
declare(strict_types=1);

require_once __DIR__ . '/geography.php';

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

function url(string $path = ''): string {
    $base = '/' . trim(APP_BASE_PATH, '/');
    return rtrim($base, '/') . '/' . ltrim($path, '/');
}

function redirect(string $path): void {
    header("Location: " . url($path), true, 303);
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
