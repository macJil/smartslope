<?php

declare(strict_types=1);

function start_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
        session_start();
    }
}


// Check if logged in

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

// Check if admin

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Require user login

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

// Require admin

function require_admin(): void
{
    if (!is_admin()) {
        redirect('index.php');
    }
}

// Authenticate user

function authenticate(string $username, string $password): ?array
{
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        return $user;
    }
    return null;
}
