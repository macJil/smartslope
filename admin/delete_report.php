<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST'); http_response_code(405); exit('Method not allowed.');
}
$id = filter_var($_POST['report_id'] ?? null, FILTER_VALIDATE_INT);
if (!csrf_is_valid($_POST['csrf_token'] ?? null) || !$id || $id < 1) {
    flash('admin_report_message', 'Reload the page and select a valid report.');
    redirect_to('admin/index.php');
}
try {
    $deleted = (new ReportRepository($pdo))->delete((int)$id);
    flash('admin_report_message', $deleted ? 'Report deleted.' : 'Report not found.');
} catch (PDOException $exception) {
    error_log('SmartSlope report deletion failed: ' . $exception->getMessage());
    flash('admin_report_message', 'The report could not be deleted.');
}
redirect_to('admin/index.php');
