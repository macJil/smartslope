<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/app/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        flash('register_error', 'Your session expired. Please try again.');
        redirect_to('configs/register.php');
    }

    $fullName = trim(post_string('full_name'));
    $username = trim(post_string('username'));
    $email = strtolower(trim(post_string('email')));
    $contactNumber = trim(post_string('contact_number'));
    $password = post_string('password');

    // Count Unicode characters for names while keeping usernames ASCII and predictable.
    $fullNameLength = preg_match_all('/./us', $fullName);
    $validUsername = preg_match('/\A[A-Za-z0-9._-]{3,50}\z/', $username) === 1;
    $validContact = preg_match('/\A\+?[0-9]{7,15}\z/', $contactNumber) === 1;
    $validEmail = strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    $validPasswordLength = strlen($password) >= 8 && strlen($password) <= 72;

    if ($fullNameLength === false || $fullNameLength < 1 || $fullNameLength > 100
        || !$validUsername || !$validEmail || !$validContact || !$validPasswordLength) {
        flash(
            'register_error',
            'Use a name up to 100 characters, a 3–50 character username, a valid email, a 7–15 digit contact number (optional leading +), and a password of 8–72 bytes.'
        );
        redirect_to('configs/register.php');
    }


    try {
        $users = new UserRepository($pdo);
        $duplicates = $users->duplicateFields($username, $email, $contactNumber);
        if ($duplicates) {
            flash('register_error', 'Already registered: ' . implode(', ', array_map(
                static fn($field) => str_replace('_', ' ', $field), $duplicates)) . '. Use different account details.');
            redirect_to('configs/register.php');
        }
        $users->create($fullName, $username, $email, $contactNumber, $password);
        flash('register_success', 'Account created. You can now sign in.');
        redirect_to('configs/register.php');
    } catch (PDOException $exception) {
        error_log('SmartSlope registration failed: ' . $exception->getMessage());
        // MySQL 1062 alone means duplicate key; 23000 includes other constraints.
        $driverCode = (int) ($exception->errorInfo[1] ?? 0);
        if ($driverCode === 1062) {
            $duplicates = (new UserRepository($pdo))->duplicateFields($username, $email, $contactNumber);
            flash('register_error', $duplicates
                ? 'Already registered: ' . implode(', ', array_map(static fn($field) => str_replace('_', ' ', $field), $duplicates)) . '.'
                : 'The account database has an ID or index conflict. Ask the administrator to check the registration error log.');
            redirect_to('configs/register.php');
        }
        $schemaMismatch = in_array($driverCode, [1054, 1364, 1048, 3819, 4025, 1452], true);
        flash('register_error', $schemaMismatch
            ? 'The account database needs its email and contact columns updated. Ask the administrator to check the SmartSlope migration instructions.'
            : 'The account could not be created. Please try again.');
        redirect_to('configs/register.php');
    }
}
