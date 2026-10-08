<?php
function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params([
            'httponly' => true,
            'secure' => $isHttps,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function require_login(): void {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function require_admin(): void {
    if (!is_admin()) {
        redirect('index.php');
    }
}