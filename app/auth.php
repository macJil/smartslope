<?php
declare(strict_types=1);

function start_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        ini_set('session.use_strict_mode', '1');
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        session_set_cookie_params(['httponly' => true, 'secure' => $isHttps, 'samesite' => 'Lax']);
        session_start();
    }
}


function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}


function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}


function valid_csrf(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
        hash_equals(csrf_token(), (string)post('csrf_token'));
}


function require_post_csrf(): void {
    if (!valid_csrf()) {
        http_response_code(403);
        exit('Invalid request token. Reload the page and try again.');
    }
}

// Check if logged in

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

// Check if admin

function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

// Require user login

function require_login(): void {
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

// Require admin

function require_admin(): void {
    if (!is_admin()) {
        redirect('index.php');
    }
}

// Set flash message

function hash_password(string $password): string {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Verify password

function verify_password(string $password, string $hash): bool {
    return password_verify($password, $hash);
}

// Authenticate user

function authenticate(string $username, string $password): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if ($user && verify_password($password, $user['password'])) {
        return $user;
    }
    return null;
}

// Create user
