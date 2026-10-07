<?php
/**
 * SmartSlope - Address Lookup API Endpoint
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';

if ($lat && $lng) {
    try {
        $address = lookup_address($lat, $lng);
        json_response(['success' => true, 'address' => $address]);
    } catch (Exception $e) {
        json_response(['error' => $e->getMessage()], 500);
    }
} else {
    json_response(['error' => 'Latitude and longitude required'], 400);
}
