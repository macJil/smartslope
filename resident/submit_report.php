<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    flash('report_error', 'Your session expired. Please reload the page and try again.');
    redirect_to('resident/index.php');
}

$locationId = filter_var($_POST['location_id'] ?? null, FILTER_VALIDATE_INT);
$contactNumber = trim(post_string('contact_number'));
$email = strtolower(trim(post_string('email')));
$houseLandmark = trim(post_string('house_landmark'));
$message = trim(post_string('message'));
$messageLength = preg_match_all('/./us', $message, $matches);
$validContact = preg_match('/\A\+?[0-9]{7,15}\z/', $contactNumber) === 1;
$validEmail = $email === '' || (strlen($email) <= 254 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false);

if (!$locationId || $message === '' || $messageLength === false || $messageLength > 2000
    || strlen($houseLandmark) > 255 || !$validContact || !$validEmail) {
    flash('report_error', 'Choose a location, enter a valid contact number (7–15 digits), and provide a report of at most 2,000 characters. If you enter an email, it must be valid.');
    redirect_to('resident/index.php');
}

try {
    (new ReportRepository($pdo))->create(
        (int) $locationId,
        (int) $_SESSION['user_id'],
        $contactNumber,
        $email === '' ? null : $email,
        $houseLandmark === '' ? null : $houseLandmark,
        $message
    );
    flash('report_success', 'Your report was submitted for administrator review.');
} catch (InvalidArgumentException $exception) {
    flash('report_error', 'That location is not available. Please choose an active location.');
} catch (PDOException $exception) {
    error_log('SmartSlope report submission failed: ' . $exception->getMessage());
    $schemaMismatch = in_array((int)($exception->errorInfo[1] ?? 0), [1054, 1364], true);
    flash('report_error', $schemaMismatch
        ? 'The report database needs the contact fields migration. Please contact the administrator.'
        : 'The report could not be submitted. Please try again.');
}

redirect_to('resident/index.php');
