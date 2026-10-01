<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';
require_admin();

$allLocations = !isset($_GET['location_id']);
$locationId = $allLocations ? null : filter_var($_GET['location_id'], FILTER_VALIDATE_INT);
if (!$allLocations && (!$locationId || $locationId < 1)) {
    http_response_code(400);
    exit('Invalid location.');
}
if (!$allLocations) {
    $location = (new LocationRepository($pdo))->find((int)$locationId);
    if (!$location || !(int)$location['is_active']) {
        http_response_code(404);
        exit('Location unavailable.');
    }
}
$repository = new ReadingRepository($pdo);
$readings = $allLocations ? $repository->allCurrentForStudyArea()
    : $repository->currentForLocation((int)$locationId, null);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="smartslope-' . ($allLocations ? 'all-locations' : 'location-' . (int)$locationId) . '-reading-log-' . date('Y-m-d') . '.csv"');
header('Cache-Control: no-store, max-age=0');

$output = fopen('php://output', 'wb');
if ($output === false) {
    http_response_code(500);
    exit('Could not create CSV output.');
}

// The BOM helps spreadsheet applications detect UTF-8 correctly.
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, [
    'Observation ID',
    'Observed At (Asia/Manila)',
    'Location',
    'Fetched At (Asia/Manila)',
    'Temperature (C)',
    'Humidity (%)',
    'Precipitation (mm)',
    'Rain (mm)',
    'Showers (mm)',
    'Wind speed (km/h)',
    'Wind gusts (km/h)',
    'Rainfall 1h (mm)', 'Rainfall 24h (mm)', 'Rainfall 72h (mm)', 'Risk at fetch', 'Stale at fetch',
], ',', '"', '');

$safeCsvText = static function (?string $value): string {
    $value = $value ?? '';
    // Prevent spreadsheet formula execution for text originating in API data.
    if ($value !== '' && preg_match('/\A[\s]*[=+@-]/u', $value) === 1) {
        return "'" . $value;
    }
    return $value;
};

foreach ($readings as $reading) {
    fputcsv($output, [
        (int) $reading['observation_id'],
        display_local_datetime($reading['observed_at']),
        $safeCsvText((string) $reading['location_name']),
        display_local_datetime($reading['fetched_at']),
        $reading['temperature_2m'],
        $reading['relative_humidity_2m'],
        $reading['precipitation'],
        $reading['rain'],
        $reading['showers'],
        $reading['wind_speed_10m'],
        $reading['wind_gusts_10m'],
        $reading['rainfall_1h_mm'], $reading['rainfall_24h_mm'], $reading['rainfall_72h_mm'],
        $safeCsvText($reading['risk_level']), (int)$reading['is_stale'],
    ], ',', '"', '');
}

fclose($output);
exit;
