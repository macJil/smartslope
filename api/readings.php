<?php
// Simple Readings API Endpoint
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Reading.php';

start_session();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function api_json(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

if (!is_logged_in()) {
    api_json(401, ['error' => 'Sign in required.']);
}

$database = new Database();
$pdo = $database->connect();
$reading = new Reading($pdo);

// Get readings based on parameters
$locationId = filter_var($_GET['location_id'] ?? null, FILTER_VALIDATE_INT);
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));

if ($locationId) {
    $readings = $reading->getByLocation($locationId, $limit);
} else {
    $readings = $reading->getAllRecent($limit);
}

// Format response
$formattedReadings = [];
foreach ($readings as $r) {
    $formattedReadings[] = [
        'id' => $r['id'],
        'location_id' => $r['location_id'],
        'risk_level' => $r['risk_level'] ?? null,
        'rainfall_1h' => $r['rainfall_1h'] ?? null,
        'rainfall_24h' => $r['rainfall_24h'] ?? null,
        'temperature' => $r['temperature'] ?? null,
        'humidity' => $r['humidity'] ?? null,
        'observed_at' => $r['observed_at'] ?? null,
    ];
}

api_json(200, ['readings' => $formattedReadings]);
