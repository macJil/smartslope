<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        flash('login_error', 'Your session expired. Please try again.');
        redirect_to('index.php');
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $user = (new UserRepository($pdo))->authenticate($username, $password);

    if ($user === null) {
        flash('login_error', 'The username or password is incorrect.');
        redirect_to('index.php');
    }

    session_regenerate_id(true);
    // Discard anonymous session state before assigning authenticated identity.
    $_SESSION = [];
    $_SESSION['user_id'] = (int) $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['contact_number'] = $user['contact_number'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['created_at'] = $user['created_at'];

    redirect_to($user['role'] === 'admin'
        ? 'admin/index.php'
        : 'resident/index.php');
}
