<?php
// Simple Address API Endpoint
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Location.php';

start_session();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function address_json(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!is_logged_in()) {
    address_json(401, ['error' => 'Sign in required.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    header('Allow: GET');
    address_json(405, ['error' => 'Method not allowed.']);
}

// Simple version - use the Location model directly
$database = new Database();
$pdo = $database->connect();
$location = new Location($pdo);

$lat = filter_var($_GET['lat'] ?? null, FILTER_VALIDATE_FLOAT);
$lng = filter_var($_GET['lng'] ?? null, FILTER_VALIDATE_FLOAT);

if ($lat === false || $lng === false || !$location->isInIrisan($lat, $lng)) {
    address_json(422, ['error' => 'Select an Irisan point.']);
}

// For now, return empty address since we don't have the Nominatim integration
address_json(200, ['address' => '']);
