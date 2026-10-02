<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/bootstrap.php';
start_session();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function reading_json(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR);
    exit;
}
if (!is_logged_in()) reading_json(401, ['error' => 'Please sign in again.']);
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if (!in_array($method, ['GET', 'POST'], true)) {
    header('Allow: GET, POST');
    reading_json(405, ['error' => 'Method not allowed.']);
}
if ($method === 'POST' && !valid_csrf()) reading_json(403, ['error' => 'Invalid request token. Reload the page and try again.']);
$locationId = filter_var($method === 'POST' ? post('location_id') : get('location_id'), FILTER_VALIDATE_INT);
if (!$locationId || $locationId < 1) reading_json(422, ['error' => 'A positive location ID is required.']);
try {
    $location = get_location($locationId);
    if (!$location || !$location['active']) reading_json(404, ['error' => 'Location not found.']);
    if ($method === 'POST') refresh_location($locationId);
    $latest = get_latest_reading($locationId);
    reading_json(200, [
        'location' => array_intersect_key($location, array_flip(['id','name','purok','landmark','lat','lng','susceptibility'])),
        'latest' => $latest,
        'assessment' => reading_assessment($latest, $location),
        'readings' => get_readings($locationId, 10),
    ]);
} catch (Throwable $error) {
    error_log('SmartSlope reading endpoint: ' . $error->getMessage());
    reading_json(503, ['error' => 'Weather data could not be loaded or saved. Previous readings remain available.']);
}
