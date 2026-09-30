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
$houseLandmark = trim((string) ($_POST['house_landmark'] ?? ''));
$message = trim((string) ($_POST['message'] ?? ''));
$messageLength = preg_match_all('/./us', $message, $matches);

if (!$locationId || $message === '' || $messageLength === false || $messageLength > 2000
    || strlen($houseLandmark) > 255) {
    flash('report_error', 'Choose a location and enter a report of at most 2,000 characters.');
    redirect_to('resident/index.php');
}

try {
    (new ReportRepository($pdo))->create(
        (int) $locationId,
        (int) $_SESSION['user_id'],
        $houseLandmark === '' ? null : $houseLandmark,
        $message
    );
    flash('report_success', 'Your report was submitted for administrator review.');
} catch (InvalidArgumentException $exception) {
    flash('report_error', 'That location is not available. Please choose an active location.');
} catch (PDOException $exception) {
    error_log('SmartSlope report submission failed: ' . $exception->getMessage());
    flash('report_error', 'The report could not be submitted. Please try again.');
}

redirect_to('resident/index.php');