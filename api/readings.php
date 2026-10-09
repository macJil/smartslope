<?php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../includes/risk.php';
require_once __DIR__ . '/../includes/data.php';
require_once __DIR__ . '/../includes/geography.php';
require_once __DIR__ . '/../includes/http.php';
require_once __DIR__ . '/../includes/weather.php';
require_once __DIR__ . '/../includes/awareness.php';
require_once __DIR__ . '/../includes/views.php';



session_start();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reading_json(int $status, array $body): void
{
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}
if (empty($_SESSION['user_id'])) {
    reading_json(401, ['error' => 'Please sign in again.']);
}
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    reading_json(405, ['error' => 'Method not allowed.']);
}
$locationId = filter_var($method === 'POST' ? post('location_id') : get('location_id'), FILTER_VALIDATE_INT);
if (!$locationId || $locationId < 1) {
    reading_json(422, ['error' => 'A positive location ID is required.']);
}
try {
    $location = get_location($locationId);
    if (!$location || !$location['active']) {
        reading_json(404, ['error' => 'Location not found.']);
    }
    if ($method === 'POST') {
        refresh_location($locationId);
    }
    $latest = get_latest_reading($locationId);
    $assessment = reading_assessment($latest, $location);
    $readings = get_readings($locationId, 10);
    if ($latest) {
        unset($latest['provider_payload'], $latest['adjustment_log']);
    }
    foreach ($readings as &$row) {
        unset($row['provider_payload'], $row['adjustment_log']);
    }
    unset($row);
    $baseline = susceptibility_lookup($location);
    $reportCounts = location_report_summary($locationId);
    reading_json(200, [
        'location' => array_intersect_key($location, array_flip(['id','name','purok','landmark','lat','lng','susceptibility'])),
        'latest' => $latest,
        'assessment' => $assessment,
        'baseline' => $baseline, 'notices' => awareness_notices($assessment, $baseline),
        'report_counts' => $reportCounts,
        'readings' => $readings,
        'view' => [
            'assessment' => ui_assessment_panel($latest, $location, $assessment),
            'readings' => ui_readings_rows($readings, (($_SESSION['role'] ?? '') === 'admin'), 'dashboard-readings', 'bulkDashboardReadingsForm', [$locationId => $location]),
            'high' => ui_current_count($readings, 'high'),
            'medium' => ui_current_count($readings, 'medium'),
            'history' => ui_history($readings),
            'reports' => ui_report_summary($reportCounts, (($_SESSION['role'] ?? '') === 'admin')),
        ],
    ]);
} catch (Throwable $error) {
    error_log('SmartSlope reading endpoint: ' . $error->getMessage());
    reading_json(503, ['error' => 'Weather data could not be loaded or saved. Previous readings remain available.']);
}
