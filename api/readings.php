<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, max-age=0');

require_once __DIR__ . '/../app/json.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    respond_json(405, ['error' => 'method_not_allowed']);
}

$rawLocationId = $_GET['location_id'] ?? null;
$locationId = is_string($rawLocationId)
    ? filter_var($rawLocationId, FILTER_VALIDATE_INT)
    : false;
if ($locationId === false || $locationId === null || $locationId < 1) {
    respond_json(400, ['error' => 'invalid_location_id']);
}

// Mark as JSON request for consistent error handling in bootstrap
define('SMARTSLOPE_JSON_REQUEST', true);
require_once __DIR__ . '/../app/bootstrap.php';

try {
    $reading = (new ReadingRepository($pdo))->latestForActiveLocation((int) $locationId);
    if ($reading) {
        $reading['observed_at'] = str_replace(' ', 'T', $reading['observed_at']) . 'Z';
        $age = time() - strtotime($reading['observed_at']);
        $reading['stale'] = $age > 7200 || $age < -600;
        $reading['alert'] = $reading['stale'] ? null
            : (new AlertRepository($pdo))->activeForReading((int)$reading['reading_id']);
    }
    respond_json(200, ['data' => $reading]);
} catch (PDOException $exception) {
    error_log('SmartSlope reading API failed: ' . $exception->getMessage());
    respond_json(503, ['error' => 'service_unavailable']);
}
