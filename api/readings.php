<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/config.php';
start_session();
require_login();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

$locationId = filter_var($_POST['location_id'] ?? $_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
$location = $locationId ? get_location($locationId) : null;
if (!$location || !$location['active']) {
    http_response_code(404);
    echo json_encode(['error' => 'Location not found.']);
    exit;
}
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!valid_csrf()) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid request token. Reload the page and try again.']);
            exit;
        }
        refresh_location($locationId);
    } elseif ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed.']);
        exit;
    }
    echo json_encode([
        'location' => ['id' => (int)$location['id'], 'name' => $location['name']],
        'latest' => get_latest_reading($locationId),
        'readings' => get_readings($locationId, 10),
    ], JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $error) {
    error_log('SmartSlope reading endpoint: ' . $error->getMessage());
    http_response_code(503);
    echo json_encode(['error' => 'A current weather reading could not be saved. The previous data is still available.']);
}
