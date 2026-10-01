<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET'); http_response_code(405); exit('Method not allowed.');
}
try {
    $reports = (new ReportRepository($pdo))->adminQueue();
} catch (PDOException $exception) {
    error_log('SmartSlope report CSV failed: ' . $exception->getMessage());
    http_response_code(503); exit('Reports are temporarily unavailable.');
}
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartslope-reports-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store');
$output = fopen('php://output', 'wb');
if ($output === false) { http_response_code(500); exit('Could not create CSV.'); }
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Report ID','Reporter','Email','Phone','Location','Landmark','Observation',
    'Status','Submitted (PHT)','Reviewer','Reviewed (PHT)'], ',', '"', '');
$safe = static function ($value): string {
    $value = (string)($value ?? '');
    return preg_match('/\A[\s]*[=+@-]/u', $value) ? "'" . $value : $value;
};
foreach ($reports as $report) {
    fputcsv($output, array_map($safe, [
        $report['report_id'], $report['reporter_name'], $report['reporter_email'],
        $report['reporter_contact_number'], $report['location_name'], $report['house_landmark'],
        $report['message'], $report['status'], display_local_datetime($report['created_at']),
        $report['reviewer_name'], display_local_datetime($report['reviewed_at'])
    ]), ',', '"', '');
}
fclose($output);
exit;
