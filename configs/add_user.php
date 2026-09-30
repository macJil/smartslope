<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        flash('register_error', 'Your session expired. Please try again.');
        redirect_to('configs/register.php');
    }

    $fullName = trim((string) ($_POST['full_name'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    // Count Unicode characters for names while keeping usernames ASCII and predictable.
    $fullNameLength = preg_match_all('/./us', $fullName);
    $validUsername = preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) === 1;
    $validEmail = strlen($email) <= 254
        && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $validPasswordLength = strlen($password) >= 8 && strlen($password) <= 72;

    if ($fullNameLength === false || $fullNameLength < 1 || $fullNameLength > 100
        || !$validUsername || !$validEmail || !$validPasswordLength) {
        flash(
            'register_error',
            'Use a name up to 100 characters, a 3–50 character username (letters, numbers, dot, underscore, or hyphen), a valid email, and a password of 8–72 bytes.'
        );
        redirect_to('configs/register.php');
    }

    $email = strtolower($email);

    try {
        (new UserRepository($pdo))->create($fullName, $username, $email, $password);
        flash('register_success', 'Account created. You can now sign in.');
        redirect_to('configs/register.php');
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            flash('register_error', 'That username or email is already registered.');
            redirect_to('configs/register.php');
        }
        error_log('SmartSlope registration failed: ' . $exception->getMessage());
        flash('register_error', 'The account could not be created. Please try again.');
        redirect_to('configs/register.php');
    }
}