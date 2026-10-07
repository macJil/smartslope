<?php
/**
 * SmartSlope - Weather API Endpoint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

start_session();

if (!is_logged_in()) {
    json_response(['error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$locationId = (int)($_GET['location_id'] ?? 0);

if ($method === 'GET') {
    // Get readings for a location
    if ($locationId > 0) {
        $readings = get_readings_by_location($locationId);
        foreach ($readings as &$r) {
            $r['assessment'] = assess_reading($r);
        }
        json_response(['success' => true, 'data' => $readings]);
    } else {
        json_response(['error' => 'Location ID required'], 400);
    }
} elseif ($method === 'POST') {
    // Fetch and save weather for a location
    if ($locationId > 0) {
        try {
            $userId = $_SESSION['user_id'] ?? null;
            $eventId = fetch_and_save_weather($locationId, $userId);
            
            // Get the reading we just created
            $reading = get_latest_reading($locationId);
            if ($reading) {
                $reading['assessment'] = assess_reading($reading);
            }
            
            json_response(['success' => true, 'event_id' => $eventId, 'data' => $reading]);
        } catch (Exception $e) {
            json_response(['error' => $e->getMessage()], 500);
        }
    } else {
        json_response(['error' => 'Location ID required'], 400);
    }
} else {
    json_response(['error' => 'Method not allowed'], 405);
}
