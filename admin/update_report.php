<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
    flash('admin_report_message', 'Your session expired. Reload the page and try again.');
    redirect_to('admin/index.php');
}

$reportId = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
$status = (string) ($_POST['status'] ?? '');

if (!$reportId || !in_array($status, ['reviewed', 'resolved'], true)) {
    flash('admin_report_message', 'The report update was not valid.');
    redirect_to('admin/index.php');
}

try {
    $updated = (new ReportRepository($pdo))->updateStatus(
        (int) $reportId,
        $status,
        (int) $_SESSION['user_id']
    );
    flash('admin_report_message', $updated ? 'Report status updated.' : 'The report was not found.');
} catch (PDOException $exception) {
    error_log('SmartSlope report status update failed: ' . $exception->getMessage());
    flash('admin_report_message', 'The report status could not be updated.');
}

redirect_to('admin/index.php');