<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
require_once __DIR__ . '/../app/json.php';
define('SMARTSLOPE_JSON_REQUEST', true);
require_once __DIR__ . '/../app/bootstrap.php';

if (empty($_SESSION['user_id']) || !in_array($_SESSION['role'] ?? '', ['user', 'admin'], true)) {
    respond_json(401, ['error' => 'authentication_required']);
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond_json(405, ['error' => 'method_not_allowed']);
}

try {
    $rows = (new ReadingRepository($pdo))->allCurrentForStudyArea();
    foreach ($rows as &$row) {
        $row['time'] = str_replace(' ', 'T', $row['observed_at']) . 'Z';
        $row['fetched_at'] = str_replace(' ', 'T', $row['fetched_at']) . 'Z';
        unset($row['observed_at']);
    }
    unset($row);
    respond_json(200, ['data' => ['current_readings' => $rows]]);
} catch (Throwable $exception) {
    error_log('SmartSlope saved readings lookup failed: ' . $exception->getMessage());
    respond_json(503, ['error' => 'database_unavailable']);
}
